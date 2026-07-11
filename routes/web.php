<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V1 (Livewire/Volt) app — RETIRED, kept commented for safety
|--------------------------------------------------------------------------
| The original Livewire app (marketing pages, admin/teacher/student portals,
| Breeze-style auth) is no longer in use. Its routes are COMMENTED OUT below
| rather than deleted, so nothing is lost and it can be revived if ever
| needed. The active application is V2 (routes/v2.php).
|
| Two thin compatibility routes are kept ACTIVE (below the retired block):
| 'home' and 'login'. The framework's auth redirects and a couple of V2 login
| blades still reference those route names, and both now funnel into the V2
| student login — which is the default entry point.
|
| ---- retired V1 routes (do not uncomment without restoring auth.php) --------
|
| use App\Livewire\Admin\ImportSummary;
| use App\Livewire\Admin\PaperForm;
| use App\Livewire\Admin\PaperIndex;
| use App\Livewire\Admin\PaperQuestions;
| use App\Livewire\Admin\QuestionBrowser;
| use App\Livewire\Admin\QuestionForm;
| use App\Livewire\Admin\QuestionReview;
| use App\Livewire\Student\PaperRunner;
| use App\Livewire\Student\PracticeRunner;
| use App\Livewire\Student\ResultSummary;
| use App\Livewire\Student\SubjectShow;
| use App\Livewire\Student\TestAttempt;
| use App\Livewire\Teacher\QuestionPicker;
| use App\Livewire\Teacher\TestGenerator;
| use Livewire\Volt\Volt;
|
| // Public (marketing + practice)
| Route::view('/', 'welcome')->name('home');
| Route::view('/pricing', 'public.pricing')->name('pricing');
| Route::view('/about', 'public.about')->name('about');
| Route::view('/contact', 'public.contact')->name('contact');
| Route::get('/subjects/{slug}', SubjectShow::class)->name('subjects.show');
| Route::get('/practice/{slug}', PracticeRunner::class)->name('practice.random');
| Route::get('/papers/{paper}', PaperRunner::class)->name('papers.show');
| Route::get('/practice/session/{session}/result', ResultSummary::class)->name('practice.result');
|
| // Dashboard router - picks role-specific dashboard
| Route::get('dashboard', function () {
|     $user = auth()->user();
|     if ($user?->isAdmin())   return redirect()->route('admin.dashboard');
|     if ($user?->isTeacher()) return redirect()->route('teacher.dashboard');
|     return redirect()->route('student.dashboard');
| })->middleware(['auth', 'verified'])->name('dashboard');
|
| // Admin
| Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
|     Route::view('/', 'admin.dashboard')->name('dashboard');
|     Route::get('imports', ImportSummary::class)->name('imports');
|     Route::get('questions', QuestionBrowser::class)->name('questions');
|     Route::get('questions/{question}/review', QuestionReview::class)->name('questions.review');
|     Route::view('papers-overview', 'admin.papers')->name('papers-overview');
|     Route::view('topics', 'admin.topics')->name('topics');
|     Route::view('users', 'admin.users')->name('users');
|     Route::view('analytics', 'admin.analytics')->name('analytics');
|     Route::get('papers', PaperIndex::class)->name('papers');
|     Route::get('papers/create', PaperForm::class)->name('papers.create');
|     Route::get('papers/{paper}/edit', PaperForm::class)->name('papers.edit');
|     Route::get('papers/{paper}/questions', PaperQuestions::class)->name('papers.questions');
|     Route::get('papers/{paper}/questions/create', QuestionForm::class)->name('papers.questions.create');
|     Route::get('papers/{paper}/questions/{question}/edit', QuestionForm::class)->name('papers.questions.edit');
| });
|
| // Teacher
| Route::middleware(['auth', 'teacher'])->prefix('teacher')->name('teacher.')->group(function () {
|     Route::view('/', 'teacher.dashboard')->name('dashboard');
|     Route::get('test-generator', TestGenerator::class)->name('test-generator');
|     Route::get('question-picker', QuestionPicker::class)->name('question-picker');
|     Route::view('submissions', 'teacher.submissions')->name('submissions');
|     Route::view('bank', 'teacher.bank')->name('bank');
|     Route::view('classes', 'teacher.classes')->name('classes');
| });
|
| // Student
| Route::middleware(['auth'])->prefix('student')->name('student.')->group(function () {
|     Route::view('/', 'student.dashboard')->name('dashboard');
|     Route::view('tests', 'student.tests')->name('tests');
|     Route::get('tests/{id}', TestAttempt::class)->name('tests.show');
|     Route::view('results/{id}', 'student.result')->name('results.show');
|     Route::view('review', 'student.review')->name('review');
|     Route::view('practice', 'student.practice')->name('practice');
|     Route::view('analytics', 'student.analytics')->name('analytics');
| });
|
| // Settings
| Route::middleware(['auth'])->group(function () {
|     Route::redirect('settings', 'settings/profile');
|     Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
|     Volt::route('settings/password', 'settings.password')->name('settings.password');
|     Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
| });
|
| require __DIR__.'/auth.php';   // V1 Breeze/Volt auth (login/register/password/verify/logout)
|
| -----------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Active: default entry point → V2 student login
|--------------------------------------------------------------------------
| V2 is the live app. The root and the generic 'login' name both funnel into
| the V2 student login. Logged-in users are bounced to their own portal
| dashboard by RedirectIfAuthenticated (see AppServiceProvider::boot()).
*/
Route::get('/', function () {
    // A signed-in user goes straight to their own dashboard; everyone else to
    // the student login (the default entry point).
    $dashboards = [
        'v2_super_admin'  => 'v2.super_admin.dashboard',
        'v2_school_admin' => 'v2.school.dashboard',
        'v2_branch_admin' => 'v2.branch.index',
        'v2_teacher'      => 'v2.teacher.dashboard',
        'v2_student'      => 'v2.student.dashboard',
    ];
    foreach ($dashboards as $guard => $dashboard) {
        if (Route::has($dashboard) && auth()->guard($guard)->check()) {
            return redirect()->route($dashboard);
        }
    }

    return redirect()->route('v2.student.login');
})->name('home');

// Kept so framework auth redirects + legacy `route('login')` references resolve
// to the default (V2 student) login.
Route::get('login', fn () => redirect()->route('v2.student.login'))->name('login');
