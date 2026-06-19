@extends('v2.layouts.school_admin')
@section('page_title', 'Edit Class')

@section('content')
<div style="max-width: 560px;">
    <a href="{{ route('v2.school.classes.show', $class) }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <form method="POST" action="{{ route('v2.school.classes.update', $class) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Grade</label>
                <select name="grade_id" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);" required>
                    @foreach($grades as $grade)
                        <option value="{{ $grade->id }}" {{ $class->grade_id == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Subject <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <select name="subject_id" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                    <option value="">- None -</option>
                    @foreach($subjects as $ss)
                        <option value="{{ $ss->subject_id }}" {{ $class->subject_id == $ss->subject_id ? 'selected' : '' }}>
                            {{ $ss->subject?->name ?? $ss->subject_id }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Section</label>
                <input type="text" name="section" value="{{ old('section', $class->section) }}"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Custom Name</label>
                <input type="text" name="name" value="{{ old('name', $class->name) }}"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <button type="submit"
                    style="width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer;">
                Save Changes
            </button>
        </form>
    </div>
</div>
@endsection
