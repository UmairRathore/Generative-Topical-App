<?php

use App\Livewire\Admin\ImportSummary;
use App\Livewire\Admin\PaperForm;
use App\Livewire\Admin\PaperIndex;
use App\Livewire\Admin\PaperQuestions;
use App\Livewire\Admin\QuestionBrowser;
use App\Livewire\Admin\QuestionForm;
use App\Livewire\Admin\QuestionReview;
use App\Livewire\Student\PaperRunner;
use App\Livewire\Student\PracticeRunner;
use App\Livewire\Student\ResultSummary;
use App\Livewire\Student\SubjectShow;
use App\Livewire\Student\TestAttempt;
use App\Livewire\Teacher\QuestionPicker;
use App\Livewire\Teacher\TestGenerator;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public (marketing + practice)
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome')->name('home');
Route::view('/pricing', 'public.pricing')->name('pricing');
Route::view('/about', 'public.about')->name('about');
Route::view('/contact', 'public.contact')->name('contact');

Route::get('/subjects/{slug}', SubjectShow::class)->name('subjects.show');
Route::get('/practice/{slug}', PracticeRunner::class)->name('practice.random');
Route::get('/papers/{paper}', PaperRunner::class)->name('papers.show');
Route::get('/practice/session/{session}/result', ResultSummary::class)->name('practice.result');

/*
|--------------------------------------------------------------------------
| Dashboard router — picks role-specific dashboard
|--------------------------------------------------------------------------
*/
Route::get('dashboard', function () {
    $user = auth()->user();
    if ($user?->isAdmin())   return redirect()->route('admin.dashboard');
    if ($user?->isTeacher()) return redirect()->route('teacher.dashboard');
    return redirect()->route('student.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');

    Route::get('imports', ImportSummary::class)->name('imports');
    Route::get('questions', QuestionBrowser::class)->name('questions');
    Route::get('questions/{question}/review', QuestionReview::class)->name('questions.review');

    Route::view('papers-overview', 'admin.papers')->name('papers-overview');
    Route::view('topics', 'admin.topics')->name('topics');
    Route::view('users', 'admin.users')->name('users');
    Route::view('analytics', 'admin.analytics')->name('analytics');

    Route::get('papers', PaperIndex::class)->name('papers');
    Route::get('papers/create', PaperForm::class)->name('papers.create');
    Route::get('papers/{paper}/edit', PaperForm::class)->name('papers.edit');
    Route::get('papers/{paper}/questions', PaperQuestions::class)->name('papers.questions');
    Route::get('papers/{paper}/questions/create', QuestionForm::class)->name('papers.questions.create');
    Route::get('papers/{paper}/questions/{question}/edit', QuestionForm::class)->name('papers.questions.edit');
});

/*
|--------------------------------------------------------------------------
| Teacher
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::view('/', 'teacher.dashboard')->name('dashboard');
    Route::get('test-generator', TestGenerator::class)->name('test-generator');
    Route::get('question-picker', QuestionPicker::class)->name('question-picker');
    Route::view('submissions', 'teacher.submissions')->name('submissions');
    Route::view('bank', 'teacher.bank')->name('bank');
    Route::view('classes', 'teacher.classes')->name('classes');
});

/*
|--------------------------------------------------------------------------
| Student
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('student')->name('student.')->group(function () {
    Route::view('/', 'student.dashboard')->name('dashboard');
    Route::view('tests', 'student.tests')->name('tests');
    Route::get('tests/{id}', TestAttempt::class)->name('tests.show');
    Route::view('results/{id}', 'student.result')->name('results.show');
    Route::view('review', 'student.review')->name('review');
    Route::view('practice', 'student.practice')->name('practice');
    Route::view('analytics', 'student.analytics')->name('analytics');
});

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
