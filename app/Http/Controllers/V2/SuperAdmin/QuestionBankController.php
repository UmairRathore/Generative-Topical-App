<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Super Admin — Question Bank
|--------------------------------------------------------------------------
| Read-only browse of the entire global question pool with filters
| (level/grade, subject, year, session, variant, topic, search).
| Super Admin sees ALL questions — there is no school scoping on the bank.
*/
class QuestionBankController extends Controller
{
    private const SESSION_LABELS = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];

    public function index(Request $request)
    {
        $filters = [
            'level'   => $request->string('level')->toString(),
            'subject' => $request->integer('subject') ?: null,
            'year'    => $request->integer('year') ?: null,
            'session' => $request->string('session')->toString(),
            'variant' => $request->string('variant')->toString(),
            'topic'   => $request->integer('topic') ?: null,
            'answer'  => $request->string('answer')->toString(), // '', answered, unanswered
            'q'       => trim($request->string('q')->toString()),
        ];

        $query = Question::query()
            ->with(['paper:id,source_paper,year,session_code,variant', 'subject:id,name,code', 'topic:id,external_id,title'])
            ->withCount(['options', 'images'])
            ->when($filters['subject'], fn ($q, $v) => $q->where('subject_id', $v))
            ->when($filters['year'], fn ($q, $v) => $q->where('year', $v))
            ->when($filters['topic'], fn ($q, $v) => $q->where('topic_id', $v))
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

        $questions = $query->paginate(25)->withQueryString();

        // Filter option lists (only values that actually exist in the bank).
        $subjectIds = Question::query()->distinct()->pluck('subject_id');

        return view('v2.super_admin.question_bank.index', [
            'questions' => $questions,
            'filters'   => $filters,
            'subjects'  => Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code', 'level']),
            'levels'    => Subject::whereIn('id', $subjectIds)->whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'years'     => Question::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'topics'    => Topic::query()
                ->when($filters['subject'], fn ($q, $v) => $q->where('subject_id', $v))
                ->orderBy('sort_order')->get(['id', 'external_id', 'title']),
            'sessions'  => self::SESSION_LABELS,
            'variants'  => ['11', '12', '13', '14'],
            'stats'     => [
                'total'   => Question::count(),
                'tagged'  => Question::whereNotNull('topic_id')->count(),
                'papers'  => \App\Models\V2\Paper::count(),
                'matched' => $questions->total(),
            ],
        ]);
    }
}
