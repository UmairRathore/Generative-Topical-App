<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily teacher notifications: student attention flags + operational updates.
Schedule::command('v2:teacher-attention-reminders')->dailyAt('06:00');

// Daily student notifications: exams opening/closing today + missed exams.
Schedule::command('v2:student-exam-reminders')->dailyAt('06:05');
