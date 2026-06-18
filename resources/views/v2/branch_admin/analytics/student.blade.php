@extends('v2.layouts.branch_admin')
@section('page_title', 'Student Analytics')

@php $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<a href="{{ route('v2.branch.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Analytics
</a>

<div class="flex items-start justify-between" style="margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $student->name }}</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Roll {{ $student->roll_number }}@if ($student->email) · {{ $student->email }}@endif</p>
    </div>
    {{-- AI progress report — generates + saves a JSON record; PDF is rendered on demand --}}
    <form method="POST" action="{{ route('v2.branch.report.generate', $student) }}" class="flex items-center gap-2" style="flex-wrap: wrap;">
        @csrf
        <select name="duration" class="select" style="width: auto; padding: 7px 12px; font-size: 13px;">
            <option value="monthly">Monthly (last month)</option>
            <option value="quarter">Last 3 months</option>
            <option value="year">This year</option>
            <option value="all">All-time</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><x-icon name="sparkle" size="13"/> Generate report</button>
    </form>
</div>

{{-- Saved reports (PDF rendered from the stored JSON) --}}
@if ($reports->isNotEmpty())
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 24px;">
        <div style="padding: 12px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Progress reports</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <tbody>
                @foreach ($reports as $r)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td data-label="Period" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ ucfirst($r->period_label) }}</td>
                        <td data-label="Score" style="padding: var(--pad-cell); font-size: 13px;">{{ $r->overall_avg !== null ? $r->overall_avg.'%' : '—' }} · {{ $r->tests_count }} {{ \Illuminate\Support\Str::plural('test', $r->tests_count) }}</td>
                        <td data-label="Source" style="padding: var(--pad-cell); font-size: 12px; color: var(--text-soft);">{{ $r->source === 'openai' ? 'AI (OpenAI)' : 'Generated' }}</td>
                        <td data-label="Generated" style="padding: var(--pad-cell); font-size: 12px; color: var(--text-faint);">{{ $r->created_at->diffForHumans() }}</td>
                        <td data-label="" style="padding: var(--pad-cell); text-align: right;">
                            <a href="{{ route('v2.branch.report.pdf', $r) }}" class="btn btn-ghost btn-sm"><x-icon name="download" size="12"/> PDF</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($stats['overall']['tests'] === 0)
    <div style="padding: 40px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
        This student hasn't completed any tests yet.
    </div>
@else
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px;">
        @foreach ([
            ['Tests completed', $stats['overall']['tests'], 'clipboard'],
            ['Overall average', $stats['overall']['avg'].'%', 'chart'],
            ['Subjects', count($stats['subjects']), 'book'],
        ] as [$label, $value, $icon])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                    <x-icon :name="$icon" size="13"/> {{ $label }}
                </div>
                <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($stats['subjects'] as $subject)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; margin-bottom: 18px;">
            <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                <span class="badge badge-emerald" style="font-size: 13px; font-weight: 700;">{{ $subject['avg'] }}%</span>
            </div>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">By topic</div>
                    @include('v2.partials.topic_bars', ['stats' => $subject['topics'], 'empty' => 'No topic data.'])
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                    <div class="space-y-2">
                        @foreach ($subject['tests'] as $test)
                            <a href="{{ route('v2.branch.student_paper', [hid($test['exam_id']), $student]) }}" class="flex items-center justify-between" style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; text-decoration: none; color: inherit;">
                                <div style="min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                    <div style="font-size: 11.5px; color: var(--text-faint);">{{ $test['topic'] }} · {{ $test['date']?->diffForHumans() }}</div>
                                </div>
                                <div style="text-align: right; flex: none;">
                                    <span style="font-weight: 700; font-size: 14px; color: {{ $tone($test['percent']) }};">{{ $test['percent'] }}%</span>
                                    <div style="font-size: 11px; color: var(--text-faint);">{{ $test['score'] }}/{{ $test['total'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
