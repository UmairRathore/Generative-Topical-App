@extends('v2.layouts.student')
@section('page_title', 'Notifications')

@section('content')
    <div style="margin-bottom: 20px;">
        <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Student</div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 4px;">Notifications</h2>
        <p style="color: var(--text-soft); margin-top: 2px; font-size: 13.5px;">Tests released to your class, reminders, and results — newest first.</p>
    </div>

    <livewire:student.notification-index />
@endsection
