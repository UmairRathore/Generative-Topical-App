<?php

namespace App\Services\V2;

use App\Models\V2\NotesImport;
use App\Models\V2\NotesPage;

/**
 * Read-only provenance for the Save-to-Notes buttons: for a student + an
 * EXACT source (source + source_id), how many times it's been saved and into
 * which notes. Never infers "saved" from question_id alone - only the exact
 * source object counts, so one asset being saved never marks a sibling asset
 * or an AI answer from the same question as saved.
 */
class NotesProvenanceService
{
    /**
     * @return array{count:int, pages:array<int, array{id:string, title:string, url:string}>}
     */
    public function forSource(int $studentId, string $source, int $sourceId): array
    {
        return $this->forSources($studentId, ['_' => ['source' => $source, 'source_id' => $sourceId]])['_'];
    }

    /**
     * Batched provenance for several sources at once (one query).
     *
     * @param  array<string, array{source:string, source_id:int}>  $sources  keyed however the caller wants back
     * @return array<string, array{count:int, pages:array<int, array{id:string, title:string, url:string}>}>
     */
    public function forSources(int $studentId, array $sources): array
    {
        // Empty result for every requested key up front.
        $out = [];
        foreach ($sources as $key => $s) {
            $out[$key] = ['count' => 0, 'pages' => []];
        }
        if ($sources === []) {
            return $out;
        }

        $rows = NotesImport::query()
            ->where('student_id', $studentId)
            ->where(function ($q) use ($sources) {
                foreach ($sources as $s) {
                    $q->orWhere(fn ($w) => $w->where('source', $s['source'])->where('source_id', (int) $s['source_id']));
                }
            })
            ->with('page:id,title')
            ->orderByDesc('id')
            ->get(['id', 'page_id', 'source', 'source_id']);

        // Index rows by "source#id" for O(1) assembly.
        $byIdentity = [];
        foreach ($rows as $row) {
            $byIdentity[$row->source.'#'.$row->source_id][] = $row;
        }

        foreach ($sources as $key => $s) {
            $matches = $byIdentity[$s['source'].'#'.(int) $s['source_id']] ?? [];
            $pages = [];
            $seen = [];
            foreach ($matches as $row) {
                if (! $row->page || isset($seen[$row->page_id])) {
                    continue; // distinct pages; a source saved 5x to one note is still one page entry
                }
                $seen[$row->page_id] = true;
                $pages[] = [
                    'id'    => (string) $row->page->getRouteKey(),
                    'title' => (string) $row->page->title,
                    'url'   => route('v2.student.notes.pages.show', $row->page),
                ];
            }
            $out[$key] = ['count' => count($matches), 'pages' => $pages];
        }

        return $out;
    }
}
