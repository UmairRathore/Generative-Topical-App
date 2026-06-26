@extends('v2.layouts.teacher')
@section('page_title', 'Class Analytics')

@php
    $tone = fn ($p) => $p >= 70 ? 'var(--ok)' : ($p >= 50 ? 'var(--warn)' : 'var(--bad)');
@endphp

@section('content')
<style>
    .sx-grid{display:grid;gap:14px;}
    .sx-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px 18px;}
    .sx-klabel{display:flex;align-items:center;gap:6px;color:var(--text-faint);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
    .sx-kval{font-size:24px;font-weight:600;margin-top:7px;font-family:var(--font-serif,serif);}
    .sx-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px 20px;}
    .sx-ptitle{font-size:13px;font-weight:600;margin-bottom:14px;}
    .sx-chart{position:relative;width:100%;}
    .sx-alert{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:8px;margin-bottom:8px;font-size:13px;}
    @media (max-width:880px){ .sx-2col{grid-template-columns:1fr !important;} .sx-6{grid-template-columns:repeat(2,1fr) !important;} }
</style>

<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Class performance overview</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Across the exams you've created and the classes you teach.</p>
</div>

@if ($overview['exams'] === 0)
    <div class="sx-panel" style="text-align:center; padding:48px 24px; color:var(--text-faint);">
        <div style="margin-bottom:10px; display:flex; justify-content:center;"><x-icon name="chart" size="34"/></div>
        <div style="font-size:15px; font-weight:600; color:var(--text); margin-bottom:6px;">No analytics yet</div>
        <div style="font-size:13px;">Create and release an exam - once students submit, their results show up here.</div>
        <a href="{{ route('v2.teacher.exams.create') }}" class="btn btn-primary btn-sm" style="margin-top:16px;">Create an exam</a>
    </div>
@else
    {{-- KPI cards --}}
    <div class="sx-grid sx-6" style="grid-template-columns: repeat(6, 1fr); margin-bottom: 18px;">
        @foreach ([
            ['Exams made', $overview['exams'], 'clipboard', null],
            ['Submissions', $overview['submissions'], 'check', null],
            ['Avg score', $overview['avg'] !== null ? $overview['avg'].'%' : '-', 'chart', $overview['avg']],
            ['Completion', $overview['completion'].'%', 'trending', null],
            ['This month', $overview['exams_this_month'], 'calendar', null],
            ['Needs attention', $needsAttention->count(), 'flag', null],
        ] as [$label, $value, $icon, $pct])
            <div class="sx-card">
                <div class="sx-klabel"><x-icon :name="$icon" size="13"/> {{ $label }}</div>
                <div class="sx-kval" @if($pct !== null) style="color: {{ $tone($pct) }};" @elseif($label === 'Needs attention' && $value > 0) style="color: var(--bad);" @endif>{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Needs attention --}}
    @if ($needsAttention->count())
        <div class="sx-panel" style="margin-bottom: 18px;">
            <div class="sx-ptitle">Needs attention</div>
            @foreach ($needsAttention->take(6) as $s)
                @php $danger = $s['avg'] !== null && $s['avg'] < 40; @endphp
                <div class="sx-alert" style="background: {{ $danger ? 'var(--bad-soft)' : 'var(--soft-surface)' }}; border-left: 3px solid {{ $danger ? 'var(--bad)' : 'var(--warn)' }};">
                    <x-icon name="flag" size="14"/>
                    <span style="flex:1;"><strong>{{ $s['name'] }}</strong>
                        @if ($s['attempts'] === 0)
                            <span style="color: var(--text-soft);">- hasn't attempted any of your exams yet</span>
                        @else
                            <span style="color: var(--text-soft);">- {{ $s['avg'] }}% average across {{ $s['attempts'] }} {{ \Illuminate\Support\Str::plural('exam', $s['attempts']) }}</span>
                        @endif
                    </span>
                    <a href="{{ route('v2.teacher.students.show', hid($s['id'])) }}" class="btn btn-ghost btn-sm">View</a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Class comparison + score distribution --}}
    <div class="sx-grid sx-2col" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">
        <div class="sx-panel">
            <div class="sx-ptitle">Class comparison <span style="font-weight:400; color:var(--text-faint);">- average score</span></div>
            <div class="sx-chart" style="height: 240px;"><canvas id="txClasses"></canvas></div>
        </div>
        <div class="sx-panel">
            <div class="sx-ptitle">Score distribution <span style="font-weight:400; color:var(--text-faint);">- students by band</span></div>
            <div class="sx-chart" style="height: 240px;"><canvas id="txDist"></canvas></div>
        </div>
    </div>

    {{-- Topic performance --}}
    <div class="sx-panel" style="margin-bottom: 18px;">
        <div class="sx-ptitle">Performance by topic <span style="font-weight:400; color:var(--text-faint);">- all your classes</span></div>
        @if (count($topics))
            <div class="sx-chart" style="height: {{ max(180, count($topics) * 30) }}px;"><canvas id="txTopics"></canvas></div>
        @else
            <p style="font-size:13px; color:var(--text-faint);">No topic-tagged results yet.</p>
        @endif
    </div>

    {{-- Exam trend --}}
    <div class="sx-panel" style="margin-bottom: 18px;">
        <div class="sx-ptitle">Exam performance trend <span style="font-weight:400; color:var(--text-faint);">- avg score & submissions</span></div>
        <div class="sx-chart" style="height: 260px;"><canvas id="txTrend"></canvas></div>
    </div>

    {{-- Student table --}}
    <div class="sx-panel" style="padding: 0; overflow: hidden;">
        <div class="sx-ptitle" style="padding: 16px 20px 0;">Students</div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
            <thead><tr style="background: var(--soft-surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
                @foreach (['Student', 'Roll', 'Exams', 'Average', ''] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ in_array($h, ['Exams','Average']) ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($students as $s)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $s['name'] }}</td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-faint);">{{ $s['roll'] }}</td>
                        <td style="padding: var(--pad-cell); text-align: right; font-size: 13px;">{{ $s['attempts'] }}</td>
                        <td style="padding: var(--pad-cell); text-align: right; font-size: 13px; font-weight: 600; color: {{ $s['avg'] !== null ? $tone($s['avg']) : 'var(--text-faint)' }};">{{ $s['avg'] !== null ? $s['avg'].'%' : '-' }}</td>
                        <td style="padding: var(--pad-cell); text-align: right;"><a href="{{ route('v2.teacher.students.show', hid($s['id'])) }}" class="btn btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No students enrolled yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('v2.partials.chartjs')
    <script>
    (function () {
        var t = V2.theme();

        // 1) Class comparison - bar
        var classes = @json(collect($classes)->map(fn ($c) => ['name' => $c['name'], 'avg' => $c['avg'] ?? 0])->values());
        new Chart(document.getElementById('txClasses'), {
            type: 'bar',
            data: {
                labels: classes.map(function (c) { return c.name; }),
                datasets: [{ data: classes.map(function (c) { return c.avg; }), backgroundColor: classes.map(function (c) { return V2.tone(c.avg); }), borderRadius: 6 }]
            },
            options: {
                scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });

        // 2) Score distribution - histogram
        new Chart(document.getElementById('txDist'), {
            type: 'bar',
            data: {
                labels: ['0-20','21-40','41-60','61-80','81-100'],
                datasets: [{ label: 'Students', data: @json($distribution), backgroundColor: [t.bad, t.warn, t.accent, V2.alpha(t.ok, 0.7), t.ok], borderRadius: 4 }]
            },
            options: {
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });

        // 3) Topic performance - horizontal bar
        var topics = @json($topics);
        if (topics.length) {
            new Chart(document.getElementById('txTopics'), {
                type: 'bar',
                data: {
                    labels: topics.map(function (x) { return x.topic; }),
                    datasets: [{ data: topics.map(function (x) { return x.percent; }), backgroundColor: topics.map(function (x) { return V2.tone(x.percent); }), borderRadius: 4, barThickness: 16 }]
                },
                options: {
                    indexAxis: 'y',
                    scales: { x: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: V2.alpha(t.border, 0.6) } }, y: { grid: { display: false } } },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { var x = topics[c.dataIndex]; return x.correct + '/' + x.total + ' · ' + x.percent + '%'; } } } }
                }
            });
        }

        // 4) Exam trend - combo (line avg + bar submissions)
        var trend = @json($examTrend);
        new Chart(document.getElementById('txTrend'), {
            data: {
                labels: trend.map(function (e) { return e.date; }),
                datasets: [
                    { type: 'line', label: 'Avg score %', data: trend.map(function (e) { return e.avg; }), borderColor: t.bad, backgroundColor: V2.alpha(t.bad, 0.08), pointBackgroundColor: t.bad, pointRadius: 3, tension: 0.3, yAxisID: 'y', spanGaps: true },
                    { type: 'bar', label: 'Submissions', data: trend.map(function (e) { return e.count; }), backgroundColor: V2.alpha(t.primary, 0.18), borderRadius: 3, yAxisID: 'y1' }
                ]
            },
            options: {
                scales: {
                    y:  { position: 'left', min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: V2.alpha(t.border, 0.6) } },
                    y1: { position: 'right', beginAtZero: true, ticks: { precision: 0 }, grid: { drawOnChartArea: false } },
                    x:  { grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { title: function (i) { return trend[i[0].dataIndex].title; } } }
                }
            }
        });
    })();
    </script>
@endif
@endsection
