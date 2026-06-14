<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\Teacher;
use App\Services\V2\AuditLogger;
use App\Services\V2\TempPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherController extends BaseController
{
    public function __construct(private TempPasswordGenerator $pwGen) {}

    public function index(): View
    {
        $teachers = Teacher::orderBy('name')->paginate(25);
        $school   = $this->school();
        return view('v2.school_admin.teachers.index', compact('teachers', 'school'));
    }

    public function create(): View
    {
        return view('v2.school_admin.teachers.create');
    }

    public function store(Request $request): mixed
    {
        $this->checkTeacherLimit();

        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'email'       => 'required|email|unique:v2_teachers,email',
            'phone'       => 'nullable|string|max:30',
            'employee_id' => 'nullable|string|max:50',
        ]);

        $plainPassword = $this->pwGen->generate();
        $data['school_id']            = $this->schoolId();
        $data['password']             = bcrypt($plainPassword);
        $data['must_change_password'] = true;
        $data['status']               = 'active';
        $data['created_by']           = $this->admin()->id;

        $teacher = Teacher::create($data);

        AuditLogger::record('teacher.created', $teacher, ['email' => $teacher->email], null);

        return view('v2.school_admin.teachers.credentials', [
            'teacher'        => $teacher,
            'plainPassword'  => $plainPassword,
        ]);
    }

    public function bulkCreate(): View
    {
        return view('v2.school_admin.teachers.bulk_create');
    }

    public function bulkStore(Request $request): mixed
    {
        $request->validate([
            'teachers'              => 'required|array|min:1|max:50',
            'teachers.*.name'       => 'required|string|max:200',
            'teachers.*.email'      => 'required|email',
            'teachers.*.employee_id'=> 'nullable|string|max:50',
        ]);

        $school    = $this->school();
        $remaining = $school->max_teachers - Teacher::count();

        if (count($request->teachers) > $remaining) {
            return back()->withErrors(['teachers' => "License allows only {$remaining} more teachers."]);
        }

        $created = [];

        DB::transaction(function () use ($request, &$created) {
            foreach ($request->teachers as $row) {
                $plain   = $this->pwGen->generate();
                $teacher = Teacher::create([
                    'school_id'            => $this->schoolId(),
                    'name'                 => $row['name'],
                    'email'                => $row['email'],
                    'employee_id'          => $row['employee_id'] ?? null,
                    'password'             => bcrypt($plain),
                    'must_change_password' => true,
                    'status'               => 'active',
                    'created_by'           => $this->admin()->id,
                ]);

                AuditLogger::record('teacher.created', $teacher, ['email' => $teacher->email], null);

                $created[] = ['teacher' => $teacher, 'password' => $plain];
            }
        });

        return view('v2.school_admin.teachers.credentials', [
            'bulk'    => $created,
            'teacher' => null,
        ]);
    }

    public function show(Teacher $teacher): View
    {
        $this->authorizeTeacher($teacher);
        $teacher->load('classes.grade', 'classes.subject');
        return view('v2.school_admin.teachers.show', compact('teacher'));
    }

    public function edit(Teacher $teacher): View
    {
        $this->authorizeTeacher($teacher);
        return view('v2.school_admin.teachers.edit', compact('teacher'));
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorizeTeacher($teacher);

        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'email'       => "required|email|unique:v2_teachers,email,{$teacher->id}",
            'phone'       => 'nullable|string|max:30',
            'employee_id' => 'nullable|string|max:50',
        ]);

        $teacher->update($data);

        AuditLogger::record('teacher.updated', $teacher, $data, null);

        return redirect()->route('v2.school.teachers.show', $teacher)
            ->with('success', 'Teacher updated.');
    }

    public function toggle(Teacher $teacher): RedirectResponse
    {
        $this->authorizeTeacher($teacher);

        $newStatus = $teacher->status === 'active' ? 'inactive' : 'active';
        $teacher->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            $teacher->increment('session_version');
        }

        AuditLogger::record("teacher.{$newStatus}", $teacher, null, null);

        return redirect()->route('v2.school.teachers.index')
            ->with('success', "Teacher {$newStatus}.");
    }

    public function resetPassword(Teacher $teacher): mixed
    {
        $this->authorizeTeacher($teacher);

        $plain = $this->pwGen->generate();
        $teacher->update([
            'password'             => bcrypt($plain),
            'must_change_password' => true,
        ]);
        $teacher->increment('session_version');

        AuditLogger::record('teacher.password_reset', $teacher, null, null);

        return view('v2.school_admin.teachers.credentials', [
            'teacher'       => $teacher,
            'plainPassword' => $plain,
            'isReset'       => true,
        ]);
    }

    private function checkTeacherLimit(): void
    {
        $school = $this->school();
        $count  = Teacher::count();
        abort_if($count >= $school->max_teachers, 422, "Teacher license limit ({$school->max_teachers}) reached.");
    }

    private function authorizeTeacher(Teacher $teacher): void
    {
        abort_if($teacher->school_id !== $this->schoolId(), 403);
    }
}
