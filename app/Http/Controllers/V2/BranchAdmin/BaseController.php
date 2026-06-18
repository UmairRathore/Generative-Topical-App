<?php

namespace App\Http\Controllers\V2\BranchAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\BranchAdmin;

class BaseController extends Controller
{
    protected function admin(): BranchAdmin
    {
        return auth('v2_branch_admin')->user();
    }

    protected function branch(): Branch
    {
        return $this->admin()->branch;
    }

    protected function branchId(): int
    {
        return $this->admin()->branch_id;
    }

    protected function schoolId(): int
    {
        return $this->admin()->school_id;
    }
}
