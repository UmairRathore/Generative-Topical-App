<?php

namespace App\Services\V2;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Platform analytics (Super Admin)
|--------------------------------------------------------------------------
| Cross-school business + engagement metrics. All raw DB::table so no
| per-school global scope fires — Super Admin sees the whole platform.
| MRR/growth are reconstructed from row timestamps (there is no historical
| billing ledger), so they are best-effort trends, not an accounting source.
*/
class PlatformStatsService
{
    /** First-of-month Carbon points for the last $months (oldest first). */
    private function monthSeries(int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        return array_map(fn ($i) => (clone $start)->addMonths($i), range(0, $months - 1));
    }

    /**
     * Cumulative MRR by month. Approximation: a school contributes its
     * monthly_fee from the month it was activated (or created) onward, while
     * it is active or suspended. No cancellation ledger, so cancelled schools
     * are simply excluded.
     */
    public function mrrTrend(int $months = 12): array
    {
        $rows = DB::table('v2_schools')
            ->whereIn('status', ['active', 'suspended'])
            ->selectRaw('COALESCE(activated_at, created_at) as start_at, monthly_fee')
            ->get();

        return array_map(function ($m) use ($rows) {
            $end = (clone $m)->endOfMonth();
            $mrr = 0;
            foreach ($rows as $r) {
                if ($r->start_at && Carbon::parse($r->start_at) <= $end) {
                    $mrr += (int) $r->monthly_fee;
                }
            }

            return ['label' => $m->format('M Y'), 'mrr' => $mrr];
        }, $this->monthSeries($months));
    }

    /** New rows added per month for the headline entities (by created_at). */
    public function growth(int $months = 12): array
    {
        $series = $this->monthSeries($months);

        $perMonth = function (string $table) use ($series) {
            $counts = DB::table($table)
                ->where('created_at', '>=', $series[0])
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') ym, count(*) c")
                ->groupBy('ym')->pluck('c', 'ym');

            return array_map(fn ($m) => (int) ($counts[$m->format('Y-m')] ?? 0), $series);
        };

        return [
            'labels'   => array_map(fn ($m) => $m->format('M'), $series),
            'schools'  => $perMonth('v2_schools'),
            'teachers' => $perMonth('v2_teachers'),
            'students' => $perMonth('v2_students'),
            'exams'    => $perMonth('v2_exams'),
        ];
    }

    /** Submitted attempts per day for the last $days (a proxy for daily activity). */
    public function dailyActivity(int $days = 30): array
    {
        $from = now()->startOfDay()->subDays($days - 1);

        $counts = DB::table('v2_exam_attempts')
            ->where('status', 'submitted')->where('submitted_at', '>=', $from)
            ->selectRaw('DATE(submitted_at) d, count(*) c')
            ->groupBy('d')->pluck('c', 'd');

        return array_map(function ($i) use ($from, $counts) {
            $day = (clone $from)->addDays($i);

            return [
                'label'   => $day->format('d M'),
                'count'   => (int) ($counts[$day->format('Y-m-d')] ?? 0),
                'weekend' => $day->isWeekend(),
            ];
        }, range(0, $days - 1));
    }

    /** Per active-school engagement this month + a simple churn-risk band. */
    public function schoolEngagement(): array
    {
        $month = fn (string $table) => DB::table($table)
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)
            ->selectRaw('school_id, count(*) c')->groupBy('school_id')->pluck('c', 'school_id');

        $exams    = $month('v2_exams');
        $reports  = $month('v2_reports');
        $lastExam = DB::table('v2_exams')->selectRaw('school_id, max(created_at) m')
            ->groupBy('school_id')->pluck('m', 'school_id');

        return DB::table('v2_schools')->where('status', 'active')
            ->orderByDesc('monthly_fee')->get(['id', 'name', 'monthly_fee'])
            ->map(function ($s) use ($exams, $reports, $lastExam) {
                $e = (int) ($exams[$s->id] ?? 0);
                $r = (int) ($reports[$s->id] ?? 0);

                return [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'fee'       => (int) $s->monthly_fee,
                    'exams'     => $e,
                    'reports'   => $r,
                    'last_exam' => isset($lastExam[$s->id]) ? Carbon::parse($lastExam[$s->id]) : null,
                    'risk'      => $e === 0 ? 'high' : (($e < 5 && $r < 10) ? 'medium' : 'low'),
                ];
            })->all();
    }

    /** Lowest correct-rate questions platform-wide (with enough attempts to matter). */
    public function hardestQuestions(int $limit = 8, int $minAttempts = 5): array
    {
        return DB::table('v2_exam_answers as a')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->leftJoin('v2_subjects as s', 's.id', '=', 'q.subject_id')
            // Exclude voided exam-questions so a voided item can't pollute the "hardest" list.
            ->join('v2_exam_attempts as veat', 'veat.id', '=', 'a.attempt_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'veat.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->groupBy('q.id', 'q.source_paper', 'q.question_number', 't.title', 's.name')
            ->havingRaw('count(*) >= ?', [$minAttempts])
            ->orderByRaw('avg(a.is_correct) asc')
            ->limit($limit)
            ->selectRaw('q.id, q.source_paper, q.question_number, t.title as topic, s.name as subject, count(*) as attempts, avg(a.is_correct) * 100 as correct_rate')
            ->get()
            ->map(fn ($r) => [
                'source'   => $r->source_paper,
                'qno'      => $r->question_number,
                'topic'    => $r->topic ?? 'Untagged',
                'subject'  => $r->subject,
                'attempts' => (int) $r->attempts,
                'correct'  => (int) round($r->correct_rate),
            ])->all();
    }
}
