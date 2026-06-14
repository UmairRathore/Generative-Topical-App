<?php

namespace App\Livewire\Admin;

use App\Enums\LayoutType;
use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Models\OptionTable;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionOption;
use App\Services\Rendering\QuestionRenderDataFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuestionForm extends Component
{
    use WithFileUploads;

    public Paper $paper;
    public ?Question $question = null;

    // Core
    public ?int $question_number = null;
    public ?string $question_text = null;
    public ?string $clean_question_text = null;
    public ?string $image_between_question_before_text = null;
    public ?string $image_between_question_after_text = null;
    public string $layout_type = 'standard';
    public ?string $correct_answer = null;
    public string $qa_status = 'review';
    public string $review_status = 'review';
    public string $visibility = 'admin_only';
    public bool $needs_review = false;
    public string $warnings_text = '';

    /** A/B/C/D form rows: ['text' => string, 'sort_order' => int] */
    public array $options = [];

    /** Per-label new uploads: ['A' => [TemporaryUploadedFile, ...], ...] */
    public array $newOptionImages = ['A' => [], 'B' => [], 'C' => [], 'D' => []];

    /** Existing option asset IDs marked for deletion */
    public array $deleteOptionAssetIds = [];

    /** New question-level uploads: list of TemporaryUploadedFile */
    public array $newQuestionImages = [];

    /** Roles for the new question-level uploads (parallel array) */
    public array $newQuestionImageRoles = [];

    /** Existing question-level asset IDs marked for deletion */
    public array $deleteQuestionAssetIds = [];

    // Option table
    public bool $has_option_table = false;
    public string $option_table_headers_text = '';
    public string $option_table_rows_text = '';
    public bool $option_table_use_fallback_image = false;
    public mixed $newOptionTableImage = null;
    public bool $deleteOptionTableImage = false;

    public array $allowedRoles = [
        'question_diagram',
        'question_image_between_text',
        'question_image_after_text',
        'full_question_fallback',
        'unknown_visual_asset',
    ];

    public function mount(Paper $paper, ?Question $question = null): void
    {
        $this->paper = $paper;

        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $this->options[$label] = ['text' => '', 'sort_order' => $i + 1];
        }

        if ($question && $question->exists) {
            abort_unless($question->paper_id === $paper->id, 404);
            $this->question = $question->load(['options.assets', 'assets', 'optionTable']);
            $this->loadFromQuestion($this->question);
        } else {
            $next = (int) (Question::where('paper_id', $paper->id)->max('question_number') ?? 0) + 1;
            $this->question_number = $next;
        }
    }

    private function loadFromQuestion(Question $q): void
    {
        $this->question_number = $q->question_number;
        $this->question_text = $q->question_text;
        $this->clean_question_text = $q->clean_question_text;
        $this->image_between_question_before_text = $q->image_between_question_before_text;
        $this->image_between_question_after_text = $q->image_between_question_after_text;
        $this->layout_type = $q->layout_type?->value ?? 'standard';
        $this->correct_answer = $q->correct_answer;
        $this->qa_status = $q->qa_status?->value ?? 'review';
        $this->review_status = $q->review_status?->value ?? 'review';
        $this->visibility = $q->visibility?->value ?? 'admin_only';
        $this->needs_review = (bool) $q->needs_review;
        $this->warnings_text = is_array($q->warnings) ? implode("\n", array_map(fn ($w) => is_string($w) ? $w : json_encode($w), $q->warnings)) : '';

        foreach ($q->options as $opt) {
            $this->options[$opt->label] = [
                'text' => $opt->option_text ?? '',
                'sort_order' => $opt->sort_order,
            ];
        }

        if ($q->optionTable) {
            $this->has_option_table = true;
            $headers = is_array($q->optionTable->headers) ? $q->optionTable->headers : [];
            $rows = is_array($q->optionTable->rows) ? $q->optionTable->rows : [];
            $this->option_table_headers_text = implode(', ', array_map(fn ($h) => is_string($h) ? $h : json_encode($h), $headers));
            $this->option_table_rows_text = implode("\n", array_map(
                fn ($r) => implode(' | ', array_map(fn ($c) => is_scalar($c) ? (string) $c : json_encode($c), (array) $r)),
                $rows,
            ));
            $this->option_table_use_fallback_image = (bool) $q->optionTable->use_fallback_image;
        }
    }

    public function save()
    {
        $this->validate([
            'question_number' => ['required', 'integer', 'min:1', 'max:255'],
            'question_text' => ['nullable', 'string'],
            'clean_question_text' => ['nullable', 'string'],
            'image_between_question_before_text' => ['nullable', 'string'],
            'image_between_question_after_text' => ['nullable', 'string'],
            'layout_type' => ['required', 'string'],
            'correct_answer' => ['nullable', 'in:A,B,C,D'],
            'qa_status' => ['required', 'string'],
            'review_status' => ['required', 'string'],
            'visibility' => ['required', 'string'],
            'needs_review' => ['boolean'],

            'options.A.text' => ['nullable', 'string'],
            'options.B.text' => ['nullable', 'string'],
            'options.C.text' => ['nullable', 'string'],
            'options.D.text' => ['nullable', 'string'],

            'newOptionImages.*.*' => ['nullable', 'image', 'max:5120'],
            'newQuestionImages.*' => ['nullable', 'image', 'max:5120'],
            'newOptionTableImage' => ['nullable', 'image', 'max:5120'],
        ]);

        // Validate role values for new question-level uploads
        foreach ($this->newQuestionImages as $i => $f) {
            $role = $this->newQuestionImageRoles[$i] ?? null;
            if ($f && (! is_string($role) || ! in_array($role, $this->allowedRoles, true))) {
                $this->addError("newQuestionImageRoles.$i", 'Pick a role for each uploaded image.');
                return;
            }
        }

        $warnings = collect(preg_split("/\r\n|\r|\n/", $this->warnings_text))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        $rawPayload = $this->question?->raw_payload ?? [];
        $rawPayload['source'] = $rawPayload['source'] ?? 'manual_admin';
        $rawPayload['created_from_admin'] = true;

        $stem = $this->paperStem();

        try {
            $question = DB::transaction(function () use ($warnings, $rawPayload, $stem) {
                $q = Question::updateOrCreate(
                    [
                        'paper_id' => $this->paper->id,
                        'question_number' => $this->question_number,
                    ],
                    [
                        'question_text' => $this->question_text,
                        'clean_question_text' => $this->clean_question_text,
                        'image_between_question_before_text' => $this->image_between_question_before_text,
                        'image_between_question_after_text' => $this->image_between_question_after_text,
                        'layout_type' => $this->layout_type,
                        'correct_answer' => $this->correct_answer,
                        'qa_status' => $this->qa_status,
                        'review_status' => $this->review_status,
                        'visibility' => $this->visibility,
                        'needs_review' => $this->needs_review,
                        'warnings' => $warnings ?: null,
                        'raw_payload' => $rawPayload,
                    ],
                );

                $this->syncOptions($q);
                $this->syncQuestionAssets($q, $stem);
                $this->syncOptionTable($q, $stem);

                return $q;
            });

            $this->question = $question->fresh(['options.assets', 'assets', 'optionTable']);
            $this->newOptionImages = ['A' => [], 'B' => [], 'C' => [], 'D' => []];
            $this->newQuestionImages = [];
            $this->newQuestionImageRoles = [];
            $this->newOptionTableImage = null;
            $this->deleteOptionAssetIds = [];
            $this->deleteQuestionAssetIds = [];
            $this->deleteOptionTableImage = false;

            session()->flash('flash', 'Question saved.');
            return redirect()->route('admin.papers.questions.edit', [$this->paper, $this->question]);
        } catch (\Throwable $e) {
            $this->addError('form', 'Save failed: '.$e->getMessage());
        }
    }

    private function syncOptions(Question $q): void
    {
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $row = $this->options[$label] ?? ['text' => '', 'sort_order' => $i + 1];
            $opt = QuestionOption::updateOrCreate(
                ['question_id' => $q->id, 'label' => $label],
                [
                    'option_text' => $row['text'] !== '' ? $row['text'] : null,
                    'sort_order' => (int) ($row['sort_order'] ?? $i + 1),
                ],
            );

            $files = $this->newOptionImages[$label] ?? [];
            foreach ($files as $file) {
                if (! $file) {
                    continue;
                }
                $path = $this->storeUpload($file, $this->paperStem(), $q->question_number);
                QuestionAsset::create([
                    'question_id' => $q->id,
                    'question_option_id' => $opt->id,
                    'role' => 'option_image',
                    'image_path' => $path,
                    'disk' => 'public',
                    'sort_order' => 0,
                    'raw_payload' => ['source' => 'manual_admin'],
                ]);
            }
        }

        if (! empty($this->deleteOptionAssetIds)) {
            QuestionAsset::where('question_id', $q->id)
                ->whereNotNull('question_option_id')
                ->whereIn('id', $this->deleteOptionAssetIds)
                ->each(function (QuestionAsset $a) {
                    Storage::disk($a->disk ?: 'public')->delete($a->image_path);
                    $a->delete();
                });
        }
    }

    private function syncQuestionAssets(Question $q, string $stem): void
    {
        if (! empty($this->deleteQuestionAssetIds)) {
            QuestionAsset::where('question_id', $q->id)
                ->whereNull('question_option_id')
                ->whereIn('id', $this->deleteQuestionAssetIds)
                ->each(function (QuestionAsset $a) {
                    Storage::disk($a->disk ?: 'public')->delete($a->image_path);
                    $a->delete();
                });
        }

        foreach ($this->newQuestionImages as $i => $file) {
            if (! $file) {
                continue;
            }
            $role = $this->newQuestionImageRoles[$i] ?? 'unknown_visual_asset';
            $path = $this->storeUpload($file, $stem, $q->question_number);
            QuestionAsset::create([
                'question_id' => $q->id,
                'role' => $role,
                'image_path' => $path,
                'disk' => 'public',
                'sort_order' => 0,
                'raw_payload' => ['source' => 'manual_admin'],
            ]);
        }
    }

    private function syncOptionTable(Question $q, string $stem): void
    {
        if (! $this->has_option_table) {
            $q->optionTable?->delete();
            return;
        }

        $headers = collect(explode(',', $this->option_table_headers_text))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();

        $rows = collect(preg_split("/\r\n|\r|\n/", $this->option_table_rows_text))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->map(fn ($l) => array_map('trim', explode('|', $l)))
            ->values()
            ->all();

        $existing = $q->optionTable;
        $imagePath = $existing?->image_path;

        if ($this->deleteOptionTableImage && $imagePath) {
            Storage::disk($existing->disk ?: 'public')->delete($imagePath);
            $imagePath = null;
        }

        if ($this->newOptionTableImage) {
            $imagePath = $this->storeUpload($this->newOptionTableImage, $stem, $q->question_number);
        }

        OptionTable::updateOrCreate(
            ['question_id' => $q->id],
            [
                'headers' => $headers ?: null,
                'rows' => $rows ?: null,
                'image_path' => $imagePath,
                'disk' => 'public',
                'use_fallback_image' => $this->option_table_use_fallback_image,
                'raw_payload' => ['source' => 'manual_admin'],
            ],
        );
    }

    private function storeUpload($file, string $stem, ?int $questionNumber): string
    {
        $dir = 'cambpast-assets/'.$stem.'/manual/q'.($questionNumber ?? 'na');
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $name = Str::random(16).'.'.$ext;
        $relative = $dir.'/'.$name;
        Storage::disk('public')->putFileAs($dir, $file, $name);
        return $relative;
    }

    private function paperStem(): string
    {
        return Str::of($this->paper->source_file)
            ->replace('/questions.json', '')
            ->replace('\\', '/')
            ->afterLast('/')
            ->__toString() ?: ('paper_'.$this->paper->id);
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $previewData = $this->question
            ? $factory->make($this->question->fresh(['options.assets', 'assets', 'optionTable']))
            : null;

        return view('livewire.admin.question-form', [
            'previewData' => $previewData,
            'layoutTypes' => array_map(fn ($c) => $c->value, LayoutType::cases()),
            'qaStatuses' => array_map(fn ($c) => $c->value, QaStatus::cases()),
            'reviewStatuses' => array_map(fn ($c) => $c->value, QuestionReviewStatus::cases()),
            'visibilities' => array_map(fn ($c) => $c->value, QuestionVisibility::cases()),
        ])->layout('components.layouts.dashboard');
    }
}
