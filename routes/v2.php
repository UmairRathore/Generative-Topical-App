<?php

use App\Http\Controllers\V2\SchoolAdmin\AuthController as SchoolAdminAuth;
use App\Http\Controllers\V2\SchoolAdmin\DashboardController as SchoolAdminDashboard;
use App\Http\Controllers\V2\SchoolAdmin\GradeController as SchoolAdminGrade;
use App\Http\Controllers\V2\SchoolAdmin\SubjectController as SchoolAdminSubject;
use App\Http\Controllers\V2\SchoolAdmin\ClassController as SchoolAdminClass;
use App\Http\Controllers\V2\SchoolAdmin\TeacherController as SchoolAdminTeacher;
use App\Http\Controllers\V2\SchoolAdmin\StudentController as SchoolAdminStudent;
use App\Http\Controllers\V2\Student\AuthController as StudentAuth;
use App\Http\Controllers\V2\SuperAdmin\AuthController as SuperAdminAuth;
use App\Http\Controllers\V2\Teacher\AuthController as TeacherAuth;
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
                Route::view('dashboard', 'v2.super_admin.dashboard.index')->name('dashboard');
                Route::view('schools', 'v2.super_admin.schools.index')->name('schools.index');
                Route::view('schools/create', 'v2.super_admin.schools.create')->name('schools.create');
                Route::view('schools/{school}', 'v2.super_admin.schools.show')->name('schools.show');
                Route::view('schools/{school}/edit', 'v2.super_admin.schools.edit')->name('schools.edit');
                Route::view('audit', 'v2.super_admin.audit.index')->name('audit.index');
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
                Route::view('dashboard', 'v2.teacher.dashboard.index')->name('dashboard');
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

            Route::view('dashboard', 'v2.student.dashboard.index')->name('dashboard');
        });
    });
});
