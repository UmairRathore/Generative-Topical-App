@extends('v2.layouts.school_admin')
@section('page_title', 'Add Teacher')

@section('content')
<div style="max-width: 540px;">
    <a href="{{ route('v2.school.teachers.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back to Teachers
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(212,164,55,.07); border: 1px solid rgba(212,164,55,.2);">
            A secure temporary password will be generated automatically. Credentials are shown only once after creation.
        </p>

        <form method="POST" action="{{ route('v2.school.teachers.store') }}" class="space-y-5">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                @error('name') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
                @error('email') <p style="font-size: 12px; color: #ef4444; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Phone <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Employee ID <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
                <input type="text" name="employee_id" value="{{ old('employee_id') }}"
                       style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 14px; color: var(--text);">
            </div>

            <button type="submit"
                    style="width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer;">
                Create Teacher
            </button>
        </form>
    </div>
</div>
@endsection
