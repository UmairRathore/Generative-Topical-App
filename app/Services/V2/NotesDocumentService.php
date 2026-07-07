<?php

namespace App\Services\V2;

use App\Models\V2\NotesNotebook;
use App\Models\V2\NotesPage;
use App\Models\V2\NotesPageVersion;
use App\Models\V2\NotesSection;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use App\Support\SignedImage;
use Illuminate\Support\Facades\DB;

/**
 * Plumbing for the block-based Notes. The document itself is BlockNote's
 * native block array, stored verbatim on the page; this service owns the
 * denormalized search fields (plain_text, block_types_json), the throttled
 * version snapshots, and the sidebar tree (with first-visit provisioning).
 */
class NotesDocumentService
{
    public const MAX_VERSIONS = 20;

    /** Don't snapshot more often than this - autosave fires every few seconds. */
    public const SNAPSHOT_EVERY_SECONDS = 120;

    /** Block props that hold student-visible text (indexed for search). */
    private const TEXT_PROPS = ['front', 'back', 'source', 'markdown', 'title', 'questionStem', 'caption', 'label', 'latex'];

    /** Persist a new document: snapshot if due, recompute search fields, bump the concurrency counter. */
    public function apply(NotesPage $page, array $document, ?string $title = null): NotesPage
    {
        $this->snapshotIfDue($page);

        $page->fill([
            'document_json'    => $document,
            'plain_text'       => $this->flattenText($document),
            'block_types_json' => $this->blockTypes($document),
            'content_version'  => $page->content_version + 1,
            'save_status'      => NotesPage::SAVE_SAVED,
            'last_edited_at'   => now(),
        ]);
        if ($title !== null && trim($title) !== '') {
            $page->title = mb_substr(trim($title), 0, 200);
        }
        $page->save();

        return $page;
    }

    /** Restore a snapshot: the current state is snapshotted first, so restore is always reversible. */
    public function restore(NotesPage $page, NotesPageVersion $version): NotesPage
    {
        $this->snapshotNow($page);

        return $this->apply($page, $version->document_json ?? [], $version->title);
    }

    /* ============================ Snapshots ============================ */

    public function snapshotIfDue(NotesPage $page): void
    {
        if ($page->document_json === null) {
            return; // nothing worth keeping yet
        }

        $latest = $page->versions()->first();
        if ($latest && $latest->created_at && $latest->created_at->gt(now()->subSeconds(self::SNAPSHOT_EVERY_SECONDS))) {
            return;
        }

        $this->snapshotNow($page);
    }

    public function snapshotNow(NotesPage $page): void
    {
        if ($page->document_json === null) {
            return;
        }

        NotesPageVersion::create([
            'page_id'         => $page->id,
            'student_id'      => $page->student_id,
            'title'           => $page->title,
            'document_json'   => $page->document_json,
            'content_version' => $page->content_version,
        ]);

        $keep = $page->versions()->limit(self::MAX_VERSIONS)->pluck('id');
        $page->versions()->whereNotIn('id', $keep)->delete();
    }

    /* ========================== Search fields ========================== */

    /** Flatten every visible string in the document (inline runs, table cells, card fronts/backs, snapshots). */
    public function flattenText(array $document): string
    {
        $out = [];
        $this->walkText($document, $out);

        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter($out))));
    }

    /** Distinct block types present (recursive) - powers "find all flashcard blocks". */
    public function blockTypes(array $document): array
    {
        $types = [];
        $walk = function ($blocks) use (&$walk, &$types) {
            foreach ((array) $blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (! empty($block['type'])) {
                    $types[$block['type']] = true;
                }
                if (! empty($block['children'])) {
                    $walk($block['children']);
                }
            }
        };
        $walk($document);

        return array_keys($types);
    }

    private function walkText(array $blocks, array &$out): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            foreach (self::TEXT_PROPS as $prop) {
                $v = $block['props'][$prop] ?? null;
                if (is_string($v) && $v !== '') {
                    $out[] = $v;
                }
            }

            $content = $block['content'] ?? null;
            if (is_array($content)) {
                // Table blocks nest rows/cells; everything else is a flat inline array.
                if (($content['type'] ?? null) === 'tableContent') {
                    foreach ($content['rows'] ?? [] as $row) {
                        foreach ($row['cells'] ?? [] as $cell) {
                            $out[] = $this->inlineText(is_array($cell) && isset($cell['content']) ? $cell['content'] : $cell);
                        }
                    }
                } else {
                    $out[] = $this->inlineText($content);
                }
            }

            if (! empty($block['children']) && is_array($block['children'])) {
                $this->walkText($block['children'], $out);
            }
        }
    }

    private function inlineText($content): string
    {
        $parts = [];
        foreach ((array) $content as $run) {
            if (! is_array($run)) {
                continue;
            }
            if (isset($run['text']) && is_string($run['text'])) {
                $parts[] = $run['text'];
            } elseif (isset($run['content'])) {
                $parts[] = $this->inlineText($run['content']); // links wrap inner runs
            }
        }

        return implode(' ', $parts);
    }

    /* ========================= Question figures ======================= */

    /**
     * Mint fresh, viewer-bound signed URLs for the question_figure reference
     * blocks in a document - but ONLY for questions the student still reaches
     * through a released mistake. Nothing is stored in the note; the URL is
     * regenerated on every view and expires. Returns { imagePath: url }.
     */
    public function figureUrls(array $document, Student $student): array
    {
        $refs = [];
        $walk = function ($blocks) use (&$walk, &$refs) {
            foreach ((array) $blocks as $b) {
                if (! is_array($b)) {
                    continue;
                }
                if (($b['type'] ?? '') === 'question_figure') {
                    $qid = (int) ($b['props']['questionId'] ?? 0);
                    $path = (string) ($b['props']['imagePath'] ?? '');
                    if ($qid && $path !== '') {
                        $refs[] = ['q' => $qid, 'p' => $path];
                    }
                }
                if (! empty($b['children'])) {
                    $walk($b['children']);
                }
            }
        };
        $walk($document);
        if ($refs === []) {
            return [];
        }

        // Access re-check: the student must still have a released mistake on the
        // question. Loses access → the figure won't resolve, by design.
        $allowed = StudentMistake::where('student_id', $student->id)
            ->whereIn('question_id', array_values(array_unique(array_column($refs, 'q'))))
            ->whereHas('latestExam', fn ($q) => $q->whereNotNull('results_released_at'))
            ->pluck('question_id')->flip();

        $urls = [];
        foreach ($refs as $r) {
            if (! isset($allowed[$r['q']]) || isset($urls[$r['p']])) {
                continue;
            }
            $urls[$r['p']] = SignedImage::url($r['p'], ['question_id' => $r['q']]);
        }

        return $urls;
    }

    /* ============================== Tree =============================== */

    /**
     * Sidebar/picker tree (notebooks -> sections -> pages). Subject notebooks
     * (Physics, Chemistry, ...) are provisioned from the student's active
     * enrollments and listed first; free-form notebooks ("My Notes") trail.
     */
    public function tree(Student $student): array
    {
        $this->provisionSubjectNotebooks($student);

        return NotesNotebook::where('student_id', $student->id)
            ->where('is_archived', false)
            ->orderByRaw('subject_id IS NULL')->orderBy('title')->orderBy('id')
            ->with(['sections' => fn ($q) => $q->where('is_archived', false)
                ->with(['pages' => fn ($p) => $p->where('is_archived', false)
                    ->orderByDesc('is_pinned')->orderBy('sort_order')->orderByDesc('last_edited_at')
                    ->select(['id', 'section_id', 'title', 'icon', 'is_pinned', 'is_favorite', 'last_edited_at'])])])
            ->get()
            ->map(fn ($nb) => [
                'id'        => $nb->getRouteKey(),
                'title'     => $nb->title,
                'emoji'     => $nb->emoji,
                'subjectId' => $nb->subject_id,
                'sections'  => $nb->sections->map(fn ($s) => [
                    'id'      => $s->getRouteKey(),
                    'title'   => $s->title,
                    'topicId' => $s->topic_id,
                    'pages'   => $s->pages->map(fn ($p) => [
                        'id'        => $p->getRouteKey(),
                        'title'     => $p->title,
                        'icon'      => $p->icon,
                        'pinned'    => $p->is_pinned,
                        'favorite'  => $p->is_favorite,
                        'ago'       => $p->last_edited_at?->diffForHumans(short: true),
                        'url'        => route('v2.student.notes.pages.show', $p),
                        'importUrl'  => route('v2.student.notes.pages.import', $p),
                        'outlineUrl' => route('v2.student.notes.pages.outline', $p),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all();
    }

    /** Fallback provisioning: "My Notes / General" for notes created with no subject context. */
    public function ensureDefaults(Student $student): NotesSection
    {
        $notebook = NotesNotebook::firstOrCreate(
            ['student_id' => $student->id, 'subject_id' => null, 'is_archived' => false],
            ['school_id' => $student->school_id, 'title' => 'My Notes', 'emoji' => '📓'],
        );

        return NotesSection::firstOrCreate(
            ['notebook_id' => $notebook->id, 'topic_id' => null, 'is_archived' => false],
            ['student_id' => $student->id, 'school_id' => $student->school_id, 'title' => 'General'],
        );
    }

    /**
     * The subject-aware home: Notebook = subject, Section = chapter/topic.
     * Created on demand (imports, "new note in suggested place"), idempotent.
     */
    public function ensureSubjectHome(Student $student, int $subjectId, ?int $topicId = null): NotesSection
    {
        $notebook = NotesNotebook::firstOrCreate(
            ['student_id' => $student->id, 'subject_id' => $subjectId],
            [
                'school_id' => $student->school_id,
                'title'     => Subject::find($subjectId)?->name ?? 'Subject',
                'emoji'     => '📘',
            ],
        );

        if ($topicId !== null) {
            return NotesSection::firstOrCreate(
                ['notebook_id' => $notebook->id, 'topic_id' => $topicId],
                [
                    'student_id' => $student->id,
                    'school_id'  => $student->school_id,
                    'title'      => Topic::find($topicId)?->title ?? 'Topic',
                ],
            );
        }

        return NotesSection::firstOrCreate(
            ['notebook_id' => $notebook->id, 'topic_id' => null, 'is_archived' => false],
            ['student_id' => $student->id, 'school_id' => $student->school_id, 'title' => 'General'],
        );
    }

    /**
     * Syllabus topics per subject the student has a notebook for - powers the
     * "start a note in which topic?" chooser. subject_id => [{id, title}].
     */
    public function topicsBySubject(Student $student): array
    {
        $subjectIds = NotesNotebook::where('student_id', $student->id)
            ->whereNotNull('subject_id')->pluck('subject_id');

        return Topic::whereIn('subject_id', $subjectIds)
            ->orderBy('title')
            ->get(['id', 'title', 'subject_id'])
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])->values()->all())
            ->toArray();
    }

    /** One notebook per actively-enrolled subject (Physics, Chemistry, ...), idempotent. */
    private function provisionSubjectNotebooks(Student $student): void
    {
        $subjects = DB::table('v2_student_enrollments as e')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->join('v2_subjects as s', 's.id', '=', 'c.subject_id')
            ->where('e.student_id', $student->id)
            ->where('e.status', 'active')
            ->distinct()
            ->get(['s.id', 's.name']);

        foreach ($subjects as $subject) {
            NotesNotebook::firstOrCreate(
                ['student_id' => $student->id, 'subject_id' => $subject->id],
                ['school_id' => $student->school_id, 'title' => $subject->name, 'emoji' => '📘'],
            );
        }
    }
}
