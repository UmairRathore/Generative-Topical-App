@extends('v2.layouts.super_admin')
@section('page_title', 'School Detail')

@section('content')
<div style="max-width: 800px;">
    <a href="{{ route('v2.super_admin.schools.index') }}" style="font-size: 13px; color: var(--text-soft); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 20px;">
        <x-icon name="chev-l" size="12"/> All Schools
    </a>
    <div style="padding: var(--pad-card); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
        <p style="color: var(--text-soft); font-size: 14px;">School detail view — coming in next module.</p>
    </div>
</div>
@endsection
