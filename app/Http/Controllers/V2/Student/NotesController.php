<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\NotesPage;
use App\Services\V2\NotesDocumentService;
use Inertia\Inertia;

/**
 * Student Notes - Notion-style block pages (Learning Hub). Thin controller;
 * document plumbing lives in NotesDocumentService. Students only ever see
 * their own notes (school is scoped globally, ownership asserted here).
 */
class NotesController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    /** The Notes shell with no page open (sidebar + empty state). */
    public function index(NotesDocumentService $service)
    {
        $student = $this->student();

        return Inertia::render('Notes', [
            'tree'   => $service->tree($student),
            'topics' => $service->topicsBySubject($student),
            'page'   => null,
            'urls'   => $this->urls(),
        ]);
    }

    /** The Notes shell with one page open in the editor. */
    public function show(NotesPage $page, NotesDocumentService $service)
    {
        $this->authorizePage($page);
        $page->load('tags');

        $student = $this->student();

        return Inertia::render('Notes', [
            'tree'   => $service->tree($student),
            'topics' => $service->topicsBySubject($student),
            'page' => [
                'id'             => $page->getRouteKey(),
                'title'          => $page->title,
                'icon'           => $page->icon,
                'document'       => $page->document_json,
                'contentVersion' => $page->content_version,
                'pinned'         => $page->is_pinned,
                'favorite'       => $page->is_favorite,
                'archived'       => $page->is_archived,
                'subjectId'      => $page->subject_id,
                'topicId'        => $page->topic_id,
                'subtopicId'     => $page->subtopic_id,
                'tags'           => $page->tags->pluck('tag')->all(),
                'editedAt'       => $page->last_edited_at?->toIso8601String(),
                // Fresh signed URLs for question_figure blocks, minted per view
                // after re-checking access. Never persisted into the document.
                'figureUrls'     => $service->figureUrls($page->document_json ?? [], $student),
                'urls'           => [
                    'update'   => route('v2.student.notes.pages.update', $page),
                    'meta'     => route('v2.student.notes.pages.meta', $page),
                    'destroy'  => route('v2.student.notes.pages.destroy', $page),
                    'import'   => route('v2.student.notes.pages.import', $page),
                    'versions' => route('v2.student.notes.pages.versions', $page),
                    'upload'   => route('v2.student.notes.uploads.store'),
                ],
            ],
            'urls' => $this->urls(),
        ]);
    }

    private function urls(): array
    {
        return [
            'index'      => route('v2.student.notes.index'),
            'createPage' => route('v2.student.notes.pages.store'),
            'tree'       => route('v2.student.notes.tree'),
            'search'     => route('v2.student.notes.search'),
            // Exit the immersive Studio back to the main (Livewire) app.
            'dashboard'  => route('v2.student.dashboard'),
            'learningHub' => route('v2.student.learning_hub.index'),
        ];
    }

    /** A student may only ever open their own page (school is already scoped globally). */
    private function authorizePage(NotesPage $page): void
    {
        abort_unless($page->student_id === $this->student()->id, 403);
    }
}
