@props([
    'label' => '',
    'value' => '',
    'delta' => null,
    'icon' => null,
    'accent' => 'emerald', // emerald | gold
    'positive' => true,
])
<div class="card-elev relative overflow-hidden" style="padding: 18px;">
    <div class="flex justify-between items-start">
        <div style="font-size: 11px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.08em;">{{ $label }}</div>
        @if($icon)
            <div class="flex items-center justify-center"
                 style="width: 30px; height: 30px; border-radius: 7px;
                        background: {{ $accent === 'gold' ? 'var(--gold-50)' : 'var(--emerald-50)' }};
                        color: {{ $accent === 'gold' ? 'var(--gold-700)' : 'var(--emerald-800)' }};">
                <x-icon :name="$icon" size="15"/>
            </div>
        @endif
    </div>
    <div class="serif" style="font-size: 28px; font-weight: 600; margin-top: 10px; letter-spacing: -0.01em; line-height: 1.1;">{{ $value }}</div>
    @if($delta)
        <div style="font-size: 11px; margin-top: 6px; color: {{ $positive ? 'var(--success)' : 'var(--error)' }}; font-weight: 500;">{{ $delta }}</div>
    @endif
</div>
