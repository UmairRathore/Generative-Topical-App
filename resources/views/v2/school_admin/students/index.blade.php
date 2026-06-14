@extends('v2.layouts.school_admin')
@section('page_title', 'Students')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <p style="color: var(--muted); font-size: 14px;">{{ $students->total() }} student(s) · license: {{ $students->total() }} / {{ $school->max_students }}</p>
        <div class="flex gap-2">
            <a href="{{ route('v2.school.students.bulk_create') }}"
               style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13px; font-weight: 500; text-decoration: none;">
                <x-icon name="users" size="15" /> Bulk Add
            </a>
            <a href="{{ route('v2.school.students.create') }}"
               style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none;">
                <x-icon name="plus" size="16" /> Add Student
            </a>
        </div>
    </div>

    @if($students->isEmpty())
        <div style="text-align: center; padding: 60px 0; color: var(--muted);">
            <x-icon name="users" size="40" style="opacity:.3; margin: 0 auto 12px;" />
            <p>No students yet.</p>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg); border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Name</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Email</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Roll No.</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Status</th>
                        <th style="padding: 12px 16px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $student)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 13px 16px; font-size: 14px; font-weight: 500; color: var(--text);">{{ $student->name }}</td>
                        <td style="padding: 13px 16px; font-size: 13px; color: var(--muted);">{{ $student->email }}</td>
                        <td style="padding: 13px 16px; font-size: 13px; color: var(--muted);">{{ $student->roll_number ?? '—' }}</td>
                        <td style="padding: 13px 16px;">
                            <span style="display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; {{ $student->status === 'active' ? 'background:#d1fae5;color:#065f46;' : 'background:#fee2e2;color:#991b1b;' }}">
                                {{ ucfirst($student->status) }}
                            </span>
                        </td>
                        <td style="padding: 13px 16px; text-align: right;">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('v2.school.students.show', $student) }}"
                                   style="padding: 5px 12px; font-size: 12.5px; border-radius: 6px; background: var(--bg); border: 1px solid var(--border); color: var(--text); text-decoration: none; font-weight: 500;">View</a>
                                <form method="POST" action="{{ route('v2.school.students.toggle', $student) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit"
                                            style="padding: 5px 12px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); font-weight: 500; cursor: pointer; {{ $student->status === 'active' ? 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;' : 'background:#d1fae5;color:#065f46;border-color:#6ee7b7;' }}">
                                        {{ $student->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding: 16px;">{{ $students->links() }}</div>
        </div>
    @endif
</div>
@endsection
