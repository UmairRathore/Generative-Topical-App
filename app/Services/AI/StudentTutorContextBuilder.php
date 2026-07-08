<?php

namespace App\Services\AI;

use App\Models\V2\Question;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\ExamService;
use App\Services\V2\MistakeBankService;
use Illuminate\Support\Str;

/**
 * Assembles the Student-Tutor context payload (docs/ai-context/ai/03, contract A)
 * from a mistake the CALLER has already authorized (ownership + release gate +
 * school scope). Hard rules enforced here:
 *  - first name only, no other student identity (PII rule 11)
 *  - diagram REFERENCES {question_id, image_path} - never signed URLs
 *  - grounding content only through MistakeBankService::visibleAsset()
 *  - correct_answer sourced from the question, falling back to the mistake row
 *
 * Returns ['payload' => contract-A array, 'manifest' => context-item rows].
 */
class StudentTutorContextBuilder
{
    /** Asset types offered to the tutor as grounding material. */
    private const GROUNDING_TYPES = [
        'worked_solution', 'option_explanation', 'flashcards', 'memcards',
        'mermaid', 'revision_notes', 'common_mistakes',
    ];

    /**
     * Token-budget caps (chars). The worked solution is the core grounding and
     * gets the most room; everything else is supporting material. Keeps a
     * typical chat request around ~3k input tokens on a mini model.
     */
    private const CONTENT_CAPS = [
        'worked_solution'    => 6000,
        'option_explanation' => 4000,
        'mermaid'            => 1500,
        'revision_notes'     => 2500,
        'common_mistakes'    => 2500,
    ];

    /** Card decks are recall aids, not core grounding - a few examples suffice. */
    private const MAX_CARDS = 6;

    public function __construct(
        private MistakeBankService $mistakes,
        private ExamService $exams,
    ) {
    }

    /** Cheap pre-check: a chat is only worth opening if we know the correct answer. */
    public function hasGrounding(StudentMistake $mistake): bool
    {
        if ($mistake->correct_option !== null) {
            return true;
        }

        return Question::withoutGlobalScopes()
            ->whereKey($mistake->question_id)
            ->whereNotNull('correct_answer')
            ->exists();
    }

    /**
     * @param bool $includeStats attach recent performance stats (heavier DB
     *             queries + more tokens) - only worth it on a chat's first turn.
     *
     * @return array{payload: array, manifest: array<int, array>}
     */
    public function build(StudentMistake $mistake, Student $student, string $sourceType, bool $includeStats = true): array
    {
        $mistake->loadMissing(['subject:id,name,code,level', 'topic:id,title', 'subtopic:id,title']);

        $q = Question::withoutGlobalScopes()->with(['options', 'images'])->find($mistake->question_id);
        $manifest = [];

        $payload = [
            'role'        => 'student',
            'source_type' => $sourceType,
            'student'     => ['first_name' => strtok((string) $student->name, ' ') ?: 'Student'],
            'grade_class' => array_filter(['grade' => $student->primaryGrade()]),
            'curriculum'  => array_filter([
                'subject'       => $mistake->subject?->name,
                // The Cambridge level is the difficulty anchor - an O Level
                // student must never receive A Level pitched content.
                'level'         => $mistake->subject?->level,
                'syllabus_code' => $mistake->subject?->code,
                'topic'         => $mistake->topic?->title,
                'subtopic'      => $mistake->subtopic?->title,
            ]),
        ];

        $correct = $q?->correct_answer ?: $mistake->correct_option;

        $payload['question'] = [
            'question_id'    => $mistake->question_id,
            'stem'           => $q?->question_text,
            'options'        => $q?->options
                ->map(fn ($o) => ['label' => $o->label, 'text' => (string) $o->text])
                ->values()->all() ?? [],
            'correct_answer' => $correct,
            'difficulty'     => $mistake->difficulty,
            'source_paper'   => $q?->source_paper ?? $mistake->source_paper,
            // References only - the model never receives image URLs or bytes.
            'diagram_references' => $q?->images
                ->filter(fn ($im) => $im->option_label === null && preg_match('/question|diagram|figure/i', (string) $im->role))
                ->sortBy('sort_order')
                ->map(fn ($im) => array_filter([
                    'question_id' => $mistake->question_id,
                    'image_path'  => $im->image_path,
                    'caption'     => $im->caption,
                ]))->values()->all() ?? [],
        ];

        $manifest[] = ['item_type' => 'question_stem', 'ref_table' => 'v2_questions', 'ref_id' => $mistake->question_id, 'meta' => null];
        $manifest[] = ['item_type' => 'correct_answer', 'ref_table' => 'v2_questions', 'ref_id' => $mistake->question_id, 'meta' => ['source' => $q?->correct_answer ? 'question' : 'mistake']];
        foreach ($payload['question']['diagram_references'] as $ref) {
            $manifest[] = ['item_type' => 'diagram_reference', 'ref_table' => 'v2_question_images', 'ref_id' => null, 'meta' => ['image_path' => $ref['image_path']]];
        }

        // "mistake" mode teaches against the student's own wrong answer;
        // "question" mode is about the question itself - no personal attempt data.
        if ($sourceType === 'mistake') {
            $payload['student_answer'] = [
                'selected_option' => $mistake->selected_option,
                'is_correct'      => false,
            ];
            $payload['behaviour'] = [
                'mistake_count'  => $mistake->mistake_count,
                'mastery_status' => $mistake->status,
                'review_count'   => $mistake->review_count,
                'first_wrong_at' => $mistake->first_wrong_at?->toDateString(),
                'last_wrong_at'  => $mistake->last_wrong_at?->toDateString(),
            ];
            $manifest[] = ['item_type' => 'behaviour', 'ref_table' => 'v2_student_mistakes', 'ref_id' => $mistake->id, 'meta' => null];
        }

        // Trusted, super-admin-approved grounding only (rule 4), capped to a
        // token budget - the tutor needs the substance, not every byte.
        $assets = [];
        foreach (self::GROUNDING_TYPES as $type) {
            if (! $a = $this->mistakes->visibleAsset($mistake->question_id, $type)) {
                continue;
            }
            $key = $type === 'option_explanation' ? 'option_explanations' : $type;
            $payloadJson = $a->payload_json;
            if (in_array($type, ['flashcards', 'memcards'], true) && is_array($payloadJson)) {
                $payloadJson = array_slice($payloadJson, 0, self::MAX_CARDS);
            }
            $assets[$key] = array_filter([
                'title'   => $a->title,
                'content' => $a->content !== null ? Str::limit($a->content, self::CONTENT_CAPS[$type] ?? 3000, '…') : null,
                'payload' => $payloadJson,
            ], fn ($v) => $v !== null && $v !== '');
            $manifest[] = ['item_type' => $type, 'ref_table' => 'v2_question_learning_assets', 'ref_id' => $a->id, 'meta' => ['status' => $a->status]];
        }
        if ($widget = $this->mistakes->visibleAsset($mistake->question_id, 'interactive_widget')) {
            // Type only: the JS config is meaningless to the model and can be
            // thousands of tokens. Knowing a simulator exists is enough.
            $wp = $widget->payload_json;
            $assets['interactive_widget'] = ['type' => $wp['widget'] ?? $widget->asset_key];
            $manifest[] = ['item_type' => 'interactive_widget', 'ref_table' => 'v2_question_learning_assets', 'ref_id' => $widget->id, 'meta' => ['status' => $widget->status]];
        }
        $payload['grounding_assets'] = $assets;

        // Small, subject-scoped performance signal - first turn only (keeps
        // later turns cheap and the system prompt stable for provider-side
        // prompt caching).
        if ($includeStats) {
            $recent = $this->recentContext($mistake, $student);
            if ($recent !== []) {
                $payload['recent_context'] = $recent;
                $manifest[] = ['item_type' => 'recent_stats', 'ref_table' => null, 'ref_id' => null, 'meta' => [
                    'weak_topics' => count($recent['weak_topics'] ?? []),
                    'attempts'    => count($recent['recent_attempts'] ?? []),
                ]];
            }
        }

        return ['payload' => $payload, 'manifest' => $manifest];
    }

    private function recentContext(StudentMistake $mistake, Student $student): array
    {
        $context = [];

        if ($trend = $this->exams->studentScoreTrend($student->id, 5)) {
            $context['recent_attempts'] = $trend;
        }

        if ($mistake->subject_id) {
            $stats = $this->exams->studentStats($student, null, null, [$mistake->subject_id]);
            $topics = collect($stats['subjects'][0]['topics'] ?? [])
                ->filter(fn ($t) => ($t['total'] ?? 0) >= 2)
                ->sortBy('percent')
                ->take(5)
                ->map(fn ($t) => ['topic' => $t['topic'], 'percent' => $t['percent'], 'total' => $t['total']])
                ->values()->all();
            if ($topics) {
                $context['weak_topics'] = $topics;
            }
        }

        return $context;
    }
}
