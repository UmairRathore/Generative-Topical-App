{{-- Per-student × per-topic matrix.
     Props: $matrix = ['topics'=>[...], 'rows'=>[['id','student','roll','overall','attempted','cells'=>[topic=>{percent,correct,total}|null]]]]
     Optional: $studentRoute (route name) to link each student name. --}}
@php
    $barColor = fn ($p) => $p >= 60 ? 'var(--emerald-700)' : ($p >= 40 ? 'var(--accent)' : '#ef4444');
    $cellBg = fn ($p) => $p >= 60 ? 'var(--emerald-50)' : ($p >= 40 ? 'var(--gold-50)' : '#fef2f2');
    $studentRoute = $studentRoute ?? null;
@endphp

@if (empty($matrix['topics']))
    <div style="padding: 32px; text-align: center; color: var(--text-faint); font-size: 13px;">No data yet.</div>
@else
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 520px;">
            <thead>
                <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                    <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint); position: sticky; left: 0; background: var(--soft-surface);">Student</th>
                    @foreach ($matrix['topics'] as $topic)
                        <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; color: var(--text-faint);">{{ $topic }}</th>
                    @endforeach
                    <th style="padding: var(--pad-cell); text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">Overall</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix['rows'] as $row)
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: var(--pad-cell); position: sticky; left: 0; background: var(--surface);">
                            @if ($studentRoute)
                                <a href="{{ route($studentRoute, $row['id']) }}" style="font-size: 13px; font-weight: 500; color: var(--gold-700); text-decoration: none;">{{ $row['student'] }}</a>
                            @else
                                <div style="font-size: 13px; font-weight: 500;">{{ $row['student'] }}</div>
                            @endif
                            <div style="font-size: 11px; color: var(--text-faint);">{{ $row['roll'] }}</div>
                        </td>
                        @foreach ($matrix['topics'] as $topic)
                            @php $cell = $row['cells'][$topic] ?? null; @endphp
                            <td style="padding: 8px; text-align: center;">
                                @if ($cell)
                                    <span style="display: inline-block; min-width: 46px; padding: 4px 8px; border-radius: 6px; font-size: 12.5px; font-weight: 600; background: {{ $cellBg($cell['percent']) }}; color: {{ $barColor($cell['percent']) }};"
                                          title="{{ $cell['correct'] }}/{{ $cell['total'] }}">{{ $cell['percent'] }}%</span>
                                @else
                                    <span style="color: var(--text-faint); font-size: 12px;">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td style="padding: var(--pad-cell); text-align: center; font-weight: 700; font-size: 13px;">
                            {{ $row['overall'] !== null ? $row['overall'].'%' : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
