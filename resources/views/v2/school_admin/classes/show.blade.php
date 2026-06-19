@extends('v2.layouts.school_admin')
@section('page_title', $class->name ?: ($class->grade?->name . ($class->section ? " · {$class->section}" : '')))

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('v2.school.classes.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none;">
            <x-icon name="arrow-left" size="14" /> Back to Classes
        </a>
        <a href="{{ route('v2.school.classes.edit', $class) }}"
           style="padding: 8px 16px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13px; font-weight: 500; text-decoration: none;">
            Edit Class
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Class info --}}
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Class Info</h2>
            <dl class="space-y-3">
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">Grade</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $class->grade?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">Subject</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $class->subject?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">Section</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $class->section ?? '-' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em;">Students Enrolled</dt>
                    <dd style="font-size: 14px; color: var(--text); margin-top: 2px;">{{ $class->enrollments->count() }}</dd>
                </div>
            </dl>
        </div>

        {{-- Teachers --}}
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Teachers</h2>

            @if($class->classTeachers->isEmpty())
                <p style="font-size: 13px; color: var(--muted);">No teachers assigned.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($class->classTeachers as $ct)
                    <div class="flex items-center justify-between" style="padding: 10px; border-radius: 8px; background: var(--bg);">
                        <div>
                            <div style="font-size: 13px; font-weight: 500; color: var(--text);">{{ $ct->teacher?->name ?? '-' }}</div>
                            @if($ct->is_primary) <div style="font-size: 11px; color: var(--accent); font-weight: 600;">Primary</div> @endif
                        </div>
                        <form method="POST" action="{{ route('v2.school.classes.remove_teacher', $class) }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <input type="hidden" name="teacher_id" value="{{ $ct->teacher_id }}">
                            <button type="submit" style="font-size: 12px; color: #ef4444; background: transparent; border: 0; cursor: pointer; padding: 4px;">Remove</button>
                        </form>
                    </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('v2.school.classes.assign_teacher', $class) }}" class="space-y-2">
                @csrf
                <select name="teacher_id" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);" required>
                    <option value="">Assign a teacher…</option>
                    @foreach(\App\Models\V2\Teacher::orderBy('name')->get() as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2" style="font-size: 13px; color: var(--text);">
                    <input type="checkbox" name="is_primary" value="1"> Primary teacher
                </label>
                <button type="submit" style="padding: 8px 16px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13px; font-weight: 600; border: none; cursor: pointer;">
                    Assign
                </button>
            </form>
        </div>

        {{-- Enrolled students --}}
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 15px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Enrolled Students</h2>
            @if($class->enrollments->isEmpty())
                <p style="font-size: 13px; color: var(--muted);">No students enrolled.</p>
            @else
                <div class="space-y-2">
                    @foreach($class->enrollments as $enrollment)
                    <div class="flex items-center justify-between" style="padding: 8px 10px; border-radius: 8px; background: var(--bg);">
                        <div style="font-size: 13px; color: var(--text);">{{ $enrollment->student?->name ?? '-' }}</div>
                        <span style="font-size: 11.5px; color: var(--muted);">{{ $enrollment->student?->roll_number }}</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
