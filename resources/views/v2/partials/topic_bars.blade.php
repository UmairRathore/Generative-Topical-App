{{-- Per-topic bars. Props: $stats = [['topic','correct','total','percent'], ...], optional $empty --}}
@php $barColor = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp
@forelse ($stats as $t)
    <div style="margin-bottom: 13px;">
        <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
            <span style="font-weight: 500;">{{ $t['topic'] }}</span>
            <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
        </div>
        <div style="height: 8px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
            <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $barColor($t['percent']) }};"></div>
        </div>
    </div>
@empty
    <p style="font-size: 13px; color: var(--text-faint);">{{ $empty ?? 'No completed exams yet — stats appear once students submit tests.' }}</p>
@endforelse
