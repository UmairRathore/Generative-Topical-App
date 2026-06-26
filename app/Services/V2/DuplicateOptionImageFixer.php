<?php

namespace App\Services\V2;

use App\Models\V2\Question;
use App\Models\V2\QuestionImage;
use App\Models\V2\QuestionOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repairs questions where the extraction pipeline stored the SAME picture under
 * more than one image row, so the diagram renders two (or more) times.
 *
 * Two related faults, both detected by comparing image file content (MD5):
 *
 *  1. Duplicate figures - the question diagram saved twice (e.g. as both
 *     `question_image_between_text` and `question_image_after_text`), or the
 *     diagram also copied into the `table` slot, or copied once per A/B/C/D
 *     `option_image`. The figure then appears in the stem AND again under
 *     "OPTIONS".
 *
 *  2. Mis-tagged "label the diagram" questions - "at which point…", "which
 *     arrow…", "which line…" - whose answer choices are labels drawn ON one
 *     figure, but were extracted as four separate option pictures (identical
 *     crops of that one figure).
 *
 * Repair, per question:
 *   - Collapse byte-identical images to a single row, keeping the most useful
 *     role (figure > table > option image).
 *   - Then normalise option images: a genuine "which graph/diagram" set has one
 *     DISTINCT picture per choice. Anything else is not real per-option art -
 *     either the choices are textual (drop the spurious crops, keep the text) or
 *     they are labels on the figure (promote the distinct figure(s), drop the
 *     crops, and let A/B/C/D render as plain choices).
 *
 * Idempotent and safe to re-run. Used by the v2:fix-duplicate-option-images
 * command and invoked per-question by v2:import-questions --copy-images.
 */
class DuplicateOptionImageFixer
{
    /** A label-style question's option crops collapse to at most this many
     *  distinct figures (one diagram, or two like "diagram 1 / diagram 2"). */
    private const MAX_DISTINCT = 2;

    private const FIGURE_ROLES = ['question_image_between_text', 'question_image_after_text'];

    private string $disk;

    public function __construct()
    {
        $this->disk = storage_path('app/public/');
    }

    /* ===================================================================== */
    /*  Repair                                                                */
    /* ===================================================================== */

    /**
     * Repair one question. Returns a summary, or null if nothing changed.
     *
     * @return array{qid:int,deleted:int,promoted:int,cleared_text:bool,layout:bool}|null
     */
    public function fixQuestion(Question $question): ?array
    {
        $question->loadMissing('images', 'options');

        $hasOptionImages = $question->images->where('role', 'option_image')->isNotEmpty();
        if ($question->images->count() < 2 && ! $hasOptionImages) {
            return null;
        }

        $deleted = 0;
        $promoted = 0;
        $clearedText = false;
        $changedLayout = false;

        DB::transaction(function () use ($question, &$deleted, &$promoted, &$clearedText, &$changedLayout) {

            // --- 1. Collapse byte-identical images, keeping the most useful role ---
            foreach ($question->images()->get()->groupBy(fn ($im) => $this->hashOf($im)) as $hash => $group) {
                if (str_starts_with($hash, 'MISSING:') || $group->count() < 2) {
                    continue;
                }
                $keeper = $group->sortByDesc(fn ($im) => $this->rolePriority($im->role))->first();
                foreach ($group as $im) {
                    if ($im->id !== $keeper->id) {
                        $im->delete();
                        $deleted++;
                    }
                }
            }

            // --- 2. Normalise option images ---
            $question->load('images', 'options');
            $optionImages = $question->images->where('role', 'option_image')->values();

            if ($optionImages->isNotEmpty()) {
                $figureHashes  = $this->figureHashes($question);
                $distinctHashes = $optionImages->map(fn ($im) => $this->hashOf($im))->unique()->values();
                $nOptions = max($question->options->count(), 1);

                $genuine = $distinctHashes->count() === $nOptions
                    && $distinctHashes->intersect($figureHashes)->isEmpty();

                if (! $genuine) {
                    $hasText = $question->options->contains(fn ($o) => trim((string) $o->text) !== '');

                    if ($hasText) {
                        // Choices are textual; the option images are spurious. Drop them.
                        foreach ($optionImages as $im) {
                            $im->delete();
                            $deleted++;
                        }
                    } elseif ($distinctHashes->count() <= self::MAX_DISTINCT) {
                        // Labels on the figure(s): promote distinct figure(s), drop the rest.
                        $order = (int) ($question->images->whereIn('role', self::FIGURE_ROLES)->max('sort_order') ?? -1);
                        $promotedHashes = [];
                        foreach ($optionImages as $im) {
                            $hash = $this->hashOf($im);
                            if (in_array($hash, $figureHashes, true) || in_array($hash, $promotedHashes, true)) {
                                $im->delete();
                                $deleted++;
                            } else {
                                $im->update([
                                    'role'         => 'question_image_after_text',
                                    'option_label' => null,
                                    'sort_order'   => ++$order,
                                ]);
                                $promotedHashes[] = $hash;
                                $promoted++;
                            }
                        }
                        QuestionOption::where('question_id', $question->id)->update(['text' => '']);
                        $clearedText = true;
                    }
                    // else: spurious but >MAX_DISTINCT distinct and no text - too ambiguous to
                    // auto-repair; left untouched (surfaced by the dry-run report).
                }
            }

            // --- 3. Reclassify a question that no longer carries option pictures ---
            $question->load('images');
            $stillHasOptionImages = $question->images->where('role', 'option_image')->isNotEmpty();
            $hasTable = $question->images->firstWhere('role', 'table') !== null;

            if (! $stillHasOptionImages && ! $hasTable
                && in_array($question->layout_type, ['option_images', 'question_diagram_and_option_images'], true)) {
                $question->update(['layout_type' => 'question_diagram']);
                $changedLayout = true;
            }
        });

        if (! $deleted && ! $promoted && ! $clearedText && ! $changedLayout) {
            return null;
        }

        return ['qid' => $question->id, 'deleted' => $deleted, 'promoted' => $promoted,
                'cleared_text' => $clearedText, 'layout' => $changedLayout];
    }

    /**
     * Repair every question that carries duplicate images or option crops.
     *
     * @return array<int,array{qid:int,deleted:int,promoted:int,cleared_text:bool,layout:bool}>
     */
    public function fixAll(): array
    {
        $fixed = [];

        Question::with('images', 'options')
            ->where(fn ($q) => $q->has('images', '>=', 2)
                ->orWhereHas('images', fn ($i) => $i->where('role', 'option_image')))
            ->chunkById(200, function ($questions) use (&$fixed) {
                foreach ($questions as $question) {
                    if ($result = $this->fixQuestion($question)) {
                        $fixed[] = $result;
                    }
                }
            });

        return $fixed;
    }

    /* ===================================================================== */
    /*  Render-time guard                                                     */
    /* ===================================================================== */

    /**
     * Figure(s) a view should show ONCE in place of duplicate option crops, when
     * a mis-tagged question slips past the importer's dedup. Returns only the
     * distinct option figure(s) NOT already shown as a question figure (so it
     * never double-renders the stem diagram), or null for genuine per-option
     * pictures / nothing to add.
     *
     * @return Collection<int,QuestionImage>|null
     */
    public function optionFiguresToShow(Question $question): ?Collection
    {
        $optionImages = $question->images->where('role', 'option_image')
            ->sortBy(fn ($im) => $im->option_label ?? $im->sort_order)
            ->values();

        if ($optionImages->isEmpty()) {
            return null;
        }

        // Cheap short-circuit: genuine per-option pictures differ in size.
        $sizes = $optionImages->map(fn ($im) => @filesize($this->disk.$im->image_path) ?: 0);
        if ($optionImages->count() > self::MAX_DISTINCT && $sizes->unique()->count() === $optionImages->count()) {
            return null;
        }

        $figureHashes = $this->figureHashes($question);
        $seen = [];
        $toShow = collect();
        foreach ($optionImages as $im) {
            $hash = $this->hashOf($im);
            if (in_array($hash, $seen, true)) {
                continue;
            }
            $seen[] = $hash;
            if (! in_array($hash, $figureHashes, true)) {
                $toShow->push($im);
            }
        }

        // Only a small, label-style collapse should be shown this way.
        return (count($seen) <= self::MAX_DISTINCT && $toShow->isNotEmpty()) ? $toShow : null;
    }

    /* ===================================================================== */
    /*  Helpers                                                               */
    /* ===================================================================== */

    /** @return array<int,string> content hashes of the question's figure images */
    private function figureHashes(Question $question): array
    {
        return $question->images
            ->whereIn('role', self::FIGURE_ROLES)
            ->map(fn ($im) => $this->hashOf($im))
            ->values()->all();
    }

    private function hashOf(QuestionImage $image): string
    {
        $file = $this->disk.$image->image_path;

        return is_file($file) ? md5_file($file) : 'MISSING:'.$image->image_path;
    }

    private function rolePriority(string $role): int
    {
        return match (true) {
            in_array($role, self::FIGURE_ROLES, true) => 3,
            $role === 'table'                         => 2,
            $role === 'option_image'                  => 1,
            default                                   => 0,
        };
    }
}
