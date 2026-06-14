<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\AuditLog;
use App\Models\V2\Grade;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\Teacher;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $school = $this->school();

        $stats = [
            'teachers' => Teacher::count(),
            'students' => Student::count(),
            'classes'  => SchoolClass::count(),
            'grades'   => Grade::count(),
        ];

        $recentLogs = AuditLog::where('actor_type', 'SchoolAdmin')
            ->where('actor_id', $this->admin()->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('v2.school_admin.dashboard.index', compact('school', 'stats', 'recentLogs'));
    }
}
