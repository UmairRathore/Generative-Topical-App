<?php

use App\Http\Controllers\V2\SchoolAdmin\AnalyticsController as SchoolAdminAnalytics;
use App\Http\Controllers\V2\SchoolAdmin\AuthController as SchoolAdminAuth;
use App\Http\Controllers\V2\SchoolAdmin\DashboardController as SchoolAdminDashboard;
use App\Http\Controllers\V2\SchoolAdmin\GradeController as SchoolAdminGrade;
use App\Http\Controllers\V2\SchoolAdmin\SubjectController as SchoolAdminSubject;
use App\Http\Controllers\V2\SchoolAdmin\ClassController as SchoolAdminClass;
use App\Http\Controllers\V2\SchoolAdmin\TeacherController as SchoolAdminTeacher;
use App\Http\Controllers\V2\SchoolAdmin\StudentController as SchoolAdminStudent;
use App\Http\Controllers\V2\BranchAdmin\AnalyticsController as BranchAnalytics;
use App\Http\Controllers\V2\BranchAdmin\AuthController as BranchAuth;
use App\Http\Controllers\V2\BranchAdmin\ReportController as BranchReport;
use App\Http\Controllers\V2\Student\AuthController as StudentAuth;
use App\Http\Controllers\V2\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\V2\Student\ExamController as StudentExam;
use App\Http\Controllers\V2\SuperAdmin\AuthController as SuperAdminAuth;
use App\Http\Controllers\V2\SuperAdmin\ClassController as SuperAdminClass;
use App\Http\Controllers\V2\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\V2\SuperAdmin\GradeController as SuperAdminGrade;
use App\Http\Controllers\V2\SuperAdmin\QuestionBankController as SuperAdminQuestionBank;
use App\Http\Controllers\V2\SuperAdmin\SchoolController as SuperAdminSchool;
use App\Http\Controllers\V2\SuperAdmin\StudentController as SuperAdminStudent;
use App\Http\Controllers\V2\SuperAdmin\SubjectController as SuperAdminSubject;
use App\Http\Controllers\V2\SuperAdmin\TeacherController as SuperAdminTeacher;
use App\Http\Controllers\V2\SuperAdmin\TopicController as SuperAdminTopic;
use App\Http\Controllers\V2\Teacher\AuthController as TeacherAuth;
use App\Http\Controllers\V2\Teacher\ClassController as TeacherClass;
use App\Http\Controllers\V2\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\V2\Teacher\ExamController as TeacherExam;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->name('v2.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Super Admin
    |----------------------------------------------------------------------
    */
    Route::prefix('super-admin')->name('super_admin.')->group(function () {

        Route::middleware('guest:v2_super_admin')->group(function () {
            Route::get('login', [SuperAdminAuth::class, 'showLogin'])->name('login');
            Route::post('login', [SuperAdminAuth::class, 'login'])->name('login.post');
        });

        Route::middleware('auth:v2_super_admin')->group(function () {
            Route::post('logout', [SuperAdminAuth::class, 'logout'])->name('logout');
            Route::get('change-password', [SuperAdminAuth::class, 'showChangePassword'])->name('change_password');
            Route::post('change-password', [SuperAdminAuth::class, 'changePassword'])->name('change_password.post');

            Route::middleware('v2.must_change_password:v2_super_admin,v2.super_admin.change_password')->group(function () {
                Route::get('dashboard', [SuperAdminDashboard::class, 'index'])->name('dashboard');

                // Platform-wide rollups (cross-school)
                Route::get('grades', [SuperAdminGrade::class, 'platform'])->name('grades.index');
                Route::get('subjects', [SuperAdminSubject::class, 'platform'])->name('subjects.index');
                Route::get('subjects/{subject}', [SuperAdminSubject::class, 'showPlatform'])->name('subjects.show');
                Route::get('topics', [SuperAdminTopic::class, 'platform'])->name('topics.index');

                // Schools + per-school drill-down (school → grade/teacher/subject/topic → class → student → paper)
                Route::get('schools', [SuperAdminSchool::class, 'index'])->name('schools.index');
                Route::view('schools/create', 'v2.super_admin.schools.create')->name('schools.create'); // stub — create not built yet
                Route::get('schools/{school}', [SuperAdminSchool::class, 'show'])->name('schools.show');
                Route::view('schools/{school}/edit', 'v2.super_admin.schools.edit')->name('schools.edit'); // stub — edit not built yet
                Route::get('schools/{school}/grades', [SuperAdminGrade::class, 'index'])->name('schools.grades.index');
                Route::get('schools/{school}/grades/{grade}', [SuperAdminGrade::class, 'show'])->name('schools.grades.show');
                Route::get('schools/{school}/teachers/{teacher}', [SuperAdminTeacher::class, 'show'])->name('schools.teachers.show');
                Route::get('schools/{school}/subjects', [SuperAdminSubject::class, 'index'])->name('schools.subjects.index');
                Route::get('schools/{school}/subjects/{subject}', [SuperAdminSubject::class, 'show'])->name('schools.subjects.show');
                Route::get('schools/{school}/topics', [SuperAdminTopic::class, 'show'])->name('schools.topics.index');
                Route::get('schools/{school}/classes/{class}', [SuperAdminClass::class, 'show'])->name('schools.classes.show');
                Route::get('schools/{school}/students/{student}', [SuperAdminStudent::class, 'show'])->name('schools.students.show');
                Route::get('schools/{school}/exams/{exam}/students/{student}/paper', [SuperAdminStudent::class, 'paper'])->name('schools.student_paper');

                // Question bank — browse + full CRUD (Super Admin only). 'create' before '{question}'.
                Route::get('question-bank', [SuperAdminQuestionBank::class, 'index'])->name('question_bank.index');
                Route::get('question-bank/create', [SuperAdminQuestionBank::class, 'create'])->name('question_bank.create');
                Route::post('question-bank', [SuperAdminQuestionBank::class, 'store'])->name('question_bank.store');
                Route::get('question-bank/{question}/edit', [SuperAdminQuestionBank::class, 'edit'])->name('question_bank.edit');
                Route::put('question-bank/{question}', [SuperAdminQuestionBank::class, 'update'])->name('question_bank.update');
                Route::delete('question-bank/{question}', [SuperAdminQuestionBank::class, 'destroy'])->name('question_bank.destroy');
                Route::patch('question-bank/{question}/status', [SuperAdminQuestionBank::class, 'setStatus'])->name('question_bank.status');

                Route::view('audit', 'v2.super_admin.audit.index')->name('audit.index'); // stub — audit viewer not built yet
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | School Admin
    |----------------------------------------------------------------------
    */
    Route::prefix('school')->name('school.')->group(function () {

        Route::middleware('guest:v2_school_admin')->group(function () {
            Route::get('login', [SchoolAdminAuth::class, 'showLogin'])->name('login');
            Route::post('login', [SchoolAdminAuth::class, 'login'])->name('login.post');
        });

        Route::middleware('auth:v2_school_admin')->group(function () {
            Route::post('logout', [SchoolAdminAuth::class, 'logout'])->name('logout');
            Route::get('change-password', [SchoolAdminAuth::class, 'showChangePassword'])->name('change_password');
            Route::post('change-password', [SchoolAdminAuth::class, 'changePassword'])->name('change_password.post');

            Route::middleware([
                'v2.must_change_password:v2_school_admin,v2.school.change_password',
                'v2.session_version:v2_school_admin,v2.school.login',
            ])->group(function () {
                Route::get('dashboard', [SchoolAdminDashboard::class, 'index'])->name('dashboard');

                // Analytics (school → grade/teacher/subject/topic → class → student → paper)
                Route::get('analytics', [SchoolAdminAnalytics::class, 'index'])->name('analytics.index');
                Route::get('analytics/grades', [SchoolAdminAnalytics::class, 'grades'])->name('analytics.grades');
                Route::get('analytics/grades/{grade}', [SchoolAdminAnalytics::class, 'grade'])->name('analytics.grade');
                Route::get('analytics/subjects', [SchoolAdminAnalytics::class, 'subjects'])->name('analytics.subjects');
                Route::get('analytics/subjects/{subject}', [SchoolAdminAnalytics::class, 'subject'])->name('analytics.subject');
                Route::get('analytics/topics', [SchoolAdminAnalytics::class, 'topics'])->name('analytics.topics');
                Route::get('analytics/teachers/{teacher}', [SchoolAdminAnalytics::class, 'teacher'])->name('analytics.teacher');
                Route::get('analytics/classes/{class}', [SchoolAdminAnalytics::class, 'classDetail'])->name('analytics.class');
                Route::get('analytics/students/{student}', [SchoolAdminAnalytics::class, 'student'])->name('analytics.student');
                Route::get('analytics/exams/{exam}/students/{student}/paper', [SchoolAdminAnalytics::class, 'studentPaper'])->name('analytics.student_paper');

                // Grades
                Route::get('grades', [SchoolAdminGrade::class, 'index'])->name('grades.index');
                Route::get('grades/create', [SchoolAdminGrade::class, 'create'])->name('grades.create');
                Route::post('grades', [SchoolAdminGrade::class, 'store'])->name('grades.store');
                Route::get('grades/{grade}/edit', [SchoolAdminGrade::class, 'edit'])->name('grades.edit');
                Route::put('grades/{grade}', [SchoolAdminGrade::class, 'update'])->name('grades.update');
                Route::post('grades/{grade}/toggle', [SchoolAdminGrade::class, 'toggle'])->name('grades.toggle');

                // Subjects
                Route::get('subjects', [SchoolAdminSubject::class, 'index'])->name('subjects.index');
                Route::post('subjects/assign', [SchoolAdminSubject::class, 'assign'])->name('subjects.assign');
                Route::delete('subjects/remove', [SchoolAdminSubject::class, 'remove'])->name('subjects.remove');

                // Classes
                Route::get('classes', [SchoolAdminClass::class, 'index'])->name('classes.index');
                Route::get('classes/create', [SchoolAdminClass::class, 'create'])->name('classes.create');
                Route::post('classes', [SchoolAdminClass::class, 'store'])->name('classes.store');
                Route::get('classes/{class}', [SchoolAdminClass::class, 'show'])->name('classes.show');
                Route::get('classes/{class}/edit', [SchoolAdminClass::class, 'edit'])->name('classes.edit');
                Route::put('classes/{class}', [SchoolAdminClass::class, 'update'])->name('classes.update');
                Route::post('classes/{class}/assign-teacher', [SchoolAdminClass::class, 'assignTeacher'])->name('classes.assign_teacher');
                Route::delete('classes/{class}/remove-teacher', [SchoolAdminClass::class, 'removeTeacher'])->name('classes.remove_teacher');

                // Teachers
                Route::get('teachers', [SchoolAdminTeacher::class, 'index'])->name('teachers.index');
                Route::get('teachers/create', [SchoolAdminTeacher::class, 'create'])->name('teachers.create');
                Route::post('teachers', [SchoolAdminTeacher::class, 'store'])->name('teachers.store');
                Route::get('teachers/bulk-create', [SchoolAdminTeacher::class, 'bulkCreate'])->name('teachers.bulk_create');
                Route::post('teachers/bulk', [SchoolAdminTeacher::class, 'bulkStore'])->name('teachers.bulk_store');
                Route::get('teachers/{teacher}', [SchoolAdminTeacher::class, 'show'])->name('teachers.show');
                Route::get('teachers/{teacher}/edit', [SchoolAdminTeacher::class, 'edit'])->name('teachers.edit');
                Route::put('teachers/{teacher}', [SchoolAdminTeacher::class, 'update'])->name('teachers.update');
                Route::post('teachers/{teacher}/toggle', [SchoolAdminTeacher::class, 'toggle'])->name('teachers.toggle');
                Route::post('teachers/{teacher}/reset-password', [SchoolAdminTeacher::class, 'resetPassword'])->name('teachers.reset_password');

                // Students
                Route::get('students', [SchoolAdminStudent::class, 'index'])->name('students.index');
                Route::get('students/create', [SchoolAdminStudent::class, 'create'])->name('students.create');
                Route::post('students', [SchoolAdminStudent::class, 'store'])->name('students.store');
                Route::get('students/bulk-create', function () { return view('v2.school_admin.students.bulk_create'); })->name('students.bulk_create');
                Route::post('students/bulk', [SchoolAdminStudent::class, 'bulkStore'])->name('students.bulk_store');
                Route::get('students/{student}', [SchoolAdminStudent::class, 'show'])->name('students.show');
                Route::get('students/{student}/edit', [SchoolAdminStudent::class, 'edit'])->name('students.edit');
                Route::put('students/{student}', [SchoolAdminStudent::class, 'update'])->name('students.update');
                Route::post('students/{student}/toggle', [SchoolAdminStudent::class, 'toggle'])->name('students.toggle');
                Route::post('students/{student}/reset-password', [SchoolAdminStudent::class, 'resetPassword'])->name('students.reset_password');
                Route::post('students/{student}/enroll', [SchoolAdminStudent::class, 'enroll'])->name('students.enroll');
                Route::delete('students/{student}/unenroll', [SchoolAdminStudent::class, 'unenroll'])->name('students.unenroll');
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | Branch Admin (campus) — same drill-down as school admin, scoped to one branch
    |----------------------------------------------------------------------
    */
    Route::prefix('branch')->name('branch.')->group(function () {

        Route::middleware('guest:v2_branch_admin')->group(function () {
            Route::get('login', [BranchAuth::class, 'showLogin'])->name('login');
            Route::post('login', [BranchAuth::class, 'login'])->name('login.post');
        });

        Route::middleware('auth:v2_branch_admin')->group(function () {
            Route::post('logout', [BranchAuth::class, 'logout'])->name('logout');
            Route::get('change-password', [BranchAuth::class, 'showChangePassword'])->name('change_password');
            Route::post('change-password', [BranchAuth::class, 'changePassword'])->name('change_password.post');

            Route::middleware('v2.must_change_password:v2_branch_admin,v2.branch.change_password')->group(function () {
                Route::get('dashboard', [BranchAnalytics::class, 'index'])->name('index');
                Route::get('grades', [BranchAnalytics::class, 'grades'])->name('grades');
                Route::get('grades/{grade}', [BranchAnalytics::class, 'grade'])->name('grade');
                Route::get('subjects', [BranchAnalytics::class, 'subjects'])->name('subjects');
                Route::get('subjects/{subject}', [BranchAnalytics::class, 'subject'])->name('subject');
                Route::get('topics', [BranchAnalytics::class, 'topics'])->name('topics');
                Route::get('teachers/{teacher}', [BranchAnalytics::class, 'teacher'])->name('teacher');
                Route::get('classes/{class}', [BranchAnalytics::class, 'classDetail'])->name('class');
                Route::get('students/{student}', [BranchAnalytics::class, 'student'])->name('student');
                Route::post('students/{student}/reports', [BranchReport::class, 'generate'])->name('report.generate');
                Route::get('reports/{report}/pdf', [BranchReport::class, 'pdf'])->name('report.pdf');
                Route::get('exams/{exam}/students/{student}/paper', [BranchAnalytics::class, 'studentPaper'])->name('student_paper');
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | Teacher
    |----------------------------------------------------------------------
    */
    Route::prefix('teacher')->name('teacher.')->group(function () {

        Route::middleware('guest:v2_teacher')->group(function () {
            Route::get('login', [TeacherAuth::class, 'showLogin'])->name('login');
            Route::post('login', [TeacherAuth::class, 'login'])->name('login.post');
        });

        Route::middleware('auth:v2_teacher')->group(function () {
            Route::post('logout', [TeacherAuth::class, 'logout'])->name('logout');
            Route::get('change-password', [TeacherAuth::class, 'showChangePassword'])->name('change_password');
            Route::post('change-password', [TeacherAuth::class, 'changePassword'])->name('change_password.post');

            Route::middleware('v2.must_change_password:v2_teacher,v2.teacher.change_password')->group(function () {
                Route::get('dashboard', [TeacherDashboard::class, 'index'])->name('dashboard');

                // Exams
                Route::get('exams', [TeacherExam::class, 'index'])->name('exams.index');
                Route::get('exams/create', [TeacherExam::class, 'create'])->name('exams.create');
                Route::post('exams', [TeacherExam::class, 'store'])->name('exams.store');
                Route::get('exams/{exam}', [TeacherExam::class, 'show'])->name('exams.show');
                Route::get('exams/{exam}/students/{student}/paper', [TeacherExam::class, 'studentPaper'])->name('exams.student_paper');

                // Classes (analytics: per-topic + per-student-per-topic)
                Route::get('classes', [TeacherClass::class, 'index'])->name('classes.index');
                Route::get('classes/{class}', [TeacherClass::class, 'show'])->name('classes.show');
                Route::get('students/{student}', [TeacherClass::class, 'student'])->name('students.show');
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | Student
    |----------------------------------------------------------------------
    */
    Route::prefix('student')->name('student.')->group(function () {

        Route::middleware('guest:v2_student')->group(function () {
            Route::get('login', [StudentAuth::class, 'showLogin'])->name('login');
            Route::post('login', [StudentAuth::class, 'login'])->name('login.post');
        });

        Route::middleware('auth:v2_student')->group(function () {
            Route::post('logout', [StudentAuth::class, 'logout'])->name('logout');

            Route::get('dashboard', [StudentDashboard::class, 'index'])->name('dashboard');

            // Exams
            Route::get('exams', [StudentExam::class, 'index'])->name('exams.index');
            Route::get('exams/{exam}/take', [StudentExam::class, 'take'])->name('exams.take');
            Route::post('exams/{exam}/submit', [StudentExam::class, 'submit'])->name('exams.submit');
            Route::get('exams/{exam}/result', [StudentExam::class, 'result'])->name('exams.result');
        });
    });
});
