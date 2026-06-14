<?php

namespace App\Services\Questions;

class QuestionTextNormalizer
{
    /**
     * Greek symbols and other unicode glyphs commonly found in physics text,
     * mapped to ASCII tokens the classifier can match against keyword lists.
     */
    private const GLYPH_MAP = [
        'µ' => ' micro ', 'μ' => ' micro ',
        'Ω' => ' ohm ', 'ω' => ' omega ',
        'ρ' => ' rho ', 'σ' => ' sigma ',
        'θ' => ' theta ', 'ϕ' => ' phi ', 'φ' => ' phi ',
        'λ' => ' lambda ', 'Λ' => ' lambda ',
        'π' => ' pi ', 'Π' => ' pi ',
        'α' => ' alpha ', 'β' => ' beta ', 'γ' => ' gamma ',
        'Δ' => ' delta ', 'δ' => ' delta ',
        'τ' => ' tau ', 'Φ' => ' flux ',
        '°' => ' deg ',
        '×' => ' x ', '·' => ' ', '−' => '-', '–' => '-', '—' => '-',
        '²' => '^2', '³' => '^3', '¹' => '^1', '⁰' => '^0',
        '⁴' => '^4', '⁵' => '^5', '⁶' => '^6', '⁷' => '^7', '⁸' => '^8', '⁹' => '^9',
        '₀' => '_0', '₁' => '_1', '₂' => '_2', '₃' => '_3', '₄' => '_4',
        '₅' => '_5', '₆' => '_6', '₇' => '_7', '₈' => '_8', '₉' => '_9',
        '√' => ' sqrt ', '∝' => ' proportional ', '≈' => ' approx ',
        '→' => ' arrow ', '←' => ' arrow ', '↔' => ' arrow ',
        '∞' => ' infinity ',
    ];

    /**
     * Normalize raw question text for keyword scoring.
     */
    public function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $t = strtr($text, self::GLYPH_MAP);
        $t = mb_strtolower($t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? '';
        return trim($t);
    }

    /**
     * Build a single normalized search blob from a question's structured fields.
     *
     * @param array{
     *     question_text?:?string,
     *     options?:array<int,array<string,mixed>>,
     *     option_table?:?array<string,mixed>,
     *     assets?:array<int,array<string,mixed>>,
     *     layout_type?:?string,
     *     correct_answer?:?string,
     * } $question
     */
    public function blob(array $question): string
    {
        $parts = [];
        $parts[] = $question['question_text'] ?? '';

        foreach ($question['options'] ?? [] as $opt) {
            $parts[] = $opt['text'] ?? '';
        }

        $table = $question['option_table'] ?? null;
        if (is_array($table)) {
            foreach ($table['headers'] ?? [] as $h) {
                $parts[] = is_string($h) ? $h : '';
            }
            foreach ($table['rows'] ?? [] as $row) {
                if (is_array($row)) {
                    foreach ($row as $cell) {
                        $parts[] = is_string($cell) ? $cell : '';
                    }
                }
            }
        }

        foreach ($question['assets'] ?? [] as $asset) {
            $parts[] = $asset['caption'] ?? '';
            $parts[] = $asset['ocr_text'] ?? '';
            $labels = $asset['diagram_labels'] ?? null;
            if (is_array($labels)) {
                foreach ($labels as $label) {
                    $parts[] = is_string($label) ? $label : '';
                }
            }
        }

        $parts[] = $question['layout_type'] ?? '';

        $joined = implode(' ', array_filter(array_map('strval', $parts), fn ($s) => $s !== ''));
        return $this->normalize($joined);
    }
}
