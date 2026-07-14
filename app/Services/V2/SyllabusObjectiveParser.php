<?php

namespace App\Services\V2;

/**
 * Deterministic parser: extracted syllabus text -> structured learning
 * objectives. Consumes the page-marked text produced by
 * storage/syllabus/tools/extract_pdf_text.py and returns the official
 * section map + numbered objectives with (a)(b)(c) sub-items.
 *
 * Output is UNTRUSTED by design (equations arrive linearized by PDF text
 * extraction, e.g. "speed = distance time v = s t") - rows land as
 * status 'extracted' and only a human-reviewed overlay promotes them to
 * 'validated'. The parser therefore optimises for structural fidelity and
 * self-validation, not for prettifying wording.
 *
 * Self-validation rules that make false positives structurally impossible:
 *  - objective numbers must be strictly sequential (1, 2, 3...) within a
 *    leaf section, so page numbers / data values never start an objective;
 *  - a "N Title" line is a topic heading only when N advances the topic
 *    sequence AND N differs from the next expected objective number;
 *  - sub-item keys must be strictly sequential letters (a, b, c...).
 */
class SyllabusObjectiveParser
{
    /** Verbatim noise lines/prefixes stripped before parsing (page furniture, third-party watermark). */
    private const NOISE_PREFIXES = [
        'Cambridge O Level Physics 5054 syllabus',
        'www.cambridgeinternational.org',
    ];

    private const WATERMARK = 'bestexamhelp.com';

    public function __construct(
        private string $contentStart = '3 Subject content',
        private string $contentEnd = '4 Details of the assessment',
    ) {}

    /**
     * @return array{
     *     topics: array<int, string>,
     *     sections: array<int, array>,
     *     warnings: array<int, string>,
     *     totals: array{sections: int, leaf_sections: int, objectives: int}
     * }
     */
    public function parse(string $text): array
    {
        [$lines, $pages] = $this->stripNoise($text);
        [$lines, $pages] = $this->sliceSubjectContent($lines, $pages);

        $topics = [];
        $sections = [];       // code => section array (built in encounter order)
        $order = [];
        $warnings = [];
        $lastTopic = 0;
        $lastSection = null;  // last accepted section code (document-order guard)
        $current = null;      // current section code
        $currentLo = null;    // index into $sections[$current]['objectives']
        $currentSub = null;   // index into that objective's sub_items

        foreach ($lines as $i => $rawLine) {
            $line = trim($rawLine);
            $page = $pages[$i];

            if ($line === '' || $line === 'continued') {
                continue;
            }

            // Some PDF editions (first seen: 5054 2026-2028) emit stray spaces
            // INSIDE leading section codes ("1.7. 2 Work", "1.7 .4 Efficiency").
            // Codes are structural, so normalize them at line start; titles are
            // left exactly as extracted (they are untrusted review input).
            $line = preg_replace_callback(
                '/^(\d)\s*\.\s*(\d)(\s*\.\s*(\d))?(?=\s)/u',
                fn ($m) => $m[1].'.'.$m[2].(isset($m[4]) && $m[4] !== '' ? '.'.$m[4] : ''),
                $line
            );

            // Section heading: "1.2 Motion", "1.5.1 Balanced and unbalanced forces",
            // possibly suffixed "continued" when a section spans a page break.
            // Guards against prose like "1.5 N force...": the title must start with
            // an uppercase letter, a NEW code must advance in document order, and an
            // already-seen code is only re-opened by an explicit "... continued".
            if (preg_match('/^(\d\.\d(?:\.\d)?)\s+([A-Z].*)$/u', $line, $m)) {
                $code = $m[1];
                $isContinued = (bool) preg_match('/\bcontinued$/u', $m[2]);
                $isNew = ! isset($sections[$code])
                    && $this->advancesSectionOrder($code, $lastSection)
                    // A real heading is immediately followed by objective 1 or a
                    // deeper/next section heading - prose that merely looks like a
                    // heading (e.g. a wrapped "1.5 N force ..." line) is not.
                    && $this->nextLineStartsSectionBody($lines, $i);

                if ($isNew || (isset($sections[$code]) && $isContinued)) {
                    if ($isNew) {
                        $sections[$code] = [
                            'code' => $code,
                            'title' => trim(preg_replace('/\s+continued$/u', '', $m[2])),
                            'topic' => (int) strtok($code, '.'),
                            'pdf_pages' => [$page],
                            'objectives' => [],
                        ];
                        $order[] = $code;
                        $lastSection = $code;
                    } elseif (! in_array($page, $sections[$code]['pdf_pages'], true)) {
                        $sections[$code]['pdf_pages'][] = $page;
                    }

                    $current = $code;
                    $currentLo = null;
                    $currentSub = null;

                    continue;
                }
                // otherwise: falls through and is treated as objective body text
            }

            // Topic heading: "2 Thermal physics". Sequential rule disambiguates from
            // objective lines - verified against all 5 topic boundaries of 5054_y25.
            if (preg_match('/^(\d)\s+([A-Z].*)$/u', $line, $m) && (int) $m[1] === $lastTopic + 1) {
                $expected = $this->nextObjectiveNumber($sections, $current);
                if ($current === null || (int) $m[1] !== $expected) {
                    $topics[(int) $m[1]] = trim($m[2]);
                    $lastTopic = (int) $m[1];
                    $current = null;
                    $currentLo = null;
                    $currentSub = null;

                    continue;
                }
            }

            if ($current === null) {
                continue; // preamble between the section-3 heading and the first subtopic
            }

            $section = &$sections[$current];

            // Objective start: "7 Sketch, plot and interpret..." - or a bare number on
            // its own line (1.1's layout splits numbers from wording). Only accepted
            // when the number is exactly the next in sequence for this section.
            if (preg_match('/^(\d{1,2})(?:\s+(\S.*))?$/u', $line, $m)) {
                $number = (int) $m[1];
                if ($number === $this->nextObjectiveNumber($sections, $current)) {
                    $section['objectives'][] = [
                        'number' => $number,
                        'parts' => [],
                        'sub_items' => [],
                        'pdf_pages' => [$page],
                    ];
                    $currentLo = array_key_last($section['objectives']);
                    $currentSub = null;

                    $rest = trim($m[2] ?? '');
                    if ($rest !== '') {
                        if (preg_match('/^\(([a-z])\)\s*(.*)$/u', $rest, $s)) {
                            $section['objectives'][$currentLo]['sub_items'][] = ['key' => $s[1], 'parts' => [$s[2]]];
                            $currentSub = 0;
                        } else {
                            $section['objectives'][$currentLo]['parts'][] = $rest;
                        }
                    }
                    unset($section);

                    continue;
                }
            }

            if ($currentLo === null) {
                $warnings[] = "orphan text in {$current} (p{$page}): ".mb_substr($line, 0, 60);
                unset($section);

                continue;
            }

            $objective = &$section['objectives'][$currentLo];
            if (! in_array($page, $objective['pdf_pages'], true)) {
                $objective['pdf_pages'][] = $page;
            }

            // Sub-item start "(a) ..." - keys must advance alphabetically, so option
            // markers inside prose like "(NTC only)" never open a sub-item.
            if (preg_match('/^\(([a-z])\)\s*(.*)$/u', $line, $s)) {
                $expectedKey = $objective['sub_items'] === []
                    ? 'a'
                    : chr(ord(end($objective['sub_items'])['key']) + 1);
                if ($s[1] === $expectedKey) {
                    $objective['sub_items'][] = ['key' => $s[1], 'parts' => [$s[2]]];
                    $currentSub = array_key_last($objective['sub_items']);
                    unset($objective, $section);

                    continue;
                }
            }

            // Continuation line: joins the open sub-item, else the objective body.
            if ($currentSub !== null) {
                $objective['sub_items'][$currentSub]['parts'][] = $line;
            } else {
                $objective['parts'][] = $line;
            }
            unset($objective, $section);
        }

        return $this->finalise($topics, $sections, $order, $warnings);
    }

    /**
     * Remove page markers and per-page furniture, tracking the PDF page of every
     * surviving line. Returns [lines, pages] parallel arrays.
     *
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private function stripNoise(string $text): array
    {
        $lines = [];
        $pages = [];
        $page = 0;
        $headerZone = 0;   // lines still eligible to be page furniture
        $seenSiteLine = false;

        foreach (preg_split('/\R/u', $text) as $raw) {
            $line = rtrim($raw);
            $trimmed = trim($line);

            if (preg_match('/^===== PAGE (\d+) =====$/', $trimmed, $m)) {
                $page = (int) $m[1];
                $headerZone = 6;
                $seenSiteLine = false;

                continue;
            }

            if ($headerZone > 0) {
                $isNoise = false;
                foreach (self::NOISE_PREFIXES as $prefix) {
                    if (str_starts_with($trimmed, $prefix)) {
                        $isNoise = true;
                        $seenSiteLine = $seenSiteLine || str_starts_with($trimmed, 'www.');
                        break;
                    }
                }
                // The printed page number sits between the Cambridge line and the
                // site line; a bare number after the site line is real content.
                if (! $isNoise && ! $seenSiteLine && preg_match('/^\d{1,3}$/', $trimmed)) {
                    $isNoise = true;
                }
                if (str_contains($trimmed, 'Back to contents page')) {
                    $isNoise = true;
                    $seenSiteLine = true;
                }
                if ($isNoise) {
                    $headerZone--;

                    continue;
                }
                $headerZone = 0;
            }

            if ($trimmed === self::WATERMARK) {
                continue;
            }

            $lines[] = $line;
            $pages[] = $page;
        }

        return [$lines, $pages];
    }

    /**
     * Keep only the subject-content span (between the section-3 heading and the
     * section-4 heading).
     *
     * @return array{0: array<int, string>, 1: array<int, int>}
     */
    private function sliceSubjectContent(array $lines, array $pages): array
    {
        $start = null;
        $end = count($lines);

        // Exact-line anchors: the contents page lists "3 Subject content ..... 10"
        // as a dotted TOC entry, so a prefix match would slice from the TOC and
        // swallow the front matter. Only the real heading is the bare line.
        $startRe = '/^'.preg_quote($this->contentStart, '/').'\s*$/u';
        $endRe = '/^'.preg_quote($this->contentEnd, '/').'\s*$/u';

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($start === null && preg_match($startRe, $trimmed)) {
                $start = $i + 1;

                continue;
            }
            if ($start !== null && preg_match($endRe, $trimmed)) {
                $end = $i;
                break;
            }
        }

        if ($start === null) {
            return [[], []];
        }

        return [
            array_values(array_slice($lines, $start, $end - $start)),
            array_values(array_slice($pages, $start, $end - $start)),
        ];
    }

    /** True when the next non-empty line opens a section body: objective 1, a section heading, or a topic heading. */
    private function nextLineStartsSectionBody(array $lines, int $index): bool
    {
        for ($j = $index + 1, $n = count($lines); $j < $n; $j++) {
            $next = trim($lines[$j]);
            if ($next === '' || $next === 'continued') {
                continue;
            }

            // A real heading is followed by its first objective ("1 ..." or the
            // bare-number layout) or by a child/sibling section heading. Nothing
            // broader: "N Capital..." would match ordinary objective lines and
            // let wrapped prose (e.g. "1.5 N acting on...") open fake sections.
            return preg_match('/^1(\s|$)/', $next) === 1
                || preg_match('/^\d\.\d/', $next) === 1;
        }

        return false;
    }

    /** True when $code sorts after $previous in natural section order (1.5.6 -> 1.6, 1.8 -> 2.1.1). */
    private function advancesSectionOrder(string $code, ?string $previous): bool
    {
        if ($previous === null) {
            return true;
        }

        $a = array_map('intval', explode('.', $previous));
        $b = array_map('intval', explode('.', $code));

        // Lexicographic segment comparison (PHP's array <=> compares count first,
        // which would wrongly rank 1.6 below 1.5.6). A deeper code extending the
        // previous one (1.5 -> 1.5.1) advances; a prefix (1.5.6 -> 1.5) does not.
        for ($i = 0, $len = max(count($a), count($b)); $i < $len; $i++) {
            $x = $a[$i] ?? -1;
            $y = $b[$i] ?? -1;
            if ($x !== $y) {
                return $y > $x;
            }
        }

        return false; // equal codes never re-open through this path
    }

    private function nextObjectiveNumber(array $sections, ?string $current): int
    {
        if ($current === null || $sections[$current]['objectives'] === []) {
            return 1;
        }

        return end($sections[$current]['objectives'])['number'] + 1;
    }

    /** Join accumulated line parts, mark leaves, compute totals. */
    private function finalise(array $topics, array $sections, array $order, array $warnings): array
    {
        $join = fn (array $parts): string => trim(preg_replace(
            '/\s{2,}/u',
            ' ',
            implode(' ', array_map('trim', $parts))
        ));

        $out = [];
        $objectiveTotal = 0;
        $leafCount = 0;

        foreach ($order as $code) {
            $section = $sections[$code];
            $isLeaf = ! collect($order)->contains(fn ($other) => str_starts_with($other, $code.'.'));

            $objectives = array_map(fn (array $lo): array => [
                'number' => $lo['number'],
                'text' => $join($lo['parts']),
                'sub_items' => array_map(
                    fn (array $si): array => ['key' => $si['key'], 'text' => $join($si['parts'])],
                    $lo['sub_items']
                ),
                'pdf_pages' => $lo['pdf_pages'],
            ], $section['objectives']);

            if (! $isLeaf && $objectives !== []) {
                $warnings[] = "non-leaf section {$code} captured objectives - check source structure";
            }

            $objectiveTotal += count($objectives);
            $leafCount += $isLeaf ? 1 : 0;

            $out[] = [
                'code' => $code,
                'title' => $section['title'],
                'topic' => $section['topic'],
                'leaf' => $isLeaf,
                'pdf_pages' => $section['pdf_pages'],
                'lo_count' => count($objectives),
                'objectives' => $objectives,
            ];
        }

        return [
            'topics' => $topics,
            'sections' => $out,
            'warnings' => $warnings,
            'totals' => [
                'sections' => count($out),
                'leaf_sections' => $leafCount,
                'objectives' => $objectiveTotal,
            ],
        ];
    }
}
