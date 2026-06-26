<?php

namespace App\Http\Controllers\V2\BranchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V2\Concerns\ReviewsEscalations;

/*
| Read-only "Reported Questions" audit scoped to this branch only.
| Admins are not in the approval chain - there are no actions here.
*/
class FlaggedQuestionController extends Controller
{
    use ReviewsEscalations;

    public function index()
    {
        $admin = auth('v2_branch_admin')->user();

        return view('v2.admin.flagged_questions', [
            'layout'     => 'v2.layouts.branch_admin',
            'reports'    => $this->reportedQuestions(null, $admin->branch_id),
            'showBranch' => false,
            'scopeLabel' => 'branch',
        ]);
    }
}
