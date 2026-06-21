@extends('v2.layouts.super_admin')
@section('page_title', 'Platform Analytics')

@php
    $riskBadge = [
        'low'    => ['var(--ok)',   'Low'],
        'medium' => ['var(--warn)', 'Medium'],
        'high'   => ['var(--bad)',  'High'],
    ];
@endphp

@section('content')
<style>
    .sx-grid{display:grid;gap:14px;}
    .sx-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:14px 16px;}
    .sx-klabel{display:flex;align-items:center;gap:6px;color:var(--text-faint);font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
    .sx-kval{font-size:21px;font-weight:600;margin-top:6px;font-family:var(--font-serif,serif);}
    .sx-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px 20px;}
    .sx-ptitle{font-size:13px;font-weight:600;margin-bottom:14px;}
    .sx-chart{position:relative;width:100%;}
    .sx-pill{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;color:#fff;}
    @media (max-width:980px){ .sx-2col{grid-template-columns:1fr !important;} .sx-8{grid-template-columns:repeat(2,1fr) !important;} }
</style>

<div style="margin-bottom: 22px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Platform overview</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Business and engagement metrics across every school on the platform.</p>
</div>

{{-- KPI cards --}}
<div class="sx-grid sx-8" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 18px;">
    @foreach ([
        ['Active schools', number_format($stats['schools']), 'school'],
        ['Branches', number_format($stats['branches']), 'layers'],
        ['Teachers', number_format($stats['teachers']), 'user'],
        ['Students', number_format($stats['students']), 'users'],
        ['Questions', number_format($stats['questions']), 'book'],
        ['Exams', number_format($stats['exams']), 'clipboard'],
        ['Submissions', number_format($stats['submissions']), 'check'],
        ['MRR', 'Rs ' . number_format($stats['mrr']), 'trending'],
    ] as [$label, $value, $icon])
        <div class="sx-card">
            <div class="sx-klabel"><x-icon :name="$icon" size="12"/> {{ $label }}</div>
            <div class="sx-kval">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- MRR + growth --}}
<div class="sx-panel" style="margin-bottom: 18px;">
    <div class="sx-ptitle">Monthly recurring revenue <span style="font-weight:400; color:var(--text-faint);">— last 12 months (estimated)</span></div>
    <div class="sx-chart" style="height: 240px;"><canvas id="paMrr"></canvas></div>
</div>

<div class="sx-panel" style="margin-bottom: 18px;">
    <div class="sx-ptitle">Platform growth <span style="font-weight:400; color:var(--text-faint);">— new accounts per month</span></div>
    <div class="sx-chart" style="height: 250px;"><canvas id="paGrowth"></canvas></div>
</div>

{{-- School engagement --}}
<div class="sx-panel" style="padding: 0; overflow: hidden; margin-bottom: 18px;">
    <div class="sx-ptitle" style="padding: 16px 20px 0;">School engagement <span style="font-weight:400; color:var(--text-faint);">— this month</span></div>
    <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
        <thead><tr style="background: var(--soft-surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
            @foreach (['School', 'MRR', 'Exams/mo', 'Reports/mo', 'Last exam', 'Churn risk'] as $h)
                <th style="padding: var(--pad-cell); text-align: {{ in_array($h, ['MRR','Exams/mo','Reports/mo']) ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse ($engagement as $s)
                @php [$riskColor, $riskLabel] = $riskBadge[$s['risk']]; @endphp
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">{{ $s['name'] }}</td>
                    <td style="padding: var(--pad-cell); text-align: right; font-size: 13px;">Rs {{ number_format($s['fee']) }}</td>
                    <td style="padding: var(--pad-cell); text-align: right; font-size: 13px;">{{ $s['exams'] }}</td>
                    <td style="padding: var(--pad-cell); text-align: right; font-size: 13px;">{{ $s['reports'] }}</td>
                    <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-faint);">{{ $s['last_exam'] ? $s['last_exam']->diffForHumans() : 'never' }}</td>
                    <td style="padding: var(--pad-cell);"><span class="sx-pill" style="background: {{ $riskColor }};">{{ $riskLabel }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding: 28px; text-align: center; color: var(--text-faint); font-size: 13px;">No active schools.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Daily activity --}}
<div class="sx-panel" style="margin-bottom: 18px;">
    <div class="sx-ptitle">Daily activity <span style="font-weight:400; color:var(--text-faint);">— submissions, last 30 days</span></div>
    <div class="sx-chart" style="height: 200px;"><canvas id="paDaily"></canvas></div>
</div>

{{-- Top topics + hardest questions --}}
<div class="sx-grid sx-2col" style="grid-template-columns: 1fr 1fr;">
    <div class="sx-panel">
        <div class="sx-ptitle">Most-attempted topics</div>
        @if (count($topTopics))
            <div class="sx-chart" style="height: {{ max(180, count($topTopics) * 30) }}px;"><canvas id="paTopics"></canvas></div>
        @else
            <p style="font-size:13px; color:var(--text-faint);">No attempts yet.</p>
        @endif
    </div>
    <div class="sx-panel" style="padding: 0; overflow: hidden;">
        <div class="sx-ptitle" style="padding: 18px 20px 0;">Hardest questions <span style="font-weight:400; color:var(--text-faint);">— lowest correct rate</span></div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
            <thead><tr style="background: var(--soft-surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
                @foreach (['Question', 'Topic', 'Attempts', 'Correct'] as $h)
                    <th style="padding: 9px 14px; text-align: {{ in_array($h, ['Attempts','Correct']) ? 'right' : 'left' }}; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">{{ $h }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($hardest as $q)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 9px 14px; font-size: 12.5px;">{{ $q['source'] }} · Q{{ $q['qno'] }}</td>
                        <td style="padding: 9px 14px; font-size: 12px; color: var(--text-soft);">{{ \Illuminate\Support\Str::limit($q['topic'], 18) }}</td>
                        <td style="padding: 9px 14px; text-align: right; font-size: 12.5px;">{{ $q['attempts'] }}</td>
                        <td style="padding: 9px 14px; text-align: right; font-size: 12.5px; font-weight: 600; color: var(--bad);">{{ $q['correct'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 24px; text-align: center; color: var(--text-faint); font-size: 13px;">Not enough attempts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('v2.partials.chartjs')
<script>
(function () {
    var t = V2.theme();
    var money = function (v) { return 'Rs ' + Number(v).toLocaleString(); };

    // 1) MRR trend — area
    var mrr = @json($mrrTrend);
    new Chart(document.getElementById('paMrr'), {
        type: 'line',
        data: {
            labels: mrr.map(function (e) { return e.label; }),
            datasets: [{ label: 'MRR', data: mrr.map(function (e) { return e.mrr; }), borderColor: t.ok, backgroundColor: V2.alpha(t.ok, 0.12), borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: t.ok, tension: 0.4, fill: true }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return v >= 1000 ? 'Rs ' + (v / 1000) + 'k' : 'Rs ' + v; } }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false } } },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return money(c.raw); } } } }
        }
    });

    // 2) Platform growth — multi-line (students/exams on right axis)
    var g = @json($growth);
    new Chart(document.getElementById('paGrowth'), {
        type: 'line',
        data: {
            labels: g.labels,
            datasets: [
                { label: 'Schools',  data: g.schools,  borderColor: t.primary, backgroundColor: t.primary, yAxisID: 'y',  tension: 0.3, pointRadius: 2 },
                { label: 'Teachers', data: g.teachers, borderColor: t.accent,  backgroundColor: t.accent,  yAxisID: 'y',  tension: 0.3, pointRadius: 2 },
                { label: 'Students', data: g.students, borderColor: t.warn,    backgroundColor: t.warn,    yAxisID: 'y1', tension: 0.3, pointRadius: 2, borderDash: [4, 3] },
                { label: 'Exams',    data: g.exams,    borderColor: t.ok,      backgroundColor: t.ok,      yAxisID: 'y1', tension: 0.3, pointRadius: 2, borderDash: [4, 3] }
            ]
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: {
                y:  { position: 'left',  beginAtZero: true, ticks: { precision: 0 }, grid: { color: V2.alpha(t.border, 0.6) }, title: { display: true, text: 'schools / teachers' } },
                y1: { position: 'right', beginAtZero: true, ticks: { precision: 0 }, grid: { drawOnChartArea: false }, title: { display: true, text: 'students / exams' } },
                x:  { grid: { display: false } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 3) Daily activity — bar (weekends lighter)
    var daily = @json($daily);
    new Chart(document.getElementById('paDaily'), {
        type: 'bar',
        data: {
            labels: daily.map(function (d) { return d.label; }),
            datasets: [{ label: 'Submissions', data: daily.map(function (d) { return d.count; }), backgroundColor: daily.map(function (d) { return d.weekend ? V2.alpha(t.primary, 0.3) : t.primary; }), borderRadius: 3 }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: V2.alpha(t.border, 0.6) } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 10, autoSkip: true } } },
            plugins: { legend: { display: false } }
        }
    });

    // 4) Most-attempted topics — horizontal bar
    var topics = @json($topTopics);
    if (topics.length) {
        new Chart(document.getElementById('paTopics'), {
            type: 'bar',
            data: {
                labels: topics.map(function (x) { return x.topic; }),
                datasets: [{ label: 'Attempts', data: topics.map(function (x) { return x.total; }), backgroundColor: t.accent, borderRadius: 4, barThickness: 16 }]
            },
            options: {
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: V2.alpha(t.border, 0.6) } }, y: { grid: { display: false } } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { var x = topics[c.dataIndex]; return x.total + ' attempts · ' + x.percent + '% correct'; } } } }
            }
        });
    }
})();
</script>
@endsection
