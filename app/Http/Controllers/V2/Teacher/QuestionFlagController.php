<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Services\V2\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class QuestionFlagController extends Controller
{
    /** Teacher-uploaded flag screenshots live here (public disk). */
    private const UPLOAD_DIR = 'v2/question-flags';

    private function teacher()
    {
        return auth('v2_teacher')->user();
    }

    /**
     * Flag a question as wrong/broken. Pulls it from the live pool immediately
     * (status -> under_review) and queues it for super-admin review. A teacher
     * may only flag questions in a subject they teach.
     */
    public function store(Request $request, Question $question)
    {
        $teacher = $this->teacher();

        $subjectIds = $teacher->classes()->pluck('v2_classes.subject_id')->unique();
        abort_unless($subjectIds->contains($question->subject_id), 403);

        $data = $request->validate([
            'reason'     => ['required', Rule::in(array_keys(QuestionFlag::REASONS))],
            'note'       => ['nullable', 'string', 'max:1000'],
            'screenshot' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
        ]);

        $path = $request->hasFile('screenshot')
            ? $request->file('screenshot')->store(self::UPLOAD_DIR.'/'.$question->id, 'public')
            : null;

        // One open flag per (question, teacher) — re-flagging refreshes it, keeping
        // any newly attached screenshot rather than spawning duplicate queue rows.
        QuestionFlag::updateOrCreate(
            [
                'question_id'           => $question->id,
                'flagged_by_teacher_id' => $teacher->id,
                'status'                => 'open',
            ],
            [
                'school_id'       => $teacher->school_id,
                'reason'          => $data['reason'],
                'note'            => $data['note'] ?? null,
                'screenshot_path' => $path ?: null,
            ],
        );

        // Auto-hide: only demote a currently-live question. Never override a
        // draft/archived state an admin may have set deliberately.
        if ($question->status === 'active') {
            $question->update(['status' => 'under_review']);
        }

        AuditLogger::record('question.flagged', $question, [
            'reason'     => $data['reason'],
            'has_note'   => filled($data['note'] ?? null),
            'has_image'  => (bool) $path,
        ]);

        $message = 'Thanks — reported and pulled from the question pool for review.';

        // AJAX (exam preview / question gallery): respond with JSON so the page
        // keeps its state instead of reloading and losing the drawn questions.
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'question_id' => $question->id]);
        }

        return back()->with('success', $message);
    }
}
