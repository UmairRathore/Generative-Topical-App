<?php

namespace App\Http\Controllers\V2\BranchAdmin;

use App\Models\V2\Report;
use App\Models\V2\Student;
use App\Services\V2\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Branch Admin - student progress report
|--------------------------------------------------------------------------
| generate(): builds the report (stats + AI/fallback narrative) over a chosen
| duration and stores it as JSON in v2_reports. The PDF is NOT stored - it is
| rendered from the saved JSON on demand (saves space; the JSON stays readable
| and re-usable for any other operation). Branch-scoped.
*/
class ReportController extends BaseController
{
    public function generate(Student $student, Request $request, ReportService $reports)
    {
        abort_unless($student->branch_id === $this->branchId(), 403);

        [$since, $until, $label, $key] = $this->window($request);

        $data = $reports->build($student, $since, $label, $until);

        $tests = $data['stats']['overall']['tests'] ?? 0;
        $avg = $data['stats']['overall']['avg'] ?? null;

        $report = Report::create([
            'school_id'    => $student->school_id,
            'branch_id'    => $this->branchId(),
            'student_id'   => $student->id,
            'generated_by' => $this->admin()->id,
            'period_key'   => $key,
            'period_label' => $label,
            'range_from'   => $key === 'all' ? null : $since,
            'range_to'     => $until,
            'overall_avg'  => $avg,
            'tests_count'  => $tests,
            'source'       => $data['narrative']['source'] ?? 'template',
            'payload'      => $data,
        ]);

        // No data in the window is not an error - the report is still saved, but say so plainly.
        if ($tests === 0) {
            return redirect()->route('v2.branch.student', $student)
                ->with('info', "No completed tests for {$student->name} in {$label} - an empty report was saved.")
                ->with('report_ready', $report->id);
        }

        return redirect()->route('v2.branch.student', $student)
            ->with('success', "Report generated for {$student->name} - {$label}: {$tests} tests, {$avg}% average.")
            ->with('report_ready', $report->id);
    }

    /** Render the PDF from a stored report's JSON payload (nothing is cached/stored). */
    public function pdf(Report $report)
    {
        abort_unless($report->branch_id === $this->branchId(), 403);

        $student = $report->student;

        $pdf = Pdf::loadView('v2.branch_admin.report.pdf', [
            'student'     => $student,
            'branch'      => $report->branch,
            'school'      => $report->school,
            'period'      => $report->period_label,
            'stats'       => $report->payload['stats'],
            'narrative'   => $report->payload['narrative'],
            'generatedAt' => $report->created_at,
        ])->setPaper('a4');

        return $pdf->download('report-'.Str::slug($student->name).'-'.$report->created_at->format('Y-m-d').'.pdf');
    }

    /**
     * Resolve the report window into [since, until, label, key]. Supports preset
     * rolling windows, all-time, and a custom from–to range (validated).
     */
    private function window(Request $request): array
    {
        $duration = (string) $request->input('duration', 'month');

        if ($duration === 'custom') {
            $v = $request->validate([
                'from' => ['required', 'date', 'before_or_equal:today'],
                'to'   => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            ]);
            $from = Carbon::parse($v['from'])->startOfDay();
            $to   = Carbon::parse($v['to'])->endOfDay();

            return [$from, $to, $from->format('j M Y').' – '.$to->format('j M Y'), 'custom'];
        }

        [$days, $label] = match ($duration) {
            'week'     => [7,   'the last week'],
            '2weeks'   => [14,  'the last 2 weeks'],
            '3weeks'   => [21,  'the last 3 weeks'],
            '2months'  => [61,  'the last 2 months'],
            '3months'  => [92,  'the last 3 months'],
            'quarter'  => [120, 'the last quarter'],
            '6months'  => [183, 'the last 6 months'],
            '10months' => [305, 'the last 10 months'],
            default    => [31,  'the last month'], // 'month'
        };

        return [now()->subDays($days)->startOfDay(), now(), $label, $duration];
    }
}
