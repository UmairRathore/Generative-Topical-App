@extends('v2.layouts.school_admin')
@section('page_title', 'Subjects')

@section('content')
<div>
    <p style="color: var(--muted); font-size: 14px; margin-bottom: 24px;">
        Assign global subjects to the grades in your school. Each combination creates a subject slot that classes can be built around.
    </p>

    @if($grades->isEmpty())
        <div style="text-align: center; padding: 60px 0; color: var(--muted);">
            <p>You need to create grades before you can assign subjects.</p>
            <a href="{{ route('v2.school.grades.create') }}" style="display: inline-block; margin-top: 12px; padding: 9px 18px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none;">Add Grades</a>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg); border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Subject</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Code</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Level</th>
                        @foreach($grades as $grade)
                            <th style="padding: 12px 16px; text-align: center; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">{{ $grade->short_name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($allSubjects as $subject)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 13px 16px; font-size: 13.5px; font-weight: 500; color: var(--text);">{{ $subject->name }}</td>
                        <td style="padding: 13px 16px; font-size: 12px; color: var(--muted);">{{ $subject->code }}</td>
                        <td style="padding: 13px 16px; font-size: 12px; color: var(--muted);">{{ $subject->level }}</td>
                        @foreach($grades as $grade)
                            @php $assigned = $assignedMatrix[$subject->id][$grade->id] ?? null; @endphp
                            <td style="padding: 13px 16px; text-align: center;">
                                @if($assigned)
                                    <form method="POST" action="{{ route('v2.school.subjects.remove') }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                        <input type="hidden" name="grade_id" value="{{ $grade->id }}">
                                        <button type="submit"
                                                style="width: 28px; height: 28px; border-radius: 50%; background: #d1fae5; border: 1.5px solid #6ee7b7; color: #065f46; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;"
                                                title="Remove from {{ $grade->short_name }}">
                                            ✓
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('v2.school.subjects.assign') }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                        <input type="hidden" name="grade_id" value="{{ $grade->id }}">
                                        <button type="submit"
                                                style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg); border: 1.5px dashed var(--border); color: var(--muted); cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; justify-content: center;"
                                                title="Assign to {{ $grade->short_name }}">
                                            +
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
