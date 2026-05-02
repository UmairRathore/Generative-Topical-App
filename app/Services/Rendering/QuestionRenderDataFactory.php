<?php

namespace App\Services\Rendering;

use App\Models\Question;
use Illuminate\Support\Facades\Storage;

class QuestionRenderDataFactory
{
    public function __construct(private readonly QuestionTextCleaner $cleaner)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function make(Question $question): array
    {
        $question->loadMissing(['options.assets', 'assets', 'optionTable']);

        $stem = $question->clean_question_text
            ?? $this->cleaner->clean($question->question_text);

        // Extractor stores split text in these fields (not image paths).
        $textBefore = $this->trimOrNull($question->image_between_question_before_text);
        $textAfter = $this->trimOrNull($question->image_between_question_after_text);
        $hasSplit = $textBefore !== null || $textAfter !== null;

        $assets = $question->assets;
        $byRole = function (string $role) use ($assets) {
            return $assets
                ->where('role', $role)
                ->sortBy('sort_order')
                ->map(fn ($a) => [
                    'url' => Storage::disk($a->disk ?: 'public')->url($a->image_path),
                    'caption' => $a->caption,
                ])
                ->values()
                ->all();
        };

        $betweenImages = $byRole('question_image_between_text');
        $afterImages = $byRole('question_image_after_text');
        $diagrams = $byRole('question_diagram');
        $extras = $assets
            ->reject(fn ($a) => in_array($a->role, [
                'question_image_between_text',
                'question_image_after_text',
                'question_diagram',
                'option_image',
                'option_table',
            ], true))
            ->sortBy('sort_order')
            ->map(fn ($a) => [
                'url' => Storage::disk($a->disk ?: 'public')->url($a->image_path),
                'caption' => $a->caption,
            ])
            ->values()
            ->all();

        $options = $question->options
            ->sortBy('sort_order')
            ->map(function ($opt) {
                $images = $opt->assets
                    ->sortBy('sort_order')
                    ->map(fn ($a) => Storage::disk($a->disk ?: 'public')->url($a->image_path))
                    ->values()
                    ->all();

                return [
                    'label' => $opt->label,
                    'text' => $opt->option_text,
                    'images' => $images,
                ];
            })
            ->values()
            ->all();

        $optionTable = null;
        if ($question->optionTable) {
            $t = $question->optionTable;
            $optionTable = [
                'use_fallback_image' => (bool) $t->use_fallback_image,
                'image_url' => $t->image_path
                    ? Storage::disk($t->disk ?: 'public')->url($t->image_path)
                    : null,
                'headers' => is_array($t->headers) ? $t->headers : null,
                'rows' => is_array($t->rows) ? $t->rows : null,
            ];
        }

        return [
            'id' => $question->id,
            'number' => $question->question_number,
            'stem' => $stem,
            'has_split_text' => $hasSplit,
            'text_before' => $textBefore,
            'text_after' => $textAfter,
            'between_images' => $betweenImages,
            'after_images' => $afterImages,
            'diagrams' => $diagrams,
            'extra_images' => $extras,
            'options' => $options,
            'option_table' => $optionTable,
            'has_correct_answer' => $question->correct_answer !== null,
            'correct_answer' => $question->correct_answer,
        ];
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
