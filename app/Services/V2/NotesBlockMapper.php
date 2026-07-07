<?php

namespace App\Services\V2;

use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\StudentMistake;
use Illuminate\Support\Str;

/**
 * Turns platform content (question learning assets, Mistake Bank entries)
 * into BlockNote blocks for the student's Notes - the "add to notes" bridge.
 *
 * Reference strategy: text-ish assets are SNAPSHOTTED into the block (they
 * must survive a later asset edit/hide); widgets keep only {type, config}
 * and re-mount live via the widget registry; mistakes stay a LIVE reference
 * by hashid plus a thin display snapshot so the block reads even if the
 * mistake is later archived.
 */
class NotesBlockMapper
{
    /** @return array<int, array> BlockNote blocks for one learning asset */
    public function fromAsset(QuestionLearningAsset $asset): array
    {
        return match ($asset->asset_type) {
            'worked_solution',
            'revision_notes',
            'common_mistakes'    => [$this->assetSnapshotBlock($asset)],
            'flashcards'         => $this->cardBlocks($asset, 'flashcard'),
            'memcards'           => $this->cardBlocks($asset, 'memcard'),
            'mermaid'            => [$this->block('mermaid', ['source' => (string) $asset->content])],
            'interactive_widget' => [$this->widgetBlock($asset)],
            'option_explanation' => $this->optionExplanationBlocks($asset),
            default              => [],
        };
    }

    /** A mistake block (live ref + thin snapshot), followed by its per-option explanations when available. */
    public function fromMistake(StudentMistake $mistake, ?QuestionLearningAsset $optionExplanation = null): array
    {
        $blocks = [$this->block('mistake', [
            'mistakeId'    => (string) $mistake->getRouteKey(),
            'questionStem' => Str::limit((string) ($mistake->question?->question_text ?? ''), 500),
            'selected'     => (string) $mistake->selected_option,
            'correct'      => (string) $mistake->correct_option,
            'subject'      => (string) ($mistake->subject?->name ?? ''),
            'topic'        => (string) ($mistake->topic?->title ?? ''),
            'sourcePaper'  => (string) ($mistake->source_paper ?? ''),
            'studioUrl'    => route('v2.student.learning_hub.studio', $mistake),
        ])];

        // The question's own diagram(s), as render-time REFERENCE blocks (no URL
        // stored — minted fresh on view after an access re-check).
        $blocks = array_merge($blocks, $this->questionFigureBlocks($mistake->question));

        if ($optionExplanation) {
            $blocks = array_merge($blocks, $this->optionExplanationBlocks($optionExplanation));
        }

        return $blocks;
    }

    /**
     * The question's exam figure(s) as `question_figure` reference blocks. Stores
     * only question/version/image path + caption — never a signed URL, so nothing
     * expires or leaks. The note view mints a fresh viewer-bound URL per render.
     * Mirrors the Studio's figure filter (diagram role, not an option crop).
     */
    public function questionFigureBlocks(?Question $q): array
    {
        if (! $q) {
            return [];
        }

        return collect($q->images ?? [])
            ->filter(fn ($im) => $im->option_label === null && preg_match('/question|diagram|figure/i', (string) $im->role))
            ->sortBy('sort_order')
            ->map(fn ($im) => $this->block('question_figure', [
                'questionId'        => (int) $q->id,
                'questionVersionId' => (int) ($q->current_version_id ?? 0),
                'imagePath'         => (string) $im->image_path,
                'caption'           => (string) ($im->caption ?? ''),
            ]))
            ->values()->all();
    }

    /**
     * A widget saved from the live `camb:add-to-note` contract - the config is
     * the widget AS THE STUDENT LEFT IT, not the question's pristine default.
     */
    public function widgetStateBlock(string $type, array $config): array
    {
        return $this->block('widget', [
            'widgetType' => $type,
            'config'     => json_encode($config, JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);
    }

    /* ============================ Per-type ============================= */

    /** worked_solution / revision_notes / common_mistakes -> one snapshot block rendering the stored markdown. */
    private function assetSnapshotBlock(QuestionLearningAsset $asset): array
    {
        return $this->block('asset_snapshot', [
            'assetType'  => $asset->asset_type,
            'questionId' => (int) $asset->question_id,
            'title'      => (string) ($asset->title ?? ''),
            'markdown'   => (string) ($asset->content ?? ''),
        ]);
    }

    /** flashcards / memcards payload [{front, back}] -> one block per card. */
    private function cardBlocks(QuestionLearningAsset $asset, string $blockType): array
    {
        return collect($asset->payload_json ?? [])
            ->filter(fn ($c) => is_array($c) && ! empty($c['front']) && ! empty($c['back']))
            ->map(fn ($c) => $this->block($blockType, [
                'front' => (string) $c['front'],
                'back'  => (string) $c['back'],
            ]))
            ->values()->all();
    }

    /** interactive_widget payload {widget, config} -> a live widget block. */
    private function widgetBlock(QuestionLearningAsset $asset): array
    {
        $payload = $asset->payload_json ?? [];

        return $this->widgetStateBlock(
            (string) ($payload['widget'] ?? $asset->asset_key),
            is_array($payload['config'] ?? null) ? $payload['config'] : (is_array($payload) ? $payload : []),
        );
    }

    /** option_explanation payload -> heading + one callout per option (correct/wrong variants). */
    private function optionExplanationBlocks(QuestionLearningAsset $asset): array
    {
        $payload = $asset->payload_json ?? [];
        $columns = $payload['columns'] ?? [];
        $options = $payload['options'] ?? [];
        if (! is_array($options) || $options === []) {
            return [];
        }

        $blocks = [$this->block('heading', ['level' => 3], $this->runs('Why each answer is right — or wrong'))];

        foreach ($options as $o) {
            if (! is_array($o)) {
                continue;
            }
            $label   = (string) ($o['label'] ?? '?');
            $correct = (bool) ($o['correct'] ?? false);

            // Table-style options carry values[] against columns[]; plain ones carry text.
            $body = ! empty($o['values']) && is_array($o['values'])
                ? collect($o['values'])->map(fn ($v, $i) => trim(($columns[$i] ?? '').' '.$v))->implode(' · ')
                : (string) ($o['text'] ?? '');

            $why = $correct ? '✓ correct' : (string) ($o['why'] ?? '');

            $blocks[] = $this->block(
                'callout',
                ['variant' => $correct ? 'correct' : 'wrong'],
                array_merge(
                    $this->runs($label.'. ', ['bold' => true]),
                    $this->runs($body === '' ? '' : $body.' — '),
                    $this->runs($why, $correct ? ['bold' => true] : []),
                ),
            );
        }

        return $blocks;
    }

    /* ============================ Factories ============================ */

    private function block(string $type, array $props = [], array $content = [], array $children = []): array
    {
        return [
            'id'       => (string) Str::uuid(),
            'type'     => $type,
            'props'    => $props,
            'content'  => $content,
            'children' => $children,
        ];
    }

    /** @return array<int, array> BlockNote inline text runs */
    private function runs(string $text, array $styles = []): array
    {
        if ($text === '') {
            return [];
        }

        return [['type' => 'text', 'text' => $text, 'styles' => $styles === [] ? (object) [] : $styles]];
    }
}
