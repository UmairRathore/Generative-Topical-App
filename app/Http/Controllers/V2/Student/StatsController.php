<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Services\V2\ExamService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Student — Performance / stats
|--------------------------------------------------------------------------
| The signed-in student's own history: score trend, per-topic mastery,
| subject split, monthly activity and an exam log. Read-only, self-scoped.
*/
class StatsController extends Controller
{
    public function index(ExamService $service): View
    {
        $student = auth('v2_student')->user();

        $stats       = $service->studentStats($student);
        $topics      = collect($service->studentTopicStats($student->id))
            ->sortByDesc('percent')->values()->all();
        $trend       = $service->studentScoreTrend($student->id, 10);
        $monthly     = $service->studentMonthlyActivity($student->id);
        $improvement = $service->studentImprovement($student->id);

        // Subject split (doughnut) + best subject.
        $subjects   = collect($stats['subjects']);
        $bestSubject = $subjects->sortByDesc('avg')->first();

        // Flatten every test across subjects into one log, newest first, with a
        // per-row trend arrow vs the chronologically-previous attempt.
        $ordered = $subjects
            ->flatMap(fn ($s) => collect($s['tests'])->map(fn ($t) => $t + ['subject' => $s['subject']]))
            ->sortByDesc('date')->values();

        $history = $ordered->map(function ($t, $i) use ($ordered) {
            $older = $ordered[$i + 1] ?? null;

            return $t + ['trend' => $older ? ($t['percent'] <=> $older['percent']) : 0];
        });

        return view('v2.student.stats.index', [
            'student'     => $student,
            'overall'     => $stats['overall'],
            'subjects'    => $subjects,
            'topics'      => $topics,
            'trend'       => $trend,
            'monthly'     => $monthly,
            'improvement' => $improvement,
            'bestSubject' => $bestSubject,
            'history'     => $history,
        ]);
    }
}
