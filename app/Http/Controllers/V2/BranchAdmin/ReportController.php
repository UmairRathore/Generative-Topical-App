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
| Branch Admin — student progress report
|--------------------------------------------------------------------------
| generate(): builds the report (stats + AI/fallback narrative) over a chosen
| duration and stores it as JSON in v2_reports. The PDF is NOT stored — it is
| rendered from the saved JSON on demand (saves space; the JSON stays readable
| and re-usable for any other operation). Branch-scoped.
*/
class ReportController extends BaseController
{
    public function generate(Student $student, Request $request, ReportService $reports)
    {
        abort_unless($student->branch_id === $this->branchId(), 403);

        [$since, $label, $key] = $this->window($request->input('duration', 'monthly'));

        $data = $reports->build($student, $since, $label);

        $report = Report::create([
            'school_id'    => $student->school_id,
            'branch_id'    => $this->branchId(),
            'student_id'   => $student->id,
            'generated_by' => $this->admin()->id,
            'period_key'   => $key,
            'period_label' => $label,
            'range_from'   => $key === 'all' ? null : $since,
            'range_to'     => now(),
            'overall_avg'  => $data['stats']['overall']['avg'] ?? null,
            'tests_count'  => $data['stats']['overall']['tests'] ?? 0,
            'source'       => $data['narrative']['source'] ?? 'template',
            'payload'      => $data,
        ]);

        return redirect()->route('v2.branch.student', $student)
            ->with('success', "Report generated for {$student->name} ({$label}).")
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

    /** Map a duration key to [since, label, key]. Default = monthly (last 31 days). */
    private function window(string $duration): array
    {
        return match ($duration) {
            'quarter' => [now()->subDays(92), 'the last 3 months', 'quarter'],
            'year'    => [now()->startOfYear(), now()->year.' to date', 'year'],
            'all'     => [Carbon::createFromTimestamp(0), 'all time', 'all'],
            default   => [now()->subDays(31), 'the last month', 'monthly'],
        };
    }
}
