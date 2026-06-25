@extends($layout)
@section('page_title', 'Reported Questions')

@section('content')
<div style="max-width: 960px;">
    <div style="margin-bottom: 18px;">
        <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Quality · read-only</div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 4px;">Reported Questions</h2>
        <p style="color: var(--text-soft); margin-top: 2px; font-size: 13.5px; line-height: 1.5;">
            Every reported question across your {{ $scopeLabel }} — for visibility and auditing. Teachers make the academic
            decisions (dismiss, void, or escalate for quality review); there's nothing to action here.
        </p>
    </div>

    @if ($reports->isEmpty())
        <div style="padding: 48px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
            No questions have been reported yet.
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--soft-surface); border-bottom: 1px solid var(--border);">
                        @foreach (array_filter(['Question', 'Source', 'Reported by', 'Teacher', $showBranch ? 'Branch' : null, 'Status', '']) as $h)
                            <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint);">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                    @foreach ($reports as $r)
                    <tbody x-data="{ open: false }">
                        <tr style="border-bottom: 1px solid var(--border); vertical-align: top;">
                            <td style="padding: var(--pad-cell);">
                                <div style="font-size: 13px; font-weight: 600;">{{ $r['exam'] }}@if ($r['qno']) · Q{{ $r['qno'] }}@endif</div>
                                <div style="font-size: 11.5px; color: var(--text-faint);">{{ $r['subject'] }}</div>
                            </td>
                            <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $r['source'] }}</td>
                            <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft); max-width: 220px;">
                                @if (! empty($r['students']))
                                    {{ collect($r['students'])->take(2)->implode(', ') }}@if (count($r['students']) > 2) <span style="color: var(--text-faint);">+{{ count($r['students']) - 2 }} more</span>@endif
                                @else
                                    <span style="color: var(--text-faint);">—</span>
                                @endif
                            </td>
                            <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $r['teacher'] ?? '—' }}</td>
                            @if ($showBranch)
                                <td style="padding: var(--pad-cell); font-size: 12.5px; color: var(--text-soft);">{{ $r['branch'] ?? '—' }}</td>
                            @endif
                            <td style="padding: var(--pad-cell);">
                                <span class="badge {{ $r['badge'] }}" style="white-space: nowrap;">{{ $r['status'] }}</span>
                            </td>
                            <td style="padding: var(--pad-cell); text-align: right; white-space: nowrap;">
                                <button type="button" @click="open = !open" style="font-size: 12px; background: none; border: 0; color: var(--accent); cursor: pointer;" x-text="open ? 'Hide' : 'Details'"></button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                            <td colspan="{{ $showBranch ? 7 : 6 }}" style="padding: 14px 18px;">
                                <div style="font-size: 12.5px; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: var(--text);">Teacher decision:</span>
                                    <span style="color: var(--text-soft);">{{ $r['decision'] }}</span>
                                </div>
                                <div class="flex items-center" style="gap: 7px; font-size: 12.5px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <span style="font-weight: 600; color: var(--text);">Exam action:</span>
                                    @if ($r['voided'])
                                        <span class="badge badge-blocker" style="font-size: 10px;">Voided for this exam</span>
                                        @if ($r['voidReason'])<span style="color: var(--text-faint);">({{ $r['voidReason'] }})</span>@endif
                                        <span style="color: var(--text-faint);">— excluded from this exam's marks, rankings &amp; analytics; affected students were notified. The void is permanent for this exam.</span>
                                    @else
                                        <span style="color: var(--text-soft);">Not voided</span>
                                    @endif
                                </div>
                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 8px;">Timeline</div>
                                <div style="border-left: 2px solid var(--border); padding-left: 14px;">
                                    @foreach ($r['timeline'] as $ev)
                                        <div style="position: relative; margin-bottom: 9px;">
                                            <span style="position: absolute; left: -19px; top: 4px; width: 7px; height: 7px; border-radius: 50%; background: var(--accent);"></span>
                                            <div style="font-size: 12.5px; color: var(--text);">{{ $ev['label'] }}</div>
                                            <div style="font-size: 11px; color: var(--text-faint);">{{ $ev['t']?->format('j M Y, g:i A') }} ({{ $ev['t']?->diffForHumans() }})</div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    @endforeach
            </table>
        </div>
    @endif
</div>
@endsection
