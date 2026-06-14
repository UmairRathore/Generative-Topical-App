<?php

namespace App\Services\Import;

use App\Enums\LayoutType;
use App\Models\ImportBatch;
use App\Models\OptionTable;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionOption;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CambPastImportService
{
    public function __construct(
        private readonly QuestionStatusResolver $statusResolver,
        private readonly AssetImportService $assetImporter,
    ) {
    }

    /**
     * Import a single questions.json file. Returns counters.
     *
     * @param  array<int,int>  $blockerNumbers  Question numbers to force-hide as blockers (from qa worklist).
     * @return array{paper_id:?int, imported:int, skipped:int, demo_safe:int, errors:array<int,string>}
     */
    public function importPaperFromJson(
        string $questionsJsonAbsolutePath,
        string $outputRootAbsolutePath,
        ?ImportBatch $batch = null,
        array $blockerNumbers = [],
    ): array {
        $errors = [];
        $imported = 0;
        $skipped = 0;
        $demoSafe = 0;
        $paperId = null;

        if (! is_file($questionsJsonAbsolutePath)) {
            return ['paper_id' => null, 'imported' => 0, 'skipped' => 0, 'demo_safe' => 0,
                    'errors' => ["Missing file: {$questionsJsonAbsolutePath}"]];
        }

        $raw = file_get_contents($questionsJsonAbsolutePath);
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return ['paper_id' => null, 'imported' => 0, 'skipped' => 0, 'demo_safe' => 0,
                    'errors' => ["Invalid JSON: {$questionsJsonAbsolutePath}"]];
        }

        $paperFolder = dirname($questionsJsonAbsolutePath);
        $paperStem = basename($paperFolder);
        $meta = $this->extractPaperMeta($payload, $paperStem);

        $subject = Subject::where('code', $meta['paper_code'])->first();
        if (! $subject) {
            return ['paper_id' => null, 'imported' => 0, 'skipped' => 0, 'demo_safe' => 0,
                    'errors' => ["No subject for paper_code {$meta['paper_code']} (run db:seed)."]];
        }

        $questions = $this->extractQuestionsList($payload);
        $blockerSet = array_flip($blockerNumbers);

        try {
            DB::transaction(function () use (
                &$paperId, &$imported, &$skipped, &$demoSafe, &$errors,
                $subject, $meta, $questions, $paperStem, $paperFolder, $outputRootAbsolutePath, $blockerSet
            ) {
                $paper = Paper::updateOrCreate(
                    ['source_file' => $paperStem.'/questions.json'],
                    [
                        'subject_id' => $subject->id,
                        'paper_code' => $meta['paper_code'],
                        'paper_number' => $meta['paper_number'],
                        'variant' => $meta['variant'],
                        'session' => $meta['session'],
                        'session_code' => $meta['session_code'],
                        'year' => $meta['year'],
                        'total_questions' => count($questions),
                        'raw_meta' => $meta['raw_meta'],
                    ],
                );
                $paperId = $paper->id;

                foreach ($questions as $q) {
                    $qNum = (int) ($q['question_number'] ?? 0);
                    $isBlocker = isset($blockerSet[$qNum]);
                    try {
                        $this->importOneQuestion($paper, $q, $paperStem, $paperFolder, $outputRootAbsolutePath, $isBlocker);
                        $imported++;
                    } catch (\Throwable $e) {
                        $skipped++;
                        $errors[] = "Q{$qNum} failed: ".$e->getMessage();
                        Log::warning('cambpast import question failed', [
                            'paper' => $paperStem,
                            'question_number' => $qNum,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                $demoSafe = Question::where('paper_id', $paper->id)->demoSafe()->count();
                $paper->forceFill([
                    'imported_questions_count' => $imported,
                    'demo_safe_questions_count' => $demoSafe,
                ])->save();
            });
        } catch (\Throwable $e) {
            $errors[] = "Paper transaction failed: ".$e->getMessage();
        }

        return [
            'paper_id' => $paperId,
            'imported' => $imported,
            'skipped' => $skipped,
            'demo_safe' => $demoSafe,
            'errors' => $errors,
        ];
    }

    private function importOneQuestion(Paper $paper, array $q, string $paperStem, string $paperFolder, string $outputRoot, bool $isBlocker = false): Question
    {
        if ($isBlocker) {
            $q['__force_blocker'] = true;
        }
        $statuses = $this->statusResolver->resolve($q);

        $question = Question::updateOrCreate(
            [
                'paper_id' => $paper->id,
                'question_number' => (int) ($q['question_number'] ?? 0),
            ],
            [
                'question_text' => $q['question_text'] ?? null,
                'clean_question_text' => $q['clean_question_text'] ?? null,
                // Extractor stores the *split text* before/after the embedded diagram in these fields,
                // not image paths. Schema column is longText; treat as text.
                'image_between_question_before_text' => $this->stringOrNull($q['image_between_question_before_text'] ?? null),
                'image_between_question_after_text' => $this->stringOrNull($q['image_between_question_after_text'] ?? null),
                'correct_answer' => $this->normalizeCorrectAnswer($q['correct_answer'] ?? null),
                'explanation' => $q['explanation'] ?? null,
                'page_start' => $q['page_start'] ?? null,
                'page_end' => $q['page_end'] ?? null,
                'layout_type' => $this->normalizeLayoutType($q['layout_type'] ?? null),
                'qa_status' => $statuses['qa_status'],
                'review_status' => $statuses['review_status'],
                'visibility' => $statuses['visibility'],
                'needs_review' => $statuses['needs_review'],
                'warnings' => $q['warnings'] ?? null,
                'raw_payload' => $q,
            ],
        );

        // Replace child rows on every import — keeps re-imports idempotent.
        $question->options()->delete();
        $question->assets()->delete();
        $question->optionTable()->delete();

        $this->importOptions($question, $q, $paperStem, $paperFolder, $outputRoot);
        $this->importQuestionAssets($question, $q, $paperStem, $paperFolder, $outputRoot);
        $this->importOptionTable($question, $q, $paperStem, $paperFolder, $outputRoot);

        return $question;
    }

    private function importOptions(Question $question, array $q, string $paperStem, string $paperFolder, string $outputRoot): void
    {
        $options = $q['options'] ?? [];
        if (! is_array($options)) {
            return;
        }

        $sort = 0;
        foreach (['A', 'B', 'C', 'D'] as $label) {
            $opt = $this->locateOption($options, $label);
            if ($opt === null) {
                continue;
            }

            $sort++;
            $option = QuestionOption::create([
                'question_id' => $question->id,
                'label' => $label,
                'option_text' => $opt['text'] ?? null,
                'sort_order' => $sort,
                'raw_payload' => $opt,
            ]);

            $images = $opt['images'] ?? [];
            if (! is_array($images)) {
                continue;
            }
            $i = 0;
            foreach ($images as $img) {
                $ref = is_string($img) ? $img : ($img['image_path'] ?? $img['path'] ?? null);
                if (! is_string($ref) || $ref === '') {
                    continue;
                }
                $path = $this->copyAssetIfPath($ref, $paperStem, $paperFolder, $outputRoot);
                if ($path === null) {
                    continue;
                }
                QuestionAsset::create([
                    'question_id' => $question->id,
                    'question_option_id' => $option->id,
                    'role' => 'option_image',
                    'image_path' => $path,
                    'disk' => 'public',
                    'page' => is_array($img) ? ($img['page'] ?? null) : null,
                    'bbox' => is_array($img) ? ($img['bbox'] ?? null) : null,
                    'caption' => is_array($img) ? ($img['caption'] ?? null) : null,
                    'sort_order' => $i++,
                    'raw_payload' => is_array($img) ? $img : ['source' => $ref],
                ]);
            }
        }
    }

    private function locateOption(array $options, string $label): ?array
    {
        if (isset($options[$label]) && is_array($options[$label])) {
            return $options[$label];
        }
        foreach ($options as $opt) {
            if (is_array($opt)) {
                $candidate = $opt['label'] ?? null;
                if (is_string($candidate) && strtoupper($candidate) === $label) {
                    return $opt;
                }
            }
        }
        return null;
    }

    /**
     * Iterate the question-level `assets` list (which is the authoritative source for diagrams)
     * and persist each as a QuestionAsset with its declared role.
     */
    private function importQuestionAssets(Question $question, array $q, string $paperStem, string $paperFolder, string $outputRoot): void
    {
        $assets = $q['assets'] ?? [];
        if (! is_array($assets)) {
            return;
        }

        $sort = 0;
        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }
            $ref = $asset['image_path'] ?? $asset['path'] ?? null;
            if (! is_string($ref) || $ref === '') {
                continue;
            }
            $path = $this->copyAssetIfPath($ref, $paperStem, $paperFolder, $outputRoot);
            if ($path === null) {
                continue;
            }
            QuestionAsset::create([
                'question_id' => $question->id,
                'role' => $this->normalizeRole($asset['role'] ?? null),
                'image_path' => $path,
                'disk' => 'public',
                'page' => $asset['page'] ?? null,
                'bbox' => $asset['bbox'] ?? null,
                'caption' => $asset['caption'] ?? null,
                'ocr_text' => $asset['ocr_text'] ?? null,
                'confidence' => $asset['confidence'] ?? null,
                'diagram_labels' => $asset['diagram_labels'] ?? null,
                'sort_order' => $sort++,
                'raw_payload' => $asset,
            ]);
        }
    }

    private function importOptionTable(Question $question, array $q, string $paperStem, string $paperFolder, string $outputRoot): void
    {
        $table = $q['option_table'] ?? null;
        if (! is_array($table)) {
            return;
        }

        $fallbackRef = $table['image_path'] ?? null;
        $fallbackPath = is_string($fallbackRef) && $fallbackRef !== ''
            ? $this->copyAssetIfPath($fallbackRef, $paperStem, $paperFolder, $outputRoot)
            : null;

        $hasRows = is_array($table['rows'] ?? null) && count($table['rows']) > 0;
        $useFallback = (bool) ($table['use_fallback_image'] ?? (! $hasRows && $fallbackPath !== null));

        OptionTable::create([
            'question_id' => $question->id,
            'headers' => $table['headers'] ?? null,
            'rows' => $table['rows'] ?? null,
            'image_path' => $fallbackPath,
            'disk' => 'public',
            'bbox' => $table['bbox'] ?? null,
            'diagram_labels' => $table['diagram_labels'] ?? null,
            'use_fallback_image' => $useFallback,
            'raw_payload' => $table,
        ]);
    }

    private function copyAssetIfPath(?string $reference, string $paperStem, string $paperFolder, string $outputRoot): ?string
    {
        if (! is_string($reference) || $reference === '') {
            return null;
        }
        $resolved = $this->assetImporter->resolveSourcePath($reference, $paperFolder, $outputRoot);
        if ($resolved === null) {
            return null;
        }
        return $this->assetImporter->copyPublicAsset($resolved, $paperStem);
    }

    /**
     * @return array{paper_code:string, paper_number:?int, variant:?string, session:string, session_code:?string, year:?int, raw_meta:array<string,mixed>}
     */
    private function extractPaperMeta(array $payload, string $paperStem): array
    {
        $meta = $payload['paper'] ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }

        // Extractor uses "9702/12" — split into subject code and paper-variant suffix.
        $rawCode = (string) ($meta['paper_code'] ?? '');
        $codeParts = explode('/', $rawCode, 2);
        $subjectCode = $codeParts[0] !== '' ? $codeParts[0] : null;
        $codeSuffix = $codeParts[1] ?? null;

        $stemParts = $this->parseStem($paperStem);
        $paperNumber = $stemParts['paper_number'];
        $variant = $stemParts['variant'];

        if ($paperNumber === null && is_string($codeSuffix) && preg_match('/^(\d)(\d)$/', $codeSuffix, $m)) {
            $paperNumber = (int) $m[1];
            $variant = $m[2];
        }

        $session = $stemParts['session'] ?? null;
        $year = $stemParts['year'];

        if (is_string($meta['session'] ?? null)) {
            // e.g. "February/March 2024" — pull the year if our stem parse missed it.
            if ($year === null && preg_match('/(\d{4})/', $meta['session'], $ym)) {
                $year = (int) $ym[1];
            }
            if ($session === null) {
                $session = $meta['session'];
            }
        }

        return [
            'paper_code' => (string) ($subjectCode ?? $stemParts['paper_code'] ?? '0000'),
            'paper_number' => $paperNumber,
            'variant' => $variant,
            'session' => $session ?? 'unknown',
            'session_code' => $stemParts['session_code'],
            'year' => $year,
            'raw_meta' => $meta + ['_stem' => $paperStem],
        ];
    }

    private function parseStem(string $stem): array
    {
        $parts = explode('_', $stem);
        $code = $parts[0] ?? null;
        $sessionCode = $parts[1] ?? null;
        $variantRaw = $parts[3] ?? null;

        $session = $sessionCode;
        $year = null;
        if (is_string($sessionCode) && preg_match('/^([a-z])(\d{2})$/i', $sessionCode, $m)) {
            $session = match (strtolower($m[1])) {
                'm' => 'march',
                's' => 'may-june',
                'w' => 'oct-nov',
                default => $sessionCode,
            };
            $year = 2000 + (int) $m[2];
        }

        $paperNumber = null;
        $variant = null;
        if (is_string($variantRaw) && preg_match('/^(\d)(\d)$/', $variantRaw, $vm)) {
            $paperNumber = (int) $vm[1];
            $variant = $vm[2];
        } elseif (ctype_digit((string) $variantRaw)) {
            $paperNumber = (int) $variantRaw;
        }

        return [
            'paper_code' => $code,
            'session_code' => $sessionCode,
            'session' => $session,
            'year' => $year,
            'paper_number' => $paperNumber,
            'variant' => $variant,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function extractQuestionsList(array $payload): array
    {
        if (isset($payload['questions']) && is_array($payload['questions'])) {
            return array_values(array_filter($payload['questions'], 'is_array'));
        }
        return [];
    }

    private function normalizeCorrectAnswer(mixed $value): ?string
    {
        if (is_string($value) && in_array(strtoupper($value), ['A', 'B', 'C', 'D'], true)) {
            return strtoupper($value);
        }
        return null;
    }

    private function normalizeLayoutType(mixed $value): string
    {
        if (is_string($value) && LayoutType::tryFrom($value) !== null) {
            return $value;
        }
        return LayoutType::Unknown->value;
    }

    private function normalizeRole(mixed $role): string
    {
        if (is_string($role) && $role !== '') {
            return substr($role, 0, 40);
        }
        return 'question_extra';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        return null;
    }
}
