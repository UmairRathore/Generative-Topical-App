<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\NotesPage;
use App\Services\V2\NotesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * JSON endpoints for the sidebar/page-picker tree and cross-page search.
 * Search runs on the denormalized page columns (plain_text LIKE +
 * block_types_json contains) - the document is never parsed at query time.
 */
class NotesTreeController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function index(NotesDocumentService $service)
    {
        return response()->json(['tree' => $service->tree($this->student())]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'q'          => ['nullable', 'string', 'max:120'],
            'block_type' => ['nullable', 'string', 'max:32'],
            'subject_id' => ['nullable', 'integer'],
            'tag'        => ['nullable', 'string', 'max:48'],
        ]);

        $pages = NotesPage::where('student_id', $this->student()->id)
            ->where('is_archived', false)
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('plain_text', 'like', "%{$term}%"),
            ))
            ->when($data['block_type'] ?? null, fn ($q, $t) => $q->whereJsonContains('block_types_json', $t))
            ->when($data['subject_id'] ?? null, fn ($q, $s) => $q->where('subject_id', $s))
            ->when($data['tag'] ?? null, fn ($q, $t) => $q->whereHas('tags', fn ($w) => $w->where('tag', $t)))
            ->orderByDesc('last_edited_at')
            ->limit(30)
            ->get(['id', 'title', 'icon', 'subject_id', 'last_edited_at', 'plain_text']);

        return response()->json([
            'results' => $pages->map(fn (NotesPage $p) => [
                'id'      => $p->getRouteKey(),
                'title'   => $p->title,
                'icon'    => $p->icon,
                'snippet' => Str::limit((string) $p->plain_text, 140),
                'ago'     => $p->last_edited_at?->diffForHumans(),
                'url'     => route('v2.student.notes.pages.show', $p),
            ])->values(),
        ]);
    }
}
