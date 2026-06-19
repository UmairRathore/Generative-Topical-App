@extends('v2.layouts.school_admin')
@section('page_title', 'Classes')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <p style="color: var(--muted); font-size: 14px;">{{ $classes->count() }} class(es) configured.</p>
        <a href="{{ route('v2.school.classes.create') }}"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none;">
            <x-icon name="plus" size="16" /> Add Class
        </a>
    </div>

    @if($classes->isEmpty())
        <div style="text-align: center; padding: 60px 0; color: var(--muted);">
            <x-icon name="calendar" size="40" style="opacity:.3; margin: 0 auto 12px;" />
            <p>No classes yet.</p>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg); border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Grade / Section</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Subject</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Teachers</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Status</th>
                        <th style="padding: 12px 16px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($classes as $class)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 13px 16px; font-size: 14px; font-weight: 500; color: var(--text);">
                            {{ $class->grade?->name ?? '-' }}{{ $class->section ? " · {$class->section}" : '' }}
                        </td>
                        <td style="padding: 13px 16px; font-size: 13px; color: var(--muted);">{{ $class->subject?->name ?? '-' }}</td>
                        <td style="padding: 13px 16px; font-size: 13px; color: var(--muted);">{{ $class->classTeachers->count() }}</td>
                        <td style="padding: 13px 16px;">
                            <span style="display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; {{ $class->is_active ? 'background:#d1fae5;color:#065f46;' : 'background:#f3f4f6;color:#6b7280;' }}">
                                {{ $class->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td style="padding: 13px 16px; text-align: right;">
                            <a href="{{ route('v2.school.classes.show', $class) }}"
                               style="padding: 5px 14px; font-size: 12.5px; border-radius: 6px; background: var(--bg); border: 1px solid var(--border); color: var(--text); text-decoration: none; font-weight: 500;">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
