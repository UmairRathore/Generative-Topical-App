<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\Grade;
use App\Services\V2\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends BaseController
{
    public function index(): View
    {
        $grades = Grade::all();
        return view('v2.school_admin.grades.index', compact('grades'));
    }

    public function create(): View
    {
        return view('v2.school_admin.grades.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'required|integer|min:0',
        ]);

        $data['school_id'] = $this->schoolId();
        $data['is_active']  = true;

        $grade = Grade::create($data);

        AuditLogger::record('grade.created', $grade, ['name' => $grade->name], null);

        return redirect()->route('v2.school.grades.index')
            ->with('success', "Grade \"{$grade->name}\" created.");
    }

    public function edit(Grade $grade): View
    {
        $this->authorizeGrade($grade);
        return view('v2.school_admin.grades.edit', compact('grade'));
    }

    public function update(Request $request, Grade $grade): RedirectResponse
    {
        $this->authorizeGrade($grade);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'short_name' => 'required|string|max:20',
            'sort_order' => 'required|integer|min:0',
        ]);

        $grade->update($data);

        AuditLogger::record('grade.updated', $grade, $data, null);

        return redirect()->route('v2.school.grades.index')
            ->with('success', "Grade \"{$grade->name}\" updated.");
    }

    public function toggle(Grade $grade): RedirectResponse
    {
        $this->authorizeGrade($grade);

        $grade->update(['is_active' => ! $grade->is_active]);

        AuditLogger::record(
            $grade->is_active ? 'grade.activated' : 'grade.deactivated',
            $grade,
            null,
            null
        );

        return redirect()->route('v2.school.grades.index')
            ->with('success', "Grade \"{$grade->name}\" " . ($grade->is_active ? 'activated' : 'deactivated') . '.');
    }

    private function authorizeGrade(Grade $grade): void
    {
        abort_if($grade->school_id !== $this->schoolId(), 403);
    }
}
