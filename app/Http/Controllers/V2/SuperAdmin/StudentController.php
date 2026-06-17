<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\School;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Student detail + individual graded paper (unscoped)
|--------------------------------------------------------------------------
| show() reuses ExamService::studentStats verbatim. paper() replicates the
| Teacher\ExamController::studentPaper data-prep so the shared answer_review
| partial renders one student's graded test (single / multiple / mixed alike).
*/
class StudentController extends Controller
{
    public function show(School $school, Student $student, ExamService $service): View
    {
        abort_unless($student->school_id === $school->id, 404);

        return view('v2.super_admin.students.show', [
            'school'  => $school,
            'student' => $student,
            'stats'   => $service->studentStats($student),
        ]);
    }

    public function paper(School $school, Exam $exam, Student $student, ExamService $service): View
    {
        abort_unless($exam->school_id === $school->id, 404);
        abort_unless($student->school_id === $school->id, 404);
        abort_unless(
            StudentEnrollment::where('class_id', $exam->class_id)
                ->where('student_id', $student->id)->exists(),
            404
        );

        $attempt = $exam->attempts()->where('student_id', $student->id)
            ->where('status', 'submitted')->firstOrFail();

        $exam->load(['examQuestions.question.options', 'examQuestions.question.images', 'topic', 'schoolClass']);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return view('v2.super_admin.students.paper', [
            'school'     => $school,
            'exam'       => $exam,
            'student'    => $student,
            'attempt'    => $attempt,
            'answers'    => $answers,
            'topicStats' => $service->topicStatsForAttempt($attempt),
        ]);
    }
}
