@extends('v2.layouts.school_admin')
@section('page_title', isset($isReset) ? 'Password Reset' : 'Student Credentials')

@section('content')
<div style="max-width: 640px;">

    <div style="background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; gap: 14px; align-items: flex-start;">
        <x-icon name="alert-triangle" size="22" style="color: #d97706; flex: none; margin-top: 2px;" />
        <div>
            <div style="font-weight: 700; color: #92400e; font-size: 14px;">Show this only once</div>
            <div style="font-size: 13px; color: #78350f; margin-top: 4px;">These credentials will not be accessible again. Save or print them now before navigating away.</div>
        </div>
    </div>

    @if($student)
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <div style="margin-bottom: 20px;">
            <div style="font-size: 13px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px;">Student</div>
            <div style="font-size: 18px; font-weight: 600; color: var(--text);">{{ $student->name }}</div>
        </div>

        <div class="space-y-4">
            <div>
                <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px;">Login Email</div>
                <div style="font-family: monospace; font-size: 15px; padding: 12px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text);">
                    {{ $student->email }}
                </div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px;">Temporary Password</div>
                <div style="font-family: monospace; font-size: 20px; font-weight: 700; padding: 12px 16px; background: var(--bg); border: 2px dashed var(--border); border-radius: 8px; color: var(--text); letter-spacing: .08em;">
                    {{ $plainPassword }}
                </div>
            </div>
        </div>

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); display: flex; gap: 12px;">
            <a href="{{ route('v2.school.students.index') }}"
               style="flex: 1; display: block; text-align: center; padding: 10px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13.5px; font-weight: 500; text-decoration: none;">
                Done
            </a>
            <a href="{{ route('v2.school.students.create') }}"
               style="flex: 1; display: block; text-align: center; padding: 10px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none;">
                Add Another Student
            </a>
        </div>
    </div>

    @else
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--bg); border-bottom: 1px solid var(--border);">
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">#</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Email</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Temp Password</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bulk as $i => $row)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 12px 16px; font-size: 13px; color: var(--muted);">{{ $i + 1 }}</td>
                    <td style="padding: 12px 16px; font-size: 14px; font-weight: 500; color: var(--text);">{{ $row['student']->name }}</td>
                    <td style="padding: 12px 16px; font-size: 13px; color: var(--muted);">{{ $row['student']->email }}</td>
                    <td style="padding: 12px 16px; font-family: monospace; font-size: 14px; font-weight: 700; color: var(--text);">{{ $row['password'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px; display: flex; gap: 12px;">
        <a href="{{ route('v2.school.students.index') }}"
           style="flex: 1; display: block; text-align: center; padding: 10px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font-size: 13.5px; font-weight: 500; text-decoration: none;">
            Done
        </a>
        <button onclick="window.print()"
                style="flex: 1; padding: 10px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; border: none; cursor: pointer;">
            Print / Save PDF
        </button>
    </div>
    @endif

</div>
@endsection
