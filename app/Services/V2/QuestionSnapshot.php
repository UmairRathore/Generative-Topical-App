<?php

namespace App\Services\V2;

use App\Models\V2\Question;
use App\Models\V2\QuestionImage;
use App\Models\V2\QuestionOption;
use Illuminate\Support\Arr;

/*
|--------------------------------------------------------------------------
| QuestionSnapshot
|--------------------------------------------------------------------------
| Capture a self-contained snapshot of a question's CONTENT (the v2_questions
| content fields + its options + its images metadata) and hydrate a NON-persisted
| Question back from one so the existing render partials show the exact frozen
| state. Raw attributes are used so json/boolean/decimal casts round-trip
| correctly (capture from a freshly-loaded model). Image FILES are never deleted,
| so the paths in a snapshot always resolve.
*/
class QuestionSnapshot
{
    /** Content fields (no status / timestamps / version pointer). */
    private const QUESTION_FIELDS = [
        'paper_id', 'subject_id', 'topic_id', 'subtopic_id', 'year', 'question_number',
        'question_text', 'text_before', 'text_after', 'layout_type', 'correct_answer',
        'option_table', 'marks', 'difficulty', 'page_start', 'page_end', 'source_paper',
    ];

    private const OPTION_FIELDS = ['label', 'text', 'has_image', 'sort_order'];

    private const IMAGE_FIELDS = [
        'external_id', 'image_path', 'role', 'option_label', 'page', 'bbox',
        'width', 'height', 'caption', 'ocr_text', 'confidence', 'diagram_labels', 'sort_order',
    ];

    /** Build a self-contained snapshot array from a (freshly loaded) question. */
    public static function capture(Question $question): array
    {
        $question->loadMissing(['options', 'images']);

        return [
            'question' => Arr::only($question->getAttributes(), self::QUESTION_FIELDS),
            'options'  => $question->options->map(fn ($o) => Arr::only($o->getAttributes(), self::OPTION_FIELDS))->values()->all(),
            'images'   => $question->images->map(fn ($i) => Arr::only($i->getAttributes(), self::IMAGE_FIELDS))->values()->all(),
        ];
    }

    /**
     * Hydrate a NON-persisted Question (with options/images relations set) from a
     * snapshot, keeping the original id, so the render partials reproduce the exact
     * frozen appearance. Not saved - purely for rendering.
     */
    public static function hydrate(array $snapshot, int $questionId): Question
    {
        $question = (new Question)->setRawAttributes(($snapshot['question'] ?? []) + ['id' => $questionId]);
        $question->exists = true;

        $question->setRelation('options', collect($snapshot['options'] ?? [])
            ->map(fn ($o) => (new QuestionOption)->setRawAttributes($o + ['question_id' => $questionId]))
            ->values());

        $question->setRelation('images', collect($snapshot['images'] ?? [])
            ->map(fn ($im) => (new QuestionImage)->setRawAttributes($im + ['question_id' => $questionId]))
            ->values());

        return $question;
    }
}
