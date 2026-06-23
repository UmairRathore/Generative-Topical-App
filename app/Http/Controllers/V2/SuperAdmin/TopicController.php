<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Topics (unscoped)
|--------------------------------------------------------------------------
| Answer-weighted per-topic accuracy + a single-vs-mixed exam split. The school
| and platform views share one template (v2.super_admin.topics.show); $school
| is null for the platform-wide rollup.
*/
class TopicController extends Controller
{
    /** Platform-wide topic rollup across all schools. */
    public function platform(ExamService $service): View
    {
        return view('v2.super_admin.topics.show', [
            'school'      => null,
            'topicGroups' => $service->topicWideStatsBySubject(null), // platform view: one block per subject
            'split'       => $service->examKindSplit(null),
        ]);
    }
}
