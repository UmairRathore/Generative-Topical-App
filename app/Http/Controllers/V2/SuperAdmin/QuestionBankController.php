<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Paper;
use App\Models\V2\QualityReview;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Models\V2\QuestionImage;
use App\Models\V2\QuestionVersion;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use App\Services\V2\AuditLogger;
use App\Services\V2\NotificationService;
use App\Services\V2\QuestionSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Super Admin: Question Bank
|--------------------------------------------------------------------------
| Browse + full CRUD over the global question pool. Manually authored
| questions attach to a per-subject "Custom" paper so the paper_id FK and the
| [paper_id, question_number] unique constraint hold.
|
| The editor mirrors the imported corpus's structure (see v2:import-questions):
|   STEM   : question_text, with optional text_before / between-diagram(s) /
|            text_after / after-diagram(s) for a figure that sits inside the text.
|   ANSWERS: either A–D options (each text and/or a graph image), OR a single
|            answer image (a table or graph) shown with selectable A/B/C/D
|            circles beside it (the option_table layout).
|
| status (active | draft | under_review | archived) gates exam generation,
| only `active` is drawn into a test. Active questions cannot be deleted.
*/
class QuestionBankController extends Controller
{
    private const SESSION_LABELS = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];

    private const STATUSES = ['active', 'draft', 'under_review', 'archived'];

    /** Statuses a question may be deleted from (anything but live/active). */
    private const DELETABLE = ['draft', 'under_review', 'archived'];

    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    private const OPTION_LABELS = ['A', 'B', 'C', 'D'];

    private const STEM_ROLES = ['question_image_between_text', 'question_image_after_text'];

    private const ANSWER_TYPES = ['table', 'graph'];

    /** Image-content filters for QA: value => the image roles that satisfy it. */
    private const IMAGE_FILTERS = [
        'diagram'   => ['question_image_between_text', 'question_image_after_text'],
        'option'    => ['option_image'],
        'table'     => ['table'],
        'reference' => ['reference'],
    ];

    /** Manually uploaded images live here; only these are ever unlinked on delete. */
    private const UPLOAD_DIR = 'v2/questions/custom';

    public function index(Request $request)
    {
        $filters = [
            'level'   => $request->string('level')->toString(),
            'subject' => $request->integer('subject') ?: null,
            'year'    => $request->integer('year') ?: null,
            'session' => $request->string('session')->toString(),
            'variant' => $request->string('variant')->toString(),
            'topic'   => $request->integer('topic') ?: null,
            'layout'  => $request->string('layout')->toString(),
            'answer'  => $request->string('answer')->toString(),
            'image'   => $request->string('image')->toString(),
            'status'  => $request->string('status')->toString(),
            'q'       => trim($request->string('q')->toString()),
        ];

        $view = $request->string('view')->toString() === 'gallery' ? 'gallery' : 'table';

        $query = Question::query()
            ->with(['paper:id,source_paper,year,session_code,variant', 'subject:id,name,code,level', 'topic:id,external_id,title'])
            ->withCount(['options', 'images'])
            ->when($view === 'gallery', fn ($q) => $q->with(['options', 'images']))
            ->when($filters['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($filters['year'], fn ($q, $v) => $q->where('year', $v))
            ->when($filters['topic'], fn ($q, $v) => $q->where('topic_id', $v))
            ->when($filters['layout'], fn ($q, $v) => $q->where('layout_type', $v))
            // "Trash" is the soft-deleted set (orthogonal to the status column).
            // "Untagged" filters by missing topic, not by the status column.
            ->when($filters['status'] === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($filters['status'] === 'untagged', fn ($q) => $q->whereNull('topic_id'))
            ->when($filters['status'] && !in_array($filters['status'], ['trashed', 'untagged']), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['level'], fn ($q, $v) => $q->whereHas('subject', fn ($s) => $s->where('level', $v)))
            ->when($filters['session'], fn ($q, $v) => $q->whereHas('paper', fn ($p) => $p->where('session_code', $v)))
            ->when($filters['variant'], fn ($q, $v) => $q->whereHas('paper', fn ($p) => $p->where('variant', $v)))
            ->when($filters['answer'] === 'answered', fn ($q) => $q->whereNotNull('correct_answer'))
            ->when($filters['answer'] === 'unanswered', fn ($q) => $q->whereNull('correct_answer'))
            ->when($filters['image'], function ($q, $v) {
                if ($v === 'any') {
                    $q->has('images');
                } elseif ($v === 'none') {
                    $q->doesntHave('images');
                } elseif ($roles = self::IMAGE_FILTERS[$v] ?? null) {
                    $q->whereHas('images', fn ($i) => $i->whereIn('role', $roles));
                }
            })
            ->when($filters['q'], function ($q, $v) {
                $q->where(fn ($w) => $w
                    ->where('question_text', 'like', "%{$v}%")
                    ->orWhere('source_paper', 'like', "%{$v}%"));
            })
            ->orderBy('subject_id')
            ->orderByDesc('year')
            ->orderBy('source_paper')
            ->orderBy('question_number');

        $questions = $query->paginate($view === 'gallery' ? 12 : 25)->withQueryString();

        $subjectIds = Question::query()->distinct()->pluck('subject_id');

        // Per-subject year / session / variant lists drive the cascading filters
        // (level -> subject -> year -> session -> variant; topic -> level+subject).
        $subjectMeta = Paper::whereIn('subject_id', $subjectIds)
            ->get(['subject_id', 'year', 'session_code', 'variant'])
            ->groupBy('subject_id')
            ->map(fn ($g) => [
                'years'    => $g->pluck('year')->filter()->unique()->sortDesc()->values(),
                'sessions' => $g->pluck('session_code')->filter()->unique()->values(),
                'variants' => $g->pluck('variant')->filter()->unique()->sort()->values(),
            ]);

        return view('v2.super_admin.question_bank.index', [
            'questions' => $questions,
            'filters'   => $filters,
            'view'      => $view,
            'subjects'  => Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code', 'level']),
            'levels'    => Subject::whereIn('id', $subjectIds)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'years'     => Question::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'topics'    => Topic::query()->whereIn('subject_id', $subjectIds)
                ->orderBy('subject_id')->orderBy('sort_order')
                ->get(['id', 'subject_id', 'external_id', 'title']),
            'subjectMeta' => $subjectMeta,
            'layouts'   => Question::query()->distinct()->orderBy('layout_type')->pluck('layout_type')->filter()->values(),
            'sessions'  => self::SESSION_LABELS,
            'variants'  => ['11', '12', '13', '14'],
            'statuses'  => self::STATUSES,
            'deletable' => self::DELETABLE,
            'stats'     => [
                'total'   => Question::count(),
                'tagged'  => Question::whereNotNull('topic_id')->count(),
                'papers'  => Paper::count(),
                'matched' => $questions->total(),
                'trashed' => Question::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('v2.super_admin.question_bank.form', $this->formData(new Question([
            'marks'  => 1,
            'status' => 'active',
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validateQuestion($request, null);

        $subject = Subject::findOrFail($data['subject_id']);
        $paper = $this->customPaperFor($subject);

        DB::transaction(function () use ($request, $data, $subject, $paper) {
            $question = Question::create([
                'paper_id'        => $paper->id,
                'subject_id'      => $subject->id,
                'topic_id'        => $data['topic_id'] ?? null,
                'year'            => $data['year'] ?? null,
                'question_number' => (int) Question::where('paper_id', $paper->id)->max('question_number') + 1,
                'question_text'   => $data['question_text'],
                'text_before'     => $data['text_before'] ?? null,
                'text_after'      => $data['text_after'] ?? null,
                'layout_type'     => 'text_only',
                'correct_answer'  => $data['correct_answer'],
                'marks'           => $data['marks'],
                'difficulty'      => $data['difficulty'] ?? null,
                'status'          => $data['status'],
                'source_paper'    => $paper->source_paper,
            ]);

            $this->syncOptions($question, $data['options']);
            $this->syncImages($question, $request, $data['answer_mode']);
            $this->recordVersion($question, $request);
        });

        return redirect()
            ->route('v2.super_admin.question_bank.index', ['subject' => $subject->id, 'status' => $data['status']])
            ->with('ok', 'Question created.');
    }

    public function edit(Question $question)
    {
        $question->load('options', 'images');

        // Carried through when the editor is opened from a Quality Review ("Correct
        // Question"), so the saved version links back to that review.
        return view('v2.super_admin.question_bank.form', $this->formData($question) + [
            'quality_review_id' => request('quality_review_id') ?: null,
        ]);
    }

    /** Read-only content history: every immutable version, rendered, with a diff vs current. */
    public function versions(Question $question)
    {
        $question->load('currentVersion');

        $versions = QuestionVersion::where('question_id', $question->id)
            ->orderByDesc('version_number')->get();

        $current = $question->currentVersion?->snapshot ?? [];

        $rows = $versions->map(fn ($v) => [
            'version'    => $v,
            'rendered'   => $v->toRenderableQuestion(),
            'is_current' => $v->id === $question->current_version_id,
            'diff'       => $v->id === $question->current_version_id ? [] : $this->diffSnapshots($v->snapshot ?? [], $current),
        ]);

        return view('v2.super_admin.question_bank.versions', compact('question', 'rows'));
    }

    /** Field-level changes from snapshot $a to the current snapshot $b. */
    private function diffSnapshots(array $a, array $b): array
    {
        $rows = [];

        foreach (['question_text', 'text_before', 'text_after', 'correct_answer', 'layout_type'] as $f) {
            $av = $a['question'][$f] ?? null;
            $bv = $b['question'][$f] ?? null;
            if ($av !== $bv) {
                $rows[] = ['field' => $f, 'old' => $av, 'new' => $bv];
            }
        }

        $optA = collect($a['options'] ?? [])->keyBy('label');
        $optB = collect($b['options'] ?? [])->keyBy('label');
        foreach (self::OPTION_LABELS as $l) {
            $av = $optA[$l]['text'] ?? null;
            $bv = $optB[$l]['text'] ?? null;
            if ($av !== $bv) {
                $rows[] = ['field' => "option {$l}", 'old' => $av, 'new' => $bv];
            }
        }

        $imgA = collect($a['images'] ?? [])->pluck('image_path')->sort()->values()->all();
        $imgB = collect($b['images'] ?? [])->pluck('image_path')->sort()->values()->all();
        if ($imgA !== $imgB) {
            $rows[] = ['field' => 'images', 'old' => count($imgA).' image(s)', 'new' => count($imgB).' image(s)'];
        }

        return $rows;
    }

    public function update(Request $request, Question $question, NotificationService $notifications)
    {
        $data = $this->validateQuestion($request, $question);

        // Opened from a Quality Review ("Correct question"): the admin must classify
        // the correction as Cosmetic or Material — that closes the review on save.
        $reviewId = $request->integer('quality_review_id') ?: null;
        $outcome  = $reviewId
            ? $request->validate(['outcome' => ['required', Rule::in(['cosmetic', 'material'])]])['outcome']
            : null;

        $decided = null;

        DB::transaction(function () use ($request, $data, $question, $reviewId, $outcome, &$decided) {
            $question->update([
                'subject_id'     => $data['subject_id'],
                'topic_id'       => $data['topic_id'] ?? null,
                'year'           => $data['year'] ?? null,
                'question_text'  => $data['question_text'],
                'text_before'    => $data['text_before'] ?? null,
                'text_after'     => $data['text_after'] ?? null,
                'correct_answer' => $data['correct_answer'],
                'marks'          => $data['marks'],
                'difficulty'     => $data['difficulty'] ?? null,
                'status'         => $data['status'],
            ]);

            $this->syncOptions($question, $data['options']);
            $this->syncImages($question, $request, $data['answer_mode']);
            $version = $this->recordVersion($question, $request);
            $decided = $this->finalizeReview($reviewId, $outcome, $version->id, $question);
        });

        // Notify the reporting teacher(s) only after the decision has committed.
        if ($decided) {
            $notifications->notifyReviewDecision($decided, $outcome);
            AuditLogger::record('quality_review.'.$outcome, $question, ['review_id' => $decided->id]);
        }

        // Return to the filtered/paged list the editor came from, so the admin
        // keeps their place instead of being dropped at an unfiltered page 1.
        return redirect($this->safeReturn($request) ?? route('v2.super_admin.question_bank.index'))
            ->with('ok', $decided
                ? 'Saved as a new version — quality review closed and the reporting teacher notified.'
                : 'Question updated — saved as a new version.');
    }

    /**
     * Apply a Support decision made by editing the question (Cosmetic / Material).
     * Runs inside the save transaction after the new version exists: records the
     * outcome on the review, links the resulting version, restores the question to
     * the active pool and closes the attached reports. A material error is marked
     * propagation_pending for the Phase 3 historical-propagation job — no past exams
     * are voided or recomputed here. Returns the decided review (so the caller can
     * notify after commit), or null when there is nothing to decide.
     */
    private function finalizeReview(?int $reviewId, ?string $outcome, int $versionId, Question $question): ?QualityReview
    {
        if (! $reviewId || ! in_array($outcome, ['cosmetic', 'material'], true)) {
            return null;
        }

        $review = QualityReview::find($reviewId);
        if (! $review || $review->status === 'decided') {
            return null;
        }

        $adminId = auth('v2_super_admin')->id();

        $review->update([
            'outcome'              => $outcome,
            'status'               => 'decided',
            'reviewed_by'          => $adminId,
            'reviewed_at'          => now(),
            'resulting_version_id' => $versionId,
            'propagation_status'   => $outcome === 'material' ? 'propagation_pending' : null,
        ]);

        // The corrected question returns to the active pool.
        if ($question->status === 'under_review') {
            $question->update(['status' => 'active']);
        }

        // Close the reports this review resolved.
        QuestionFlag::where('quality_review_id', $review->id)
            ->whereIn('status', ['open', 'escalated'])
            ->update(['status' => 'resolved', 'resolved_by' => $adminId, 'resolved_at' => now()]);

        return $review;
    }

    /**
     * Soft-delete only — a question is NEVER removed from the database. It moves to
     * Trash (recoverable via restore); its options and images are kept intact so a
     * restore is lossless, and frozen exams keep rendering it (ExamQuestion::question
     * uses withTrashed). Active questions must be archived/drafted first.
     */
    public function destroy(Request $request, Question $question)
    {
        if (! in_array($question->status, self::DELETABLE, true)) {
            $msg = 'Active questions can’t be moved to Trash - set them to draft, under review, or archived first.';

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : back()->with('err', $msg);
        }

        $question->delete(); // soft delete (sets deleted_at; row, options and images all kept)

        $msg = 'Question moved to Trash. You can restore it any time from the Trash view.';

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'message' => $msg])
            : redirect()->route('v2.super_admin.question_bank.index')->with('ok', $msg);
    }

    /**
     * Bring a soft-deleted question back into the bank. Soft-deleted models don't
     * route-bind (the SoftDeletes scope hides them), so we resolve the hashid by hand.
     */
    public function restore(Request $request, string $question)
    {
        $id = unhid($question) ?? (ctype_digit($question) ? (int) $question : null);
        $q = Question::onlyTrashed()->findOrFail($id);
        $q->restore();

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'message' => 'Question restored.'])
            : back()->with('ok', 'Question restored.');
    }

    public function setStatus(Request $request, Question $question)
    {
        $validated = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        $question->update(['status' => $validated['status']]);

        $msg = 'Question marked '.str_replace('_', ' ', $validated['status']).'.';

        // AJAX (status dropdown on the list): respond with JSON so the page
        // updates the badge in place instead of reloading and losing scroll.
        if ($request->wantsJson()) {
            return response()->json([
                'ok'        => true,
                'message'   => $msg,
                'status'    => $validated['status'],
                'deletable' => in_array($validated['status'], self::DELETABLE, true),
            ]);
        }

        return back()->with('ok', $msg);
    }

    /**
     * A safe "go back to where I was" URL passed through the editor — only ever
     * a question-bank index URL on this host, never an open redirect.
     */
    private function safeReturn(Request $request): ?string
    {
        $return = $request->input('return') ?: $request->query('return');
        if (! $return) {
            return null;
        }

        $base = route('v2.super_admin.question_bank.index');

        return str_starts_with((string) $return, $base) ? (string) $return : null;
    }

    /* ---------------------------------------------------------------------- */

    private function formData(Question $question): array
    {
        return [
            'question'     => $question,
            'subjects'     => Subject::orderBy('name')->get(['id', 'name', 'code']),
            'topics'       => Topic::orderBy('subject_id')->orderBy('sort_order')
                                ->get(['id', 'subject_id', 'external_id', 'title']),
            'statuses'     => self::STATUSES,
            'difficulties' => self::DIFFICULTIES,
            'labels'       => self::OPTION_LABELS,
            'answerTypes'  => self::ANSWER_TYPES,
        ];
    }

    private function validateQuestion(Request $request, ?Question $question): array
    {
        $imageRules = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'];

        $rules = [
            'subject_id'               => ['required', Rule::exists('v2_subjects', 'id')],
            'topic_id'                 => ['nullable', Rule::exists('v2_topics', 'id')],
            'question_text'            => ['required', 'string', 'max:5000'],
            'text_before'              => ['nullable', 'string', 'max:5000'],
            'text_after'               => ['nullable', 'string', 'max:5000'],
            'correct_answer'           => ['required', Rule::in(self::OPTION_LABELS)],
            'marks'                    => ['required', 'integer', 'min:1', 'max:20'],
            'difficulty'               => ['nullable', Rule::in(self::DIFFICULTIES)],
            'year'                     => ['nullable', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'status'                   => ['required', Rule::in(self::STATUSES)],
            'answer_mode'              => ['required', Rule::in(['options', 'image'])],
            'options'                  => ['required', 'array'],
            // Stem diagrams, two positions, plus a removal list of existing ids.
            'question_images_between'   => ['nullable', 'array', 'max:6'],
            'question_images_between.*' => $imageRules,
            'question_images_after'     => ['nullable', 'array', 'max:6'],
            'question_images_after.*'   => $imageRules,
            'remove_question_images'    => ['nullable', 'array'],
            'remove_question_images.*'  => ['integer'],
            // Single answer image (table/graph) + its type.
            'answer_image'             => $imageRules,
            'answer_image_type'        => ['nullable', Rule::in(self::ANSWER_TYPES)],
            'remove_answer_image'      => ['nullable', 'boolean'],
        ];
        foreach (self::OPTION_LABELS as $label) {
            $rules["options.$label"]       = ['nullable', 'string', 'max:1000'];
            $rules["option_images.$label"] = $imageRules;
        }

        $validated = $request->validate($rules);

        if ($validated['answer_mode'] === 'image') {
            // Need an answer image: a new upload, or an existing one kept on edit.
            $hasNew  = $request->hasFile('answer_image');
            $hasKept = $question
                && ! $request->boolean('remove_answer_image')
                && $question->images()->where('role', 'table')->exists();
            if (! $hasNew && ! $hasKept) {
                throw ValidationException::withMessages(['answer_image' => 'Upload the answer image (the table or graph).']);
            }
            if ($hasNew && empty($validated['answer_image_type'])) {
                throw ValidationException::withMessages(['answer_image_type' => 'Choose whether the answer image is a table or a graph.']);
            }
        } else {
            // A–D mode: each option needs text or an image.
            foreach (self::OPTION_LABELS as $label) {
                $hasText = trim((string) ($validated['options'][$label] ?? '')) !== '';
                $newImg  = $request->hasFile("option_images.$label");
                $keptImg = $question
                    && ! $request->boolean("remove_option_images.$label")
                    && $question->images()->where('role', 'option_image')->where('option_label', $label)->exists();
                if (! $hasText && ! $newImg && ! $keptImg) {
                    throw ValidationException::withMessages(["options.$label" => "Option {$label} needs either text or an image."]);
                }
            }
        }

        return $validated;
    }

    private function syncOptions(Question $question, array $options): void
    {
        $question->options()->delete();

        $rows = [];
        foreach (self::OPTION_LABELS as $i => $label) {
            $rows[] = [
                'question_id' => $question->id,
                'label'       => $label,
                'text'        => $options[$label] ?? '',
                'has_image'   => false,
                'sort_order'  => $i + 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        $question->options()->insert($rows);
    }

    /**
     * Apply all image changes, then recompute layout_type the way the importer
     * classifies the corpus:
     *   option_table   -> a single answer image (table/graph) with A–D circles
     *   question_diagram_and_option_images / option_images / question_diagram /
     *   text_only      -> derived from stem + per-option images.
     */
    private function syncImages(Question $question, Request $request, string $answerMode): void
    {
        // ---- Stem diagrams (between / after) ----
        $removeIds = array_map('intval', (array) $request->input('remove_question_images', []));
        if ($removeIds) {
            $this->deleteImages($question->images->whereIn('role', self::STEM_ROLES)->whereIn('id', $removeIds));
        }
        $nextSort = (int) ($question->images->whereIn('role', self::STEM_ROLES)->whereNotIn('id', $removeIds)->max('sort_order')) + 1;
        foreach ((array) $request->file('question_images_between', []) as $file) {
            $this->storeImage($question, $file, 'question_image_between_text', null, $nextSort++);
        }
        foreach ((array) $request->file('question_images_after', []) as $file) {
            $this->storeImage($question, $file, 'question_image_after_text', null, $nextSort++);
        }

        // ---- Answer area ----
        if ($answerMode === 'image') {
            // Single table/graph answer image (role 'table'); drop any per-option images.
            $this->deleteImages($question->images->where('role', 'option_image'));
            if ($request->boolean('remove_answer_image') || $request->hasFile('answer_image')) {
                $this->deleteImages($question->images->where('role', 'table'));
            }
            if ($request->hasFile('answer_image')) {
                $type = $request->input('answer_image_type', 'table');
                $this->storeImage($question, $request->file('answer_image'), 'table', null, 0, "answer:{$type}");
            }
        } else {
            // A–D options; drop any answer-image (switching away from the table layout).
            $this->deleteImages($question->images->where('role', 'table'));
            foreach (self::OPTION_LABELS as $label) {
                $existing = $question->images->where('role', 'option_image')->where('option_label', $label);
                if ($request->boolean("remove_option_images.$label") || $request->hasFile("option_images.$label")) {
                    $this->deleteImages($existing);
                }
                if ($request->hasFile("option_images.$label")) {
                    $this->storeImage($question, $request->file("option_images.$label"), 'option_image', $label, 0);
                }
            }
        }

        // ---- Recompute derived fields ----
        $question->load('images', 'options');
        $hasTable  = $question->images->where('role', 'table')->isNotEmpty();
        $hasStem   = $question->images->whereIn('role', self::STEM_ROLES)->isNotEmpty();
        $hasOption = $question->images->where('role', 'option_image')->isNotEmpty();

        $question->update([
            'layout_type' => match (true) {
                $hasTable              => 'option_table',
                $hasStem && $hasOption => 'question_diagram_and_option_images',
                $hasOption             => 'option_images',
                $hasStem               => 'question_diagram',
                default                => 'text_only',
            },
        ]);

        foreach ($question->options as $opt) {
            $opt->update([
                'has_image' => $question->images->where('role', 'option_image')->where('option_label', $opt->label)->isNotEmpty(),
            ]);
        }
    }

    private function storeImage(Question $question, UploadedFile $file, string $role, ?string $label, int $sort, ?string $caption = null): void
    {
        $dims = @getimagesize($file->getRealPath()) ?: [null, null];
        $path = $file->store(self::UPLOAD_DIR.'/'.$question->id, 'public');

        QuestionImage::create([
            'question_id'  => $question->id,
            'external_id'  => 'custom_q'.$question->id.'_'.$role.($label ? '_'.$label : '').'_'.$sort,
            'image_path'   => $path,
            'role'         => $role,
            'option_label' => $label,
            'caption'      => $caption,
            'width'        => $dims[0] ?: null,
            'height'       => $dims[1] ?: null,
            'sort_order'   => $sort,
        ]);
    }

    /**
     * Remove image ROWS from the current question, but NEVER unlink the files —
     * older immutable version snapshots reference them by path and must keep
     * rendering faithfully. (Orphaned files can be GC'd later against the snapshots.)
     */
    private function deleteImages(\Illuminate\Support\Collection $images): void
    {
        foreach ($images as $image) {
            $image->delete();
        }
    }

    /**
     * Record an immutable new version of the question's current content and advance
     * its current_version_id. Called inside the save transaction, AFTER options +
     * images are synced. If the edit was opened from a Quality Review
     * (?quality_review_id), the version is linked to it and the review records the
     * resulting version. Old image files are retained, so prior snapshots still render.
     */
    private function recordVersion(Question $question, Request $request): QuestionVersion
    {
        // Capture from a clean DB read so json/boolean casts round-trip as raw values.
        $fresh = Question::withTrashed()->with(['options', 'images'])->findOrFail($question->id);

        $next = (int) QuestionVersion::where('question_id', $question->id)->max('version_number') + 1;
        $reviewId = $request->integer('quality_review_id') ?: null;

        $version = QuestionVersion::create([
            'question_id'       => $question->id,
            'version_number'    => $next,
            'snapshot'          => QuestionSnapshot::capture($fresh),
            'correct_answer'    => $fresh->correct_answer,
            'change_summary'    => trim((string) $request->input('change_summary')) ?: ($next === 1 ? 'Created.' : 'Edited.'),
            'quality_review_id' => $reviewId,
            'created_by'        => auth('v2_super_admin')->id(),
        ]);

        DB::table('v2_questions')->where('id', $question->id)->update(['current_version_id' => $version->id]);

        if ($reviewId) {
            QualityReview::where('id', $reviewId)->update(['resulting_version_id' => $version->id]);
        }

        return $version;
    }

    private function customPaperFor(Subject $subject): Paper
    {
        $code = $subject->code ?: 'SUBJ'.$subject->id;

        return Paper::firstOrCreate(
            ['source_file' => "CUSTOM_{$code}.virtual"],
            [
                'subject_id'   => $subject->id,
                'source_paper' => "CUSTOM_{$code}",
                'subject_code' => $code,
                'paper_code'   => "{$code}/CUSTOM",
            ],
        );
    }
}
