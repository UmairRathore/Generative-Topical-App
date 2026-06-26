<?php

namespace App\Services\Questions;

use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionDifficulty;
use App\Models\QuestionTopicTag;
use App\Models\SyllabusLearningObjective;
use App\Models\SyllabusSubtopic;
use App\Models\SyllabusTopic;
use App\Services\Syllabus\SyllabusLoader;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates topic-tag and difficulty enrichment for questions.
 *
 * Sources:
 *   - DB (default if --from-db or no flag) - questions already imported.
 *   - JSON (--from-json) - read directly from storage/output/papers/{stem}/questions.json.
 *
 * Output:
 *   - Persists question_topic_tags + question_difficulties (idempotent: tags
 *     are reset for a question before re-inserting, difficulty uses updateOrCreate).
 *   - Optionally writes storage/output/papers/{stem}/questions_enriched.json.
 */
class QuestionEnrichmentService
{
    public function __construct(
        private readonly SyllabusLoader $syllabusLoader,
        private readonly QuestionTopicClassifier $classifier,
        private readonly QuestionDifficultyEvaluator $difficulty,
    ) {}

    /**
     * Boot the classifier with a syllabus tree from JSON path.
     */
    public function loadSyllabus(string $path): array
    {
        $data = $this->syllabusLoader->readJson($path);
        $this->classifier->loadSyllabus($data);
        return $data;
    }

    /**
     * Process a list of paper stems (e.g. "9702_m17_qp_12") OR a special "*" wildcard.
     *
     * @param array{
     *     papers: array<int,string>,
     *     all: bool,
     *     source: 'db'|'json',
     *     dry_run: bool,
     *     force: bool,
     *     limit: ?int,
     *     export_json: bool,
     *     output_root: string,
     * } $opts
     */
    public function processPapers(array $opts, ?callable $onPaper = null, ?callable $onQuestion = null): array
    {
        $stems = $this->resolvePapers($opts);
        $totals = [
            'papers' => 0,
            'questions' => 0,
            'tags_created' => 0,
            'tags_updated' => 0,
            'tags_low_confidence' => 0,
            'difficulty_created' => 0,
            'difficulty_updated' => 0,
            'exports' => [],
            'skipped_existing' => 0,
        ];

        foreach ($stems as $stem) {
            $totals['papers']++;
            $paperResult = $this->processPaper($stem, $opts, $onQuestion);
            $totals['questions'] += $paperResult['questions'];
            $totals['tags_created'] += $paperResult['tags_created'];
            $totals['tags_updated'] += $paperResult['tags_updated'];
            $totals['tags_low_confidence'] += $paperResult['tags_low_confidence'];
            $totals['difficulty_created'] += $paperResult['difficulty_created'];
            $totals['difficulty_updated'] += $paperResult['difficulty_updated'];
            $totals['skipped_existing'] += $paperResult['skipped_existing'];
            if ($paperResult['export_path'] !== null) {
                $totals['exports'][] = $paperResult['export_path'];
            }
            if ($onPaper) {
                $onPaper($stem, $paperResult);
            }
        }
        return $totals;
    }

    /**
     * @param array<string,mixed> $opts
     */
    public function processPaper(string $stem, array $opts, ?callable $onQuestion = null): array
    {
        $questions = $opts['source'] === 'json'
            ? $this->loadQuestionsFromJson($stem, $opts['output_root'])
            : $this->loadQuestionsFromDb($stem);

        if ($opts['limit']) {
            $questions = array_slice($questions, 0, $opts['limit']);
        }

        $result = [
            'questions' => 0,
            'tags_created' => 0,
            'tags_updated' => 0,
            'tags_low_confidence' => 0,
            'difficulty_created' => 0,
            'difficulty_updated' => 0,
            'export_path' => null,
            'skipped_existing' => 0,
            'enriched' => [],
        ];

        $paperModel = Paper::query()->where('source_file', "{$stem}.pdf")
            ->orWhere(function ($q) use ($stem) {
                $q->whereRaw('LOWER(source_file) = ?', [strtolower("{$stem}.pdf")]);
            })
            ->first();

        foreach ($questions as $q) {
            $tags = $this->classifier->classify($q['payload']);
            $difficulty = $this->difficulty->evaluate($q['payload'], $tags);

            $hasLowConfidence = false;
            foreach ($tags as $tag) {
                if (! empty($tag['needs_review']) || ($tag['relevance'] ?? '') === 'low') {
                    $hasLowConfidence = true;
                    break;
                }
            }
            if ($hasLowConfidence) {
                $result['tags_low_confidence']++;
            }

            // DB persistence (skipped in dry-run)
            if (! $opts['dry_run'] && $q['question_id'] !== null) {
                if (! $opts['force'] && $this->alreadyEnriched($q['question_id'])) {
                    $result['skipped_existing']++;
                } else {
                    $persisted = $this->persistEnrichment($q['question_id'], $tags, $difficulty);
                    $result['tags_created'] += $persisted['tags_created'];
                    $result['tags_updated'] += $persisted['tags_updated'];
                    $result['difficulty_created'] += $persisted['difficulty_created'];
                    $result['difficulty_updated'] += $persisted['difficulty_updated'];
                }
            }

            $enriched = [
                'question_number' => $q['payload']['question_number'] ?? null,
                'source_paper' => $stem,
                'topic_tags' => $tags,
                'difficulty' => $difficulty,
            ];
            $result['enriched'][] = $enriched;
            $result['questions']++;

            if ($onQuestion) {
                $onQuestion($enriched);
            }
        }

        if ($opts['export_json'] && ! empty($result['enriched'])) {
            $result['export_path'] = $this->writeExport($stem, $opts['output_root'], $result['enriched']);
        }

        return $result;
    }

    private function alreadyEnriched(int $questionId): bool
    {
        return QuestionTopicTag::query()->where('question_id', $questionId)->exists()
            || QuestionDifficulty::query()->where('question_id', $questionId)->exists();
    }

    /**
     * @return array{tags_created:int,tags_updated:int,difficulty_created:int,difficulty_updated:int}
     */
    private function persistEnrichment(int $questionId, array $tags, array $difficulty): array
    {
        $stats = ['tags_created' => 0, 'tags_updated' => 0, 'difficulty_created' => 0, 'difficulty_updated' => 0];

        DB::transaction(function () use ($questionId, $tags, $difficulty, &$stats) {
            $existingTagCount = QuestionTopicTag::query()->where('question_id', $questionId)->count();
            QuestionTopicTag::query()->where('question_id', $questionId)->delete();

            foreach ($tags as $tag) {
                $topic = SyllabusTopic::query()
                    ->where('external_id', (string) $tag['topic_id'])
                    ->where('syllabus_code', SyllabusLoader::DEFAULT_CODE)
                    ->first();
                if (! $topic) {
                    continue;
                }
                $sub = null;
                if (! empty($tag['subtopic_id'])) {
                    $sub = SyllabusSubtopic::query()
                        ->where('syllabus_topic_id', $topic->id)
                        ->where('external_id', (string) $tag['subtopic_id'])
                        ->first();
                }
                $lo = null;
                if ($sub && ! empty($tag['level_2_id'])) {
                    $lo = SyllabusLearningObjective::query()
                        ->where('syllabus_subtopic_id', $sub->id)
                        ->where('objective_number', (int) $tag['level_2_id'])
                        ->first();
                }

                QuestionTopicTag::query()->create([
                    'question_id' => $questionId,
                    'syllabus_topic_id' => $topic->id,
                    'syllabus_subtopic_id' => $sub?->id,
                    'syllabus_learning_objective_id' => $lo?->id,
                    'relevance' => $tag['relevance'],
                    'score' => $tag['score'],
                    'reason' => $tag['reason'] ?? null,
                    'needs_review' => (bool) ($tag['needs_review'] ?? false),
                    'classifier_version' => QuestionTopicClassifier::VERSION,
                ]);
            }

            $newTagCount = count($tags);
            if ($existingTagCount > 0) {
                $stats['tags_updated'] += $newTagCount;
            } else {
                $stats['tags_created'] += $newTagCount;
            }

            $existing = QuestionDifficulty::query()->where('question_id', $questionId)->first();
            $payload = [
                'overall' => $difficulty['overall'],
                'score' => $difficulty['score'],
                'reasoning_complexity' => $difficulty['reasoning_complexity'],
                'calculation_complexity' => $difficulty['calculation_complexity'],
                'conceptual_depth' => $difficulty['conceptual_depth'],
                'multi_topic_dependency' => $difficulty['multi_topic_dependency'],
                'visual_interpretation' => $difficulty['visual_interpretation'],
                'trap_probability' => $difficulty['trap_probability'],
                'estimated_time_seconds' => $difficulty['estimated_time_seconds'],
                'primary_difficulty_driver' => $difficulty['primary_difficulty_driver'],
                'difficulty_reason' => $difficulty['difficulty_reason'],
                'needs_review' => (bool) ($difficulty['needs_review'] ?? false),
                'classifier_version' => QuestionDifficultyEvaluator::VERSION,
            ];
            if ($existing) {
                $existing->fill($payload)->save();
                $stats['difficulty_updated']++;
            } else {
                QuestionDifficulty::query()->create(['question_id' => $questionId] + $payload);
                $stats['difficulty_created']++;
            }
        });

        return $stats;
    }

    /**
     * @param array<string,mixed> $opts
     * @return array<int,string>
     */
    private function resolvePapers(array $opts): array
    {
        if ($opts['all']) {
            if ($opts['source'] === 'json') {
                $root = rtrim($opts['output_root'], "/\\");
                $matches = glob($root.DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'questions.json') ?: [];
                $stems = array_map(fn ($p) => basename(dirname($p)), $matches);
            } else {
                $stems = Paper::query()
                    ->orderBy('source_file')
                    ->pluck('source_file')
                    ->map(fn ($s) => preg_replace('/\.pdf$/i', '', (string) $s))
                    ->filter()
                    ->values()
                    ->all();
            }
            sort($stems);
            return $stems;
        }
        return $opts['papers'];
    }

    /**
     * @return array<int,array{question_id:?int,payload:array<string,mixed>}>
     */
    private function loadQuestionsFromJson(string $stem, string $outputRoot): array
    {
        $path = rtrim($outputRoot, "/\\").DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.$stem.DIRECTORY_SEPARATOR.'questions.json';
        if (! is_file($path)) {
            throw new RuntimeException("questions.json not found for paper {$stem}: {$path}");
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            throw new RuntimeException("Invalid questions.json: {$path}");
        }
        $rows = [];
        // Try to attach DB question id where possible so we can persist.
        $paper = Paper::query()->where('source_file', "{$stem}.pdf")->first();
        $questionMap = [];
        if ($paper) {
            $questionMap = Question::query()
                ->where('paper_id', $paper->id)
                ->pluck('id', 'question_number')
                ->all();
        }
        foreach ($decoded['questions'] as $q) {
            if (! is_array($q)) {
                continue;
            }
            $qNum = $q['question_number'] ?? null;
            $rows[] = [
                'question_id' => is_int($qNum) ? ($questionMap[$qNum] ?? null) : null,
                'payload' => $q,
            ];
        }
        return $rows;
    }

    /**
     * @return array<int,array{question_id:?int,payload:array<string,mixed>}>
     */
    private function loadQuestionsFromDb(string $stem): array
    {
        $paper = Paper::query()->where('source_file', "{$stem}.pdf")->first();
        if (! $paper) {
            throw new RuntimeException("Paper not found in DB: {$stem}");
        }
        $rows = [];
        $questions = Question::query()
            ->where('paper_id', $paper->id)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order'), 'assets', 'optionTable'])
            ->orderBy('question_number')
            ->get();

        foreach ($questions as $question) {
            $payload = is_array($question->raw_payload) ? $question->raw_payload : [];

            // Always force these fields from the DB-of-record so OCR fixes are reflected.
            $payload['question_number'] = $question->question_number;
            $payload['question_text'] = $question->question_text;
            $payload['layout_type'] = $question->layout_type instanceof \BackedEnum
                ? $question->layout_type->value : (string) $question->layout_type;
            $payload['correct_answer'] = $question->correct_answer;

            if (empty($payload['options'])) {
                $payload['options'] = $question->options->map(fn ($o) => [
                    'label' => $o->label,
                    'text' => $o->option_text,
                    'images' => [],
                ])->all();
            }

            if (empty($payload['assets'])) {
                $payload['assets'] = $question->assets->map(fn ($a) => [
                    'caption' => $a->caption,
                    'ocr_text' => $a->ocr_text,
                    'diagram_labels' => $a->diagram_labels,
                ])->all();
            }

            $rows[] = ['question_id' => $question->id, 'payload' => $payload];
        }
        return $rows;
    }

    private function writeExport(string $stem, string $outputRoot, array $enriched): string
    {
        $dir = rtrim($outputRoot, "/\\").DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.$stem;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'questions_enriched.json';
        $payload = [
            'paper' => $stem,
            'classifier_version' => QuestionTopicClassifier::VERSION,
            'difficulty_version' => QuestionDifficultyEvaluator::VERSION,
            'generated_at' => now()->toIso8601String(),
            'count' => count($enriched),
            'questions' => $enriched,
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $path;
    }
}
