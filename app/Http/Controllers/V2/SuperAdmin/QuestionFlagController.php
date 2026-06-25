<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Services\V2\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| Super Admin: Question Flags
|--------------------------------------------------------------------------
| The triage queue for teacher-submitted "this question is wrong" reports.
| Flags are grouped by question. To fix a question, edit it in the Question
| Bank and set its status back to `active` (the existing status control). Once
| handled, close the queue item here ("Mark fixed" or "Dismiss").
*/
class QuestionFlagController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'open';
        $status = in_array($status, ['open', 'resolved', 'dismissed'], true) ? $status : 'open';

        // Only teacher-level flags reach Super Admin — student reports are handled by
        // their teacher; an escalated one only arrives here once an admin approves it
        // (status becomes 'open'). Student-level rows never surface in this queue.
        $flags = QuestionFlag::teacherLevel()->where('status', $status)
            ->with([
                'teacher:id,name',
                'school:id,name',
                'question' => fn ($q) => $q->with([
                    'subject:id,name,code,level',
                    'topic:id,external_id,title',
                    'options',
                    'images',
                ]),
            ])
            ->latest()
            ->get();

        $groups = $flags
            ->filter(fn ($f) => $f->question !== null)
            ->groupBy('question_id')
            ->values();

        return view('v2.super_admin.question_flags.index', [
            'groups' => $groups,
            'status' => $status,
            'counts' => [
                'open'      => QuestionFlag::where('status', 'open')->count(),
                'resolved'  => QuestionFlag::where('status', 'resolved')->count(),
                'dismissed' => QuestionFlag::where('status', 'dismissed')->count(),
            ],
        ]);
    }

    /** Close every open flag on a question — fixed (handled) or dismissed (not a real problem). */
    public function resolve(Request $request, Question $question)
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['resolved', 'dismissed'])],
        ]);

        $admin = auth('v2_super_admin')->user();

        $closed = QuestionFlag::teacherLevel()
            ->where('question_id', $question->id)
            ->where('status', 'open')
            ->update([
                'status'      => $data['outcome'],
                'resolved_by' => $admin?->id,
                'resolved_at' => now(),
            ]);

        AuditLogger::record('question.flag_'.$data['outcome'], $question, ['flags_closed' => $closed]);

        $word = $data['outcome'] === 'resolved' ? 'marked fixed' : 'dismissed';

        return back()->with('success', "Flag {$word}. Remember to set the question back to “active” in the Question Bank once it's corrected.");
    }
}
