<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Services\V2\ExamService;

class DashboardController extends Controller
{
    public function index(ExamService $service)
    {
        $student = auth('v2_student')->user();

        return view('v2.student.dashboard.index', [
            'stats' => $service->studentStats($student),
        ]);
    }
}
