<?php

use App\Livewire\Admin\ImportSummary;
use App\Livewire\Admin\PaperForm;
use App\Livewire\Admin\PaperIndex;
use App\Livewire\Admin\PaperQuestions;
use App\Livewire\Admin\QuestionBrowser;
use App\Livewire\Admin\QuestionForm;
use App\Livewire\Student\PaperRunner;
use App\Livewire\Student\PracticeRunner;
use App\Livewire\Student\ResultSummary;
use App\Livewire\Student\SubjectShow;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/subjects/{slug}', SubjectShow::class)->name('subjects.show');
Route::get('/practice/{slug}', PracticeRunner::class)->name('practice.random');
Route::get('/papers/{paper}', PaperRunner::class)->name('papers.show');
Route::get('/practice/session/{session}/result', ResultSummary::class)->name('practice.result');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('imports', ImportSummary::class)->name('imports');
    Route::get('questions', QuestionBrowser::class)->name('questions');

    Route::get('papers', PaperIndex::class)->name('papers');
    Route::get('papers/create', PaperForm::class)->name('papers.create');
    Route::get('papers/{paper}/edit', PaperForm::class)->name('papers.edit');
    Route::get('papers/{paper}/questions', PaperQuestions::class)->name('papers.questions');
    Route::get('papers/{paper}/questions/create', QuestionForm::class)->name('papers.questions.create');
    Route::get('papers/{paper}/questions/{question}/edit', QuestionForm::class)->name('papers.questions.edit');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
