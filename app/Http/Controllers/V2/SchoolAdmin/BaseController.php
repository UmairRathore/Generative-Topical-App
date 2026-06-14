<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\School;
use App\Models\V2\SchoolAdmin;

class BaseController extends Controller
{
    protected function admin(): SchoolAdmin
    {
        return auth('v2_school_admin')->user();
    }

    protected function school(): School
    {
        return $this->admin()->school;
    }

    protected function schoolId(): int
    {
        return $this->admin()->school_id;
    }
}
