@extends('v2.layouts.school_admin')
@section('page_title', 'Add Grade')

@section('content')
<div style="max-width: 520px;">
    <a href="{{ route('v2.school.grades.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back to Grades
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <form method="POST" action="{{ route('v2.school.grades.store') }}" class="space-y-5">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Grade Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Grade 10"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid {{ $errors->has('name') ? '#fca5a5' : 'var(--border)' }}; background: var(--bg); font-size: 14px; color: var(--text);" required>
                @error('name') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Short Name</label>
                <input type="text" name="short_name" value="{{ old('short_name') }}" placeholder="e.g. G10" maxlength="20"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);" required>
                @error('short_name') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);" required>
                <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">Lower numbers appear first.</p>
            </div>

            <button type="submit"
                    style="width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer;">
                Create Grade
            </button>
        </form>
    </div>
</div>
@endsection
