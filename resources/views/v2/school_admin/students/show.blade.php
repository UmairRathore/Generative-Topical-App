@extends('v2.layouts.school_admin')
@section('page_title', $student->name)

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('v2.school.students.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none;">
            <x-icon name="arrow-left" size="14" /> Back to Students
        </a>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('v2.school.students.reset_password', $student) }}">
                @csrf
                <button type="submit"
                        onclick="return confirm('Reset password for {{ $student->name }}?')"
                        style="padding: 8px 16px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13px; font-weight: 500; cursor: pointer;">
                    Reset Password
                </button>
            </form>
            <a href="{{ route('v2.school.students.edit', $student) }}"
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
                    ['Email',      $student->email],
                    ['Roll No.',   $student->roll_number ?? '—'],
                    ['Status',     ucfirst($student->status)],
                    ['Last Login', $student->last_login_at?->diffForHumans() ?? 'Never'],
                ] as [$label, $val])
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">{{ $label }}</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $val }}</dd>
                </div>
                @endforeach
            </dl>

            <form method="POST" action="{{ route('v2.school.students.toggle', $student) }}" style="margin-top: 20px;">
                @csrf
                <button type="submit"
                        style="width: 100%; padding: 9px; border-radius: 8px; border: 1px solid; font-size: 13px; font-weight: 600; cursor: pointer; {{ $student->status === 'active' ? 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;' : 'background:#d1fae5;color:#065f46;border-color:#6ee7b7;' }}">
                    {{ $student->status === 'active' ? 'Deactivate Student' : 'Activate Student' }}
                </button>
            </form>
        </div>

        <div class="lg:col-span-2" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Enrolled Classes</h2>
            @if($student->enrollments->isEmpty())
                <p style="font-size: 13px; color: var(--muted);">No class enrollments.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($student->enrollments as $enrollment)
                    <div class="flex items-center justify-between" style="padding: 10px 14px; border-radius: 8px; background: var(--bg);">
                        <div>
                            <span style="font-size: 13.5px; font-weight: 500; color: var(--text);">{{ $enrollment->schoolClass?->grade?->name }}</span>
                            @if($enrollment->schoolClass?->section)
                                <span style="font-size: 13px; color: var(--muted);"> · {{ $enrollment->schoolClass->section }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span style="font-size: 12px; color: var(--muted);">{{ $enrollment->schoolClass?->subject?->name ?? 'General' }}</span>
                            <form method="POST" action="{{ route('v2.school.students.unenroll', $student) }}" style="display:inline;">
                                @csrf @method('DELETE')
                                <input type="hidden" name="class_id" value="{{ $enrollment->class_id }}">
                                <button type="submit" style="font-size: 12px; color: #ef4444; background: transparent; border: 0; cursor: pointer; padding: 2px 6px;">Remove</button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('v2.school.students.enroll', $student) }}" class="flex gap-2">
                @csrf
                <select name="class_id" style="flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);" required>
                    <option value="">Enrol in a class…</option>
                    @foreach(\App\Models\V2\SchoolClass::with(['grade','subject'])->get() as $cls)
                        <option value="{{ $cls->id }}">{{ $cls->grade?->name }}{{ $cls->section ? " · {$cls->section}" : '' }}{{ $cls->subject ? " ({$cls->subject->name})" : '' }}</option>
                    @endforeach
                </select>
                <button type="submit" style="padding: 8px 16px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Enrol
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
