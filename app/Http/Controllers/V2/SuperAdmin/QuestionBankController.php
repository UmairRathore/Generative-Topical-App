<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Paper;
use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| Super Admin — Question Bank
|--------------------------------------------------------------------------
| Browse + full CRUD over the entire global question pool. Super Admin sees
| ALL questions — there is no school scoping on the bank. Manually authored
| questions are attached to a per-subject "Custom" paper so the paper_id FK
| and the [paper_id, question_number] unique constraint stay satisfied.
|
| Each question carries a status (active | draft | archived); only `active`
| questions are ever drawn into a generated test (see ExamService::generate).
*/
class QuestionBankController extends Controller
{
    private const SESSION_LABELS = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];

    private const STATUSES = ['active', 'draft', 'archived'];

    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    private const OPTION_LABELS = ['A', 'B', 'C', 'D'];

    public function index(Request $request)
    {
        $filters = [
            'level'   => $request->string('level')->toString(),
            'subject' => $request->integer('subject') ?: null,
            'year'    => $request->integer('year') ?: null,
            'session' => $request->string('session')->toString(),
            'variant' => $request->string('variant')->toString(),
            'topic'   => $request->integer('topic') ?: null,
            'layout'  => $request->string('layout')->toString(),  // question type
            'answer'  => $request->string('answer')->toString(),  // '', answered, unanswered
            'status'  => $request->string('status')->toString(),  // '', active, draft, archived
            'q'       => trim($request->string('q')->toString()),
        ];

        // 'gallery' renders each question's full visual (for visual QA); 'table' is the compact list.
        $view = $request->string('view')->toString() === 'gallery' ? 'gallery' : 'table';

        $query = Question::query()
            ->with(['paper:id,source_paper,year,session_code,variant', 'subject:id,name,code', 'topic:id,external_id,title'])
            ->withCount(['options', 'images'])
            // Gallery needs the actual options + images to render the question.
            ->when($view === 'gallery', fn ($q) => $q->with(['options', 'images']))
            ->when($filters['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($filters['year'], fn ($q, $v) => $q->where('year', $v))
            ->when($filters['topic'], fn ($q, $v) => $q->where('topic_id', $v))
            ->when($filters['layout'], fn ($q, $v) => $q->where('layout_type', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['level'], fn ($q, $v) => $q->whereHas('subject', fn ($s) => $s->where('level', $v)))
            ->when($filters['session'], fn ($q, $v) => $q->whereHas('paper', fn ($p) => $p->where('session_code', $v)))
            ->when($filters['variant'], fn ($q, $v) => $q->whereHas('paper', fn ($p) => $p->where('variant', $v)))
            ->when($filters['answer'] === 'answered', fn ($q) => $q->whereNotNull('correct_answer'))
            ->when($filters['answer'] === 'unanswered', fn ($q) => $q->whereNull('correct_answer'))
            ->when($filters['q'], function ($q, $v) {
                $q->where(fn ($w) => $w
                    ->where('question_text', 'like', "%{$v}%")
                    ->orWhere('source_paper', 'like', "%{$v}%"));
            })
            ->orderBy('subject_id')
            ->orderByDesc('year')
            ->orderBy('source_paper')
            ->orderBy('question_number');

        // Gallery cards are heavier (images), so page in smaller chunks.
        $questions = $query->paginate($view === 'gallery' ? 12 : 25)->withQueryString();

        // Filter option lists (only values that actually exist in the bank).
        $subjectIds = Question::query()->distinct()->pluck('subject_id');

        return view('v2.super_admin.question_bank.index', [
            'questions' => $questions,
            'filters'   => $filters,
            'view'      => $view,
            'subjects'  => Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code', 'level']),
            'levels'    => Subject::whereIn('id', $subjectIds)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'years'     => Question::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'topics'    => Topic::query()
                ->when($filters['subject'], fn ($q, $v) => $q->where('subject_id', $v))
                ->orderBy('sort_order')->get(['id', 'external_id', 'title']),
            'layouts'   => Question::query()->distinct()->orderBy('layout_type')->pluck('layout_type')->filter()->values(),
            'sessions'  => self::SESSION_LABELS,
            'variants'  => ['11', '12', '13', '14'],
            'statuses'  => self::STATUSES,
            'stats'     => [
                'total'   => Question::count(),
                'tagged'  => Question::whereNotNull('topic_id')->count(),
                'papers'  => Paper::count(),
                'matched' => $questions->total(),
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
        $data = $this->validateQuestion($request);

        $subject = Subject::findOrFail($data['subject_id']);
        $paper = $this->customPaperFor($subject);

        DB::transaction(function () use ($data, $subject, $paper) {
            $question = Question::create([
                'paper_id'        => $paper->id,
                'subject_id'      => $subject->id,
                'topic_id'        => $data['topic_id'] ?? null,
                'year'            => $data['year'] ?? null,
                'question_number' => (int) Question::where('paper_id', $paper->id)->max('question_number') + 1,
                'question_text'   => $data['question_text'],
                'layout_type'     => 'text_only',
                'correct_answer'  => $data['correct_answer'],
                'marks'           => $data['marks'],
                'difficulty'      => $data['difficulty'] ?? null,
                'status'          => $data['status'],
                'source_paper'    => $paper->source_paper,
            ]);

            $this->syncOptions($question, $data['options']);
        });

        return redirect()
            ->route('v2.super_admin.question_bank.index', ['subject' => $subject->id, 'status' => $data['status']])
            ->with('ok', 'Question created.');
    }

    public function edit(Question $question)
    {
        $question->load('options');

        return view('v2.super_admin.question_bank.form', $this->formData($question));
    }

    public function update(Request $request, Question $question)
    {
        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($data, $question) {
            $question->update([
                'subject_id'     => $data['subject_id'],
                'topic_id'       => $data['topic_id'] ?? null,
                'year'           => $data['year'] ?? null,
                'question_text'  => $data['question_text'],
                'correct_answer' => $data['correct_answer'],
                'marks'          => $data['marks'],
                'difficulty'     => $data['difficulty'] ?? null,
                'status'         => $data['status'],
            ]);

            $this->syncOptions($question, $data['options']);
        });

        return redirect()
            ->route('v2.super_admin.question_bank.index')
            ->with('ok', 'Question updated.');
    }

    public function destroy(Question $question)
    {
        DB::transaction(function () use ($question) {
            $question->options()->delete();
            $question->images()->delete();
            $question->delete();
        });

        return redirect()
            ->route('v2.super_admin.question_bank.index')
            ->with('ok', 'Question deleted.');
    }

    /** One-click status change from the listing (active / draft / archived). */
    public function setStatus(Request $request, Question $question)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $question->update(['status' => $validated['status']]);

        return back()->with('ok', "Question marked {$validated['status']}.");
    }

    /* ---------------------------------------------------------------------- */

    /** Shared view data for the create/edit form. */
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
        ];
    }

    private function validateQuestion(Request $request): array
    {
        $rules = [
            'subject_id'     => ['required', Rule::exists('v2_subjects', 'id')],
            'topic_id'       => ['nullable', Rule::exists('v2_topics', 'id')],
            'question_text'  => ['required', 'string', 'max:5000'],
            'correct_answer' => ['required', Rule::in(self::OPTION_LABELS)],
            'marks'          => ['required', 'integer', 'min:1', 'max:20'],
            'difficulty'     => ['nullable', Rule::in(self::DIFFICULTIES)],
            'year'           => ['nullable', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'status'         => ['required', Rule::in(self::STATUSES)],
            'options'        => ['required', 'array'],
        ];
        foreach (self::OPTION_LABELS as $label) {
            $rules["options.$label"] = ['required', 'string', 'max:1000'];
        }

        return $request->validate($rules);
    }

    /** Replace a question's options with the submitted A-D set. */
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
     * The synthetic paper that hosts manually-authored questions for a subject.
     * Keyed on the unique source_file so each subject gets exactly one.
     */
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
