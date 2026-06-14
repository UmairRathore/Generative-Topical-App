@extends('v2.layouts.school_admin')
@section('page_title', 'Add Class')

@section('content')
<div style="max-width: 560px;">
    <a href="{{ route('v2.school.classes.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back to Classes
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <form method="POST" action="{{ route('v2.school.classes.store') }}" class="space-y-5">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Grade</label>
                <select name="grade_id" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);" required>
                    <option value="">Select grade…</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade->id }}" {{ old('grade_id') == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                    @endforeach
                </select>
                @error('grade_id') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Subject <span style="font-weight: 400; color: var(--muted);">(optional)</span></label>
                <select name="subject_id" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                    <option value="">— None / General —</option>
                    @foreach($subjects as $ss)
                        <option value="{{ $ss->subject_id }}" {{ old('subject_id') == $ss->subject_id ? 'selected' : '' }}>
                            {{ $ss->subject?->name ?? $ss->subject_id }}{{ $ss->grade ? " ({$ss->grade->short_name})" : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Section <span style="font-weight: 400; color: var(--muted);">(optional)</span></label>
                <input type="text" name="section" value="{{ old('section') }}" placeholder="e.g. A, B, Morning"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Custom Name <span style="font-weight: 400; color: var(--muted);">(optional)</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Advanced Math"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <button type="submit"
                    style="width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer;">
                Create Class
            </button>
        </form>
    </div>
</div>
@endsection
