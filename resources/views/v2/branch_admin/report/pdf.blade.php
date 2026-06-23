<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #061C30; font-size: 12px; line-height: 1.5; }
        .wrap { padding: 0 34px 40px; }
        .band { background: #061C30; color: #ffffff; padding: 22px 34px; }
        .band .eyebrow { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #9DB3C6; }
        .band h1 { font-size: 21px; margin: 4px 0 2px; font-weight: normal; }
        .band .sub { font-size: 11px; color: #C8D5E0; }
        .card { border: 1px solid #E2E4EA; border-radius: 8px; padding: 14px 16px; margin-top: 16px; }
        .muted { color: #5C6470; }
        .h2 { font-size: 13px; font-weight: bold; margin: 0 0 8px; }
        .kpi { font-size: 26px; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .bartrack { background: #EEF0F2; height: 8px; width: 100%; border-radius: 6px; }
        .barfill { height: 8px; border-radius: 6px; }
        .summary { background: #F2F6FB; border-left: 3px solid #0097D3; padding: 12px 14px; border-radius: 4px; font-size: 12px; }
        .foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #E2E4EA; font-size: 10px; color: #8A93A0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
@php
    $tone = fn ($p) => $p >= 60 ? '#5FA052' : ($p >= 40 ? '#D9952B' : '#C64C44');
@endphp

<div class="band">
    <div class="eyebrow">{{ $school?->name ?? 'School' }} &middot; {{ $branch?->name ?? 'Branch' }}</div>
    <h1>Student Progress Report</h1>
    <div class="sub">{{ $student->name }} @if($student->roll_number)&middot; Roll {{ $student->roll_number }}@endif &middot; {{ ucfirst($period) }}</div>
</div>

<div class="wrap">

    {{-- Overview --}}
    <div class="card">
        <table>
            <tr>
                <td style="width:33%;">
                    <div class="muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px;">Overall average</div>
                    <div class="kpi" style="color: {{ $stats['overall']['tests'] ? $tone($stats['overall']['avg']) : '#8A93A0' }};">{{ $stats['overall']['tests'] ? $stats['overall']['avg'].'%' : '-' }}</div>
                </td>
                <td style="width:33%;">
                    <div class="muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px;">Tests completed</div>
                    <div class="kpi">{{ $stats['overall']['tests'] }}</div>
                </td>
                <td style="width:34%;">
                    <div class="muted" style="font-size:10px; text-transform:uppercase; letter-spacing:1px;">Subjects</div>
                    <div class="kpi">{{ count($stats['subjects']) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- AI / generated summary --}}
    <div class="card">
        <div class="h2">Summary</div>
        <div class="summary">{{ $narrative['summary'] }}</div>
    </div>

    @if ($stats['overall']['tests'] === 0)
        <div class="card muted" style="text-align:center;">No tests were completed in this period.</div>
    @else
        @foreach ($stats['subjects'] as $subject)
            <div class="card">
                <table style="margin-bottom: 10px;">
                    <tr>
                        <td><span class="h2" style="font-size:15px;">{{ $subject['subject'] }}</span></td>
                        <td style="text-align:right;">
                            <span class="badge" style="background:{{ $tone($subject['avg']) }}; color:#ffffff;">{{ $subject['avg'] }}%</span>
                        </td>
                    </tr>
                </table>

                {{-- per-topic bars --}}
                <table>
                    @foreach ($subject['topics'] as $t)
                        <tr>
                            <td style="width:40%; font-size:11px; padding:3px 8px 3px 0;">{{ $t['topic'] }}</td>
                            <td style="width:45%; padding:3px 0;">
                                <div class="bartrack"><div class="barfill" style="width: {{ max($t['percent'], 2) }}%; background: {{ $tone($t['percent']) }};"></div></div>
                            </td>
                            <td style="width:15%; text-align:right; font-size:11px; padding:3px 0 3px 8px;">{{ $t['correct'] }}/{{ $t['total'] }} &middot; {{ $t['percent'] }}%</td>
                        </tr>
                    @endforeach
                </table>

                @if (! empty($narrative['subjects'][$subject['subject']]))
                    <div style="margin-top:10px; font-size:11.5px; color:#33414F;">{{ $narrative['subjects'][$subject['subject']] }}</div>
                @endif
            </div>
        @endforeach
    @endif

    @php $logoPath = public_path('images/topicaled-logo.svg'); @endphp
    <div class="foot">
        @if (file_exists($logoPath))
            <img src="{{ $logoPath }}" height="16" alt="TopicalEd" style="vertical-align: middle; margin-right: 7px;">
        @endif
        Generated {{ $generatedAt->format('j M Y, g:i a') }}
        &middot; AI-assisted performance report
        &middot; <a href="https://topicaled.com" style="color: #313D4B; text-decoration: none;">TopicalEd</a>
    </div>
</div>
</body>
</html>
