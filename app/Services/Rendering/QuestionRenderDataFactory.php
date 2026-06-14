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
     * Build a public asset URL.
     *
     * Returns root-relative URLs (e.g. `/storage/cambpast-assets/foo.png`) so the
     * browser uses whatever host:port loaded the page. This avoids `Storage::url()`
     * which prefixes APP_URL and would lock URLs to the host configured in .env
     * (so a page served on :8001 wouldn't fetch images from :8000).
     */
    private function publicAssetUrl(?string $imagePath, ?string $disk): ?string
    {
        if (! $imagePath) {
            return null;
        }

        // For non-public disks (S3, etc.) keep the full URL — they need the host.
        $disk = $disk ?: 'public';
        if ($disk !== 'public') {
            return Storage::disk($disk)->url($imagePath);
        }

        return '/storage/'.ltrim($imagePath, '/');
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
                    'url' => $this->publicAssetUrl($a->image_path, $a->disk),
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
                'url' => $this->publicAssetUrl($a->image_path, $a->disk),
                'caption' => $a->caption,
            ])
            ->values()
            ->all();

        $options = $question->options
            ->sortBy('sort_order')
            ->map(function ($opt) {
                $images = $opt->assets
                    ->sortBy('sort_order')
                    ->map(fn ($a) => $this->publicAssetUrl($a->image_path, $a->disk))
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
                'image_url' => $this->publicAssetUrl($t->image_path, $t->disk),
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
