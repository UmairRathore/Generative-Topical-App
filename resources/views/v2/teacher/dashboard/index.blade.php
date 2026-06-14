@extends('v2.layouts.teacher')
@section('page_title', 'Dashboard')

@section('content')
@php $user = auth('v2_teacher')->user(); @endphp

<div style="margin-bottom: 28px;">
    <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Teacher Dashboard</div>
    <h2 class="serif" style="font-size: 32px; font-weight: 600; margin-top: 4px;">Welcome, {{ $user?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 4px; font-size: 14px;">{{ $user?->school?->name ?? 'Your School' }}</p>
</div>

<div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
    <p style="color: var(--text-soft); font-size: 14px;">Teacher features coming in next module.</p>
</div>
@endsection
