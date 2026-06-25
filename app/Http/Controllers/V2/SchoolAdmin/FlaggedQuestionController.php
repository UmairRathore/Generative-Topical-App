<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V2\Concerns\ReviewsEscalations;

/*
| Read-only "Reported Questions" audit across the whole school (all branches).
| Admins are not in the approval chain — there are no actions here.
*/
class FlaggedQuestionController extends Controller
{
    use ReviewsEscalations;

    public function index()
    {
        $admin = auth('v2_school_admin')->user();

        return view('v2.admin.flagged_questions', [
            'layout'     => 'v2.layouts.school_admin',
            'reports'    => $this->reportedQuestions($admin->school_id, null),
            'showBranch' => true,
            'scopeLabel' => 'school',
        ]);
    }
}
