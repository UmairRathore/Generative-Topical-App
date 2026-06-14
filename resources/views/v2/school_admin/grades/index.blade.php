@extends('v2.layouts.school_admin')
@section('page_title', 'Grades')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <p style="color: var(--muted); font-size: 14px;">{{ $grades->count() }} grade(s) configured for your school.</p>
        <a href="{{ route('v2.school.grades.create') }}"
           style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none;">
            <x-icon name="plus" size="16" /> Add Grade
        </a>
    </div>

    @if($grades->isEmpty())
        <div style="text-align: center; padding: 60px 0; color: var(--muted);">
            <x-icon name="layers" size="40" style="opacity:.3; margin: 0 auto 12px;" />
            <p>No grades yet. Add your first grade to get started.</p>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg); border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Name</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Short</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Order</th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">Status</th>
                        <th style="padding: 12px 16px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grades as $grade)
                    <tr style="border-bottom: 1px solid var(--border); transition: background .1s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 14px 16px; font-size: 14px; font-weight: 500; color: var(--text);">{{ $grade->name }}</td>
                        <td style="padding: 14px 16px; font-size: 13px; color: var(--muted);">{{ $grade->short_name }}</td>
                        <td style="padding: 14px 16px; font-size: 13px; color: var(--muted);">{{ $grade->sort_order }}</td>
                        <td style="padding: 14px 16px;">
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; {{ $grade->is_active ? 'background:#d1fae5;color:#065f46;' : 'background:#f3f4f6;color:#6b7280;' }}">
                                {{ $grade->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td style="padding: 14px 16px; text-align: right;">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('v2.school.grades.edit', $grade) }}"
                                   style="padding: 5px 12px; font-size: 12.5px; border-radius: 6px; background: var(--bg); border: 1px solid var(--border); color: var(--text); text-decoration: none; font-weight: 500;">Edit</a>
                                <form method="POST" action="{{ route('v2.school.grades.toggle', $grade) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit"
                                            style="padding: 5px 12px; font-size: 12.5px; border-radius: 6px; border: 1px solid var(--border); font-weight: 500; cursor: pointer; {{ $grade->is_active ? 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;' : 'background:#d1fae5;color:#065f46;border-color:#6ee7b7;' }}">
                                        {{ $grade->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
