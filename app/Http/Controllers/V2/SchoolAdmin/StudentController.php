<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Services\V2\AuditLogger;
use App\Services\V2\TempPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends BaseController
{
    public function __construct(private TempPasswordGenerator $pwGen) {}

    public function index(): View
    {
        $students = Student::orderBy('name')->paginate(25);
        $school   = $this->school();
        return view('v2.school_admin.students.index', compact('students', 'school'));
    }

    public function create(): View
    {
        $classes = SchoolClass::with(['grade', 'subject'])->active()->get();
        return view('v2.school_admin.students.create', compact('classes'));
    }

    public function store(Request $request): mixed
    {
        $this->checkStudentLimit();

        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'email'       => 'required|email|unique:v2_students,email',
            'roll_number' => 'nullable|string|max:50',
            'class_id'    => 'nullable|exists:v2_classes,id',
        ]);

        $plain = $this->pwGen->generate();
        $student = Student::create([
            'school_id'            => $this->schoolId(),
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'roll_number'          => $data['roll_number'] ?? null,
            'password'             => bcrypt($plain),
            'must_change_password' => true,
            'status'               => 'active',
            'created_by'           => $this->admin()->id,
        ]);

        if (! empty($data['class_id'])) {
            $class = SchoolClass::findOrFail($data['class_id']);
            abort_if($class->school_id !== $this->schoolId(), 403);

            StudentEnrollment::create([
                'student_id' => $student->id,
                'class_id'   => $class->id,
                'school_id'  => $this->schoolId(),
                'status'     => 'active',
            ]);
        }

        AuditLogger::record('student.created', $student, ['email' => $student->email], null);

        return view('v2.school_admin.students.credentials', [
            'student'       => $student,
            'plainPassword' => $plain,
        ]);
    }

    public function bulkStore(Request $request): mixed
    {
        $request->validate([
            'students'              => 'required|array|min:1|max:100',
            'students.*.name'       => 'required|string|max:200',
            // Reject in-payload duplicates AND emails already taken, so a bad CSV
            // fails validation with a friendly error instead of blowing up the
            // create transaction (or silently creating accounts on a shared email).
            'students.*.email'      => 'required|email|distinct:ignore_case|unique:v2_students,email',
            'students.*.roll_number'=> 'nullable|string|max:50',
        ]);

        $school    = $this->school();
        $remaining = $school->max_students - Student::count();

        if (count($request->students) > $remaining) {
            return back()->withErrors(['students' => "License allows only {$remaining} more students."]);
        }

        $created = [];

        DB::transaction(function () use ($request, &$created) {
            foreach ($request->students as $row) {
                $plain   = $this->pwGen->generate();
                $student = Student::create([
                    'school_id'            => $this->schoolId(),
                    'name'                 => $row['name'],
                    'email'                => $row['email'],
                    'roll_number'          => $row['roll_number'] ?? null,
                    'password'             => bcrypt($plain),
                    'must_change_password' => true,
                    'status'               => 'active',
                    'created_by'           => $this->admin()->id,
                ]);

                AuditLogger::record('student.created', $student, ['email' => $student->email], null);
                $created[] = ['student' => $student, 'password' => $plain];
            }
        });

        return view('v2.school_admin.students.credentials', [
            'bulk'    => $created,
            'student' => null,
        ]);
    }

    public function show(Student $student): View
    {
        $this->authorizeStudent($student);
        $student->load('enrollments.schoolClass.grade', 'enrollments.schoolClass.subject');
        return view('v2.school_admin.students.show', compact('student'));
    }

    public function edit(Student $student): View
    {
        $this->authorizeStudent($student);
        return view('v2.school_admin.students.edit', compact('student'));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'email'       => "required|email|unique:v2_students,email,{$student->id}",
            'roll_number' => 'nullable|string|max:50',
        ]);

        $student->update($data);

        AuditLogger::record('student.updated', $student, $data, null);

        return redirect()->route('v2.school.students.show', $student)
            ->with('success', 'Student updated.');
    }

    public function toggle(Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $newStatus = $student->status === 'active' ? 'inactive' : 'active';
        $student->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            $student->increment('session_version');
        }

        AuditLogger::record("student.{$newStatus}", $student, null, null);

        return redirect()->route('v2.school.students.index')
            ->with('success', "Student {$newStatus}.");
    }

    public function resetPassword(Student $student): mixed
    {
        $this->authorizeStudent($student);

        $plain = $this->pwGen->generate();
        $student->update([
            'password'             => bcrypt($plain),
            'must_change_password' => true,
        ]);
        $student->increment('session_version');

        AuditLogger::record('student.password_reset', $student, null, null);

        return view('v2.school_admin.students.credentials', [
            'student'       => $student,
            'plainPassword' => $plain,
            'isReset'       => true,
        ]);
    }

    public function enroll(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $data = $request->validate(['class_id' => 'required|exists:v2_classes,id']);

        $class = SchoolClass::findOrFail($data['class_id']);
        abort_if($class->school_id !== $this->schoolId(), 403);

        StudentEnrollment::firstOrCreate([
            'student_id' => $student->id,
            'class_id'   => $class->id,
        ], [
            'school_id' => $this->schoolId(),
            'status'    => 'active',
        ]);

        AuditLogger::record('student.enrolled', $student, ['class_id' => $class->id], null);

        return back()->with('success', 'Student enrolled in class.');
    }

    public function unenroll(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($student);

        $data = $request->validate(['class_id' => 'required|exists:v2_classes,id']);

        StudentEnrollment::where([
            'student_id' => $student->id,
            'class_id'   => $data['class_id'],
            'school_id'  => $this->schoolId(),
        ])->delete();

        AuditLogger::record('student.unenrolled', $student, $data, null);

        return back()->with('success', 'Student removed from class.');
    }

    private function checkStudentLimit(): void
    {
        $school = $this->school();
        $count  = Student::count();
        abort_if($count >= $school->max_students, 422, "Student license limit ({$school->max_students}) reached.");
    }

    private function authorizeStudent(Student $student): void
    {
        abort_if($student->school_id !== $this->schoolId(), 403);
    }
}
