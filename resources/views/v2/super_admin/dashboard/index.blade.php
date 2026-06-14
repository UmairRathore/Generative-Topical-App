@extends('v2.layouts.super_admin')
@section('page_title', 'Dashboard')

@section('content')
@php $user = auth('v2_super_admin')->user(); @endphp

<div style="margin-bottom: 28px;">
    <div class="uppercase-eyebrow" style="color: var(--text-faint); font-size: 11px;">Platform Overview</div>
    <h2 class="serif" style="font-size: 32px; font-weight: 600; margin-top: 4px;">Good day, {{ $user?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 4px; font-size: 14px;">Here's a summary of the TopicalEd platform.</p>
</div>

<div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px;">
    @foreach([
        ['Schools', '—', 'school', 'Total registered schools'],
        ['Active Schools', '—', 'check', 'Currently active'],
        ['Total Students', '—', 'users', 'Across all schools'],
    ] as [$label, $value, $icon, $sub])
        <div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
            <div class="flex items-start justify-between">
                <div>
                    <div style="font-size: 12px; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;">{{ $label }}</div>
                    <div class="serif" style="font-size: 36px; font-weight: 600; margin-top: 4px; color: var(--text);">{{ $value }}</div>
                    <div style="font-size: 12px; color: var(--text-soft); margin-top: 2px;">{{ $sub }}</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--emerald-900); display: flex; align-items: center; justify-content: center;">
                    <x-icon :name="$icon" size="18" style="color: var(--accent);"/>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
    <div class="flex items-center justify-between" style="margin-bottom: 16px;">
        <h3 style="font-size: 15px; font-weight: 600;">Quick Actions</h3>
    </div>
    <div class="flex" style="gap: 12px; flex-wrap: wrap;">
        <a href="{{ route('v2.super_admin.schools.index') }}" class="btn btn-primary">
            <x-icon name="school" size="14"/> Manage Schools
        </a>
        <a href="{{ route('v2.super_admin.schools.create') }}" class="btn btn-ghost">
            <x-icon name="plus" size="14"/> Add School
        </a>
        <a href="{{ route('v2.super_admin.audit.index') }}" class="btn btn-ghost">
            <x-icon name="eye" size="14"/> View Audit Log
        </a>
    </div>
</div>
@endsection
