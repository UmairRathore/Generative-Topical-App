@extends('v2.layouts.student')
@section('page_title', 'My Performance')

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
    @media (max-width:880px){ .sx-2col{grid-template-columns:1fr !important;} }
</style>

<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Your performance overview</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Every test you've submitted, broken down by topic and subject over time.</p>
</div>

@if ($overall['tests'] === 0)
    <div class="sx-panel" style="text-align:center; padding:48px 24px; color:var(--text-faint);">
        <div style="margin-bottom:10px; display:flex; justify-content:center;"><x-icon name="chart" size="34"/></div>
        <div style="font-size:15px; font-weight:600; color:var(--text); margin-bottom:6px;">No results yet</div>
        <div style="font-size:13px;">Your stats appear here once you submit your first exam.</div>
        <a href="{{ route('v2.student.exams.index') }}" class="btn btn-primary btn-sm" style="margin-top:16px;">Go to my exams</a>
    </div>
@else
    {{-- KPI cards --}}
    <div class="sx-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 18px;">
        <div class="sx-card">
            <div class="sx-klabel"><x-icon name="clipboard" size="13"/> Exams taken</div>
            <div class="sx-kval">{{ $overall['tests'] }}</div>
        </div>
        <div class="sx-card">
            <div class="sx-klabel"><x-icon name="chart" size="13"/> Average score</div>
            <div class="sx-kval" style="color: {{ $tone($overall['avg']) }};">{{ $overall['avg'] }}%</div>
        </div>
        <div class="sx-card">
            <div class="sx-klabel"><x-icon name="trending" size="13"/> Recent trend</div>
            @if ($improvement === null)
                <div class="sx-kval" style="color: var(--text-faint);">—</div>
                <div style="font-size:11px; color:var(--text-faint); margin-top:3px;">need 10+ exams</div>
            @else
                <div class="sx-kval" style="color: {{ $improvement >= 0 ? 'var(--ok)' : 'var(--bad)' }};">
                    {{ $improvement >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($improvement, 1), '0'), '.') }}%
                </div>
                <div style="font-size:11px; color:var(--text-faint); margin-top:3px;">last 5 vs previous 5</div>
            @endif
        </div>
        <div class="sx-card">
            <div class="sx-klabel"><x-icon name="trophy" size="13"/> Best subject</div>
            @if ($bestSubject)
                <div class="sx-kval" style="font-size:19px;">{{ $bestSubject['subject'] }}</div>
                <div style="font-size:11px; color:var(--text-faint); margin-top:3px;">{{ $bestSubject['avg'] }}% average</div>
            @else
                <div class="sx-kval" style="color: var(--text-faint);">—</div>
            @endif
        </div>
    </div>

    {{-- Score trend --}}
    <div class="sx-panel" style="margin-bottom: 18px;">
        <div class="sx-ptitle">Score trend <span style="font-weight:400; color:var(--text-faint);">— last {{ count($trend) }} exams</span></div>
        <div class="sx-chart" style="height: 280px;"><canvas id="sxTrend"></canvas></div>
    </div>

    {{-- Topic performance + subject split --}}
    <div class="sx-grid sx-2col" style="grid-template-columns: 1.4fr 1fr; margin-bottom: 18px;">
        <div class="sx-panel">
            <div class="sx-ptitle">Performance by topic</div>
            @if (count($topics))
                <div class="sx-chart" style="height: {{ max(180, count($topics) * 34) }}px;"><canvas id="sxTopics"></canvas></div>
            @else
                <p style="font-size:13px; color:var(--text-faint);">No topic-tagged questions attempted yet.</p>
            @endif
        </div>
        <div class="sx-panel">
            <div class="sx-ptitle">Subject split <span style="font-weight:400; color:var(--text-faint);">— exams taken</span></div>
            <div class="sx-chart" style="height: 240px;"><canvas id="sxSubjects"></canvas></div>
        </div>
    </div>

    {{-- Monthly activity --}}
    <div class="sx-panel" style="margin-bottom: 18px;">
        <div class="sx-ptitle">Monthly activity <span style="font-weight:400; color:var(--text-faint);">— {{ now()->year }}</span></div>
        <div class="sx-chart" style="height: 220px;"><canvas id="sxMonthly"></canvas></div>
    </div>

    {{-- Exam history --}}
    <div class="sx-panel" style="padding: 0; overflow: hidden;">
        <div class="sx-ptitle" style="padding: 16px 20px 0;">Exam history</div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
            <thead><tr style="background: var(--soft-surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
                @foreach (['Exam', 'Subject', 'Topic', 'Date', 'Score', 'Trend'] as $h)
                    <th style="padding: var(--pad-cell); text-align: {{ in_array($h, ['Score','Trend']) ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach ($history->take(15) as $t)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">
                            <a href="{{ route('v2.student.exams.result', hid($t['exam_id'])) }}" style="color: var(--text); text-decoration: none;">{{ $t['title'] }}</a>
                        </td>
                        <td style="padding: var(--pad-cell); font-size: 13px; color: var(--text-soft);">{{ $t['subject'] }}</td>
                        <td style="padding: var(--pad-cell);"><span class="badge badge-emerald">{{ $t['topic'] }}</span></td>
                        <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-faint);">{{ \Illuminate\Support\Carbon::parse($t['date'])->format('d M Y') }}</td>
                        <td style="padding: var(--pad-cell); text-align: right; font-size: 13px; font-weight: 600; color: {{ $tone($t['percent']) }};">{{ $t['score'] }}/{{ $t['total'] }}</td>
                        <td style="padding: var(--pad-cell); text-align: right;">
                            @if ($t['trend'] > 0)
                                <span style="color: var(--ok); font-weight: 700;">&uarr;</span>
                            @elseif ($t['trend'] < 0)
                                <span style="color: var(--bad); font-weight: 700;">&darr;</span>
                            @else
                                <span style="color: var(--text-faint);">&rarr;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('v2.partials.chartjs')
    <script>
    (function () {
        var t = V2.theme();

        // 1) Score trend — line
        var trend = @json($trend);
        new Chart(document.getElementById('sxTrend'), {
            type: 'line',
            data: {
                labels: trend.map(function (e) { return e.date || e.label; }),
                datasets: [{
                    label: 'Score %',
                    data: trend.map(function (e) { return e.score; }),
                    borderColor: t.primary,
                    backgroundColor: V2.alpha(t.primary, 0.08),
                    borderWidth: 2.5,
                    pointBackgroundColor: t.accent,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false } } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: {
                        title: function (i) { return trend[i[0].dataIndex].label; },
                        label: function (c) { return 'Score: ' + c.raw + '%' + (trend[c.dataIndex].subject ? ' · ' + trend[c.dataIndex].subject : ''); }
                    } }
                }
            }
        });

        // 2) Topic performance — horizontal bar
        var topics = @json($topics);
        if (topics.length) {
            new Chart(document.getElementById('sxTopics'), {
                type: 'bar',
                data: {
                    labels: topics.map(function (x) { return x.topic; }),
                    datasets: [{
                        data: topics.map(function (x) { return x.percent; }),
                        backgroundColor: topics.map(function (x) { return V2.tone(x.percent); }),
                        borderRadius: 4,
                        barThickness: 18,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    scales: { x: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: V2.alpha(t.border, 0.6) } }, y: { grid: { display: false } } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function (c) { var x = topics[c.dataIndex]; return x.correct + '/' + x.total + ' · ' + x.percent + '%'; } } }
                    }
                }
            });
        }

        // 3) Subject split — doughnut
        var subjects = @json($subjects->map(fn ($s) => ['name' => $s['subject'], 'count' => $s['tests_count']])->values());
        var palette = [t.primary, t.accent, t.ok, t.warn, t.bad];
        new Chart(document.getElementById('sxSubjects'), {
            type: 'doughnut',
            data: {
                labels: subjects.map(function (s) { return s.name; }),
                datasets: [{
                    data: subjects.map(function (s) { return s.count; }),
                    backgroundColor: subjects.map(function (s, i) { return palette[i % palette.length]; }),
                    borderWidth: 2,
                    borderColor: t.surface,
                }]
            },
            options: { cutout: '62%', plugins: { legend: { position: 'bottom' } } }
        });

        // 4) Monthly activity — bar
        new Chart(document.getElementById('sxMonthly'), {
            type: 'bar',
            data: {
                labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                datasets: [{ label: 'Exams taken', data: @json($monthly), backgroundColor: t.primary, borderRadius: 4 }]
            },
            options: {
                scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });
    })();
    </script>
@endif
@endsection
