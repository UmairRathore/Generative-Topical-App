@extends('v2.layouts.school_admin')
@section('page_title', $teacher->name)

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('v2.school.teachers.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none;">
            <x-icon name="arrow-left" size="14" /> Back to Teachers
        </a>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('v2.school.teachers.reset_password', $teacher) }}">
                @csrf
                <button type="submit"
                        onclick="return confirm('Reset password for {{ $teacher->name }}? The new credentials will be shown once.')"
                        style="padding: 8px 16px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13px; font-weight: 500; cursor: pointer;">
                    Reset Password
                </button>
            </form>
            <a href="{{ route('v2.school.teachers.edit', $teacher) }}"
               style="padding: 8px 16px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13px; font-weight: 600; text-decoration: none;">
                Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Profile</h2>
            <dl class="space-y-3">
                @foreach([
                    ['Email',       $teacher->email],
                    ['Phone',       $teacher->phone ?? '-'],
                    ['Employee ID', $teacher->employee_id ?? '-'],
                    ['Status',      ucfirst($teacher->status)],
                    ['Last Login',  $teacher->last_login_at?->diffForHumans() ?? 'Never'],
                ] as [$label, $val])
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">{{ $label }}</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $val }}</dd>
                </div>
                @endforeach
            </dl>

            <form method="POST" action="{{ route('v2.school.teachers.toggle', $teacher) }}" style="margin-top: 20px;">
                @csrf
                <button type="submit"
                        style="width: 100%; padding: 9px; border-radius: 8px; border: 1px solid; font-size: 13px; font-weight: 600; cursor: pointer; {{ $teacher->status === 'active' ? 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;' : 'background:#d1fae5;color:#065f46;border-color:#6ee7b7;' }}">
                    {{ $teacher->status === 'active' ? 'Deactivate Teacher' : 'Activate Teacher' }}
                </button>
            </form>
        </div>

        <div class="lg:col-span-2" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Assigned Classes</h2>
            @if($teacher->classes->isEmpty())
                <p style="font-size: 13px; color: var(--muted);">No classes assigned.</p>
            @else
                <div class="space-y-2">
                    @foreach($teacher->classes as $class)
                    <div class="flex items-center justify-between" style="padding: 10px 14px; border-radius: 8px; background: var(--bg);">
                        <div>
                            <span style="font-size: 13.5px; font-weight: 500; color: var(--text);">{{ $class->grade?->name }}</span>
                            @if($class->section) <span style="font-size: 13px; color: var(--muted);"> · {{ $class->section }}</span> @endif
                        </div>
                        <span style="font-size: 12px; color: var(--muted);">{{ $class->subject?->name ?? 'General' }}</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
