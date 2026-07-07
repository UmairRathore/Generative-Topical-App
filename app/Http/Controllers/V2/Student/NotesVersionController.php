<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\NotesPage;
use App\Models\V2\NotesPageVersion;
use App\Services\V2\NotesDocumentService;

/**
 * Page history: the last N autosave snapshots, with restore. Restoring
 * snapshots the current state first, so it is always reversible.
 */
class NotesVersionController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function index(NotesPage $page)
    {
        $this->authorizePage($page);

        return response()->json([
            'versions' => $page->versions()->get()->map(fn (NotesPageVersion $v) => [
                'id'         => $v->getRouteKey(),
                'title'      => $v->title,
                'ago'        => $v->created_at?->diffForHumans(),
                'createdAt'  => $v->created_at?->toIso8601String(),
                'blocks'     => is_array($v->document_json) ? count($v->document_json) : 0,
                'restoreUrl' => route('v2.student.notes.pages.versions.restore', [$page, $v]),
            ])->values(),
        ]);
    }

    public function restore(NotesPage $page, NotesPageVersion $version, NotesDocumentService $service)
    {
        $this->authorizePage($page);
        abort_unless($version->page_id === $page->id, 404);

        $service->restore($page, $version);

        return response()->json([
            'ok'             => true,
            'title'          => $page->title,
            'document'       => $page->document_json,
            'contentVersion' => $page->content_version,
        ]);
    }

    private function authorizePage(NotesPage $page): void
    {
        abort_unless($page->student_id === $this->student()->id, 403);
    }
}
