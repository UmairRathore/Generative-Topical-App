<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\NotesPage;
use App\Models\V2\NotesSection;
use App\Services\V2\NotesDocumentService;
use Illuminate\Http\Request;

/**
 * Page lifecycle: create, autosave (with optimistic concurrency), meta
 * (rename / pin / favorite / archive / tags), delete.
 */
class NotesPageController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    /**
     * Create a note. Target resolution: explicit section > subject/topic pair
     * (provisions the subject notebook + topic section on demand - the import
     * picker's "new note in suggested place") > the "My Notes" fallback.
     */
    public function store(Request $request, NotesDocumentService $service)
    {
        $student = $this->student();
        $data = $request->validate([
            'section_id' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'integer', 'exists:v2_subjects,id'],
            'topic_id'   => ['nullable', 'integer', 'exists:v2_topics,id'],
            'title'      => ['nullable', 'string', 'max:200'],
        ]);

        $section = null;
        if (! empty($data['section_id'])) {
            $section = (new NotesSection)->resolveRouteBinding($data['section_id']);
            abort_unless($section && $section->student_id === $student->id, 404);
        } elseif (! empty($data['subject_id'])) {
            $section = $service->ensureSubjectHome($student, (int) $data['subject_id'], $data['topic_id'] ?? null);
        }
        $section ??= $service->ensureDefaults($student);

        $page = NotesPage::create([
            'section_id'     => $section->id,
            'student_id'     => $student->id,
            'school_id'      => $student->school_id,
            // Curriculum tags follow the home it lands in.
            'subject_id'     => $section->notebook?->subject_id,
            'topic_id'       => $section->topic_id,
            'title'          => ($data['title'] ?? '') !== '' ? $data['title'] : 'Untitled',
            'last_edited_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id'        => $page->getRouteKey(),
                'title'     => $page->title,
                'url'       => route('v2.student.notes.pages.show', $page),
                'importUrl' => route('v2.student.notes.pages.import', $page),
            ]);
        }

        return redirect()->route('v2.student.notes.pages.show', $page);
    }

    /**
     * Autosave. Optimistic concurrency: a stale content_version gets a 409
     * carrying the server's copy - never a silent clobber of newer content.
     */
    public function update(NotesPage $page, Request $request, NotesDocumentService $service)
    {
        $this->authorizePage($page);

        $data = $request->validate([
            'title'           => ['nullable', 'string', 'max:200'],
            'document'        => ['present', 'array'],
            'content_version' => ['required', 'integer'],
        ]);

        if ((int) $data['content_version'] !== $page->content_version) {
            return response()->json([
                'error'          => 'stale',
                'contentVersion' => $page->content_version,
                'title'          => $page->title,
                'document'       => $page->document_json,
            ], 409);
        }

        $service->apply($page, $data['document'], $data['title'] ?? null);

        return response()->json([
            'ok'             => true,
            'contentVersion' => $page->content_version,
            'savedAt'        => $page->last_edited_at->toIso8601String(),
        ]);
    }

    /** Page metadata: rename, icon, pin/favorite/archive, curriculum + free tags. */
    public function meta(NotesPage $page, Request $request)
    {
        $this->authorizePage($page);

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:200'],
            'icon'        => ['sometimes', 'nullable', 'string', 'max:16'],
            'pinned'      => ['sometimes', 'boolean'],
            'favorite'    => ['sometimes', 'boolean'],
            'archived'    => ['sometimes', 'boolean'],
            'subject_id'  => ['sometimes', 'nullable', 'integer', 'exists:v2_subjects,id'],
            'topic_id'    => ['sometimes', 'nullable', 'integer', 'exists:v2_topics,id'],
            'subtopic_id' => ['sometimes', 'nullable', 'integer', 'exists:v2_subtopics,id'],
            'tags'        => ['sometimes', 'array', 'max:20'],
            'tags.*'      => ['string', 'max:48'],
        ]);

        $page->fill(collect($data)->only(['title', 'icon', 'subject_id', 'topic_id', 'subtopic_id'])->all());
        foreach (['pinned' => 'is_pinned', 'favorite' => 'is_favorite', 'archived' => 'is_archived'] as $in => $col) {
            if (array_key_exists($in, $data)) {
                $page->{$col} = (bool) $data[$in];
            }
        }
        $page->save();

        if (array_key_exists('tags', $data)) {
            $tags = collect($data['tags'])
                ->map(fn ($t) => mb_substr(trim((string) $t), 0, 48))
                ->filter()->unique()->values();
            $page->tags()->delete();
            $page->tags()->createMany($tags->map(fn ($t) => ['student_id' => $page->student_id, 'tag' => $t])->all());
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(NotesPage $page)
    {
        $this->authorizePage($page);
        $page->delete();

        return redirect()->route('v2.student.notes.index');
    }

    /**
     * The note's book-style sections, split on page_break blocks. Powers the
     * import picker's "insert where?" step: each section carries the id of the
     * NEXT page break (insert-before target); null = end of the note.
     */
    public function outline(NotesPage $page)
    {
        $this->authorizePage($page);

        $sections = [];
        $n = 1;
        $pending = ['label' => 'Page 1', 'insertBeforeId' => null];
        foreach ($page->document_json ?? [] as $block) {
            if (($block['type'] ?? '') !== 'page_break') {
                continue;
            }
            $pending['insertBeforeId'] = $block['id'] ?? null;
            $sections[] = $pending;
            $n++;
            $label = trim((string) ($block['props']['label'] ?? ''));
            $pending = ['label' => 'Page '.$n.($label !== '' ? ' — '.$label : ''), 'insertBeforeId' => null];
        }
        $sections[] = $pending;

        return response()->json([
            'title'    => $page->title,
            'sections' => $sections,
        ]);
    }

    private function authorizePage(NotesPage $page): void
    {
        abort_unless($page->student_id === $this->student()->id, 403);
    }
}
