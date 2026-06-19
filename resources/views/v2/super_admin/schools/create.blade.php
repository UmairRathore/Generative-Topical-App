@extends('v2.layouts.super_admin')
@section('page_title', 'Add School')

@section('content')
<div style="max-width: 640px;">
    <div style="margin-bottom: 24px;">
        <a href="{{ route('v2.super_admin.schools.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
            <x-icon name="chev-l" size="12"/> Schools
        </a>
        <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 8px;">Add New School</h2>
    </div>

    <div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
        <p style="color: var(--text-soft); font-size: 14px;">School creation form - coming in next module.</p>
    </div>
</div>
@endsection
