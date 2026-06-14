@extends('v2.layouts.school_admin')
@section('page_title', 'Edit Teacher')

@section('content')
<div style="max-width: 540px;">
    <a href="{{ route('v2.school.teachers.show', $teacher) }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <form method="POST" action="{{ route('v2.school.teachers.update', $teacher) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $teacher->name) }}" required
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                @error('name') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Email</label>
                <input type="email" name="email" value="{{ old('email', $teacher->email) }}" required
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                @error('email') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $teacher->phone) }}"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Employee ID</label>
                <input type="text" name="employee_id" value="{{ old('employee_id', $teacher->employee_id) }}"
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
