@php
    $pts = [64,68,71,72,75,78,80,77,82,84,85,84];
    $w = 280; $h = 70;
    $max = max($pts); $min = min($pts);
    $range = max(1, $max - $min);
    $d = '';
    foreach ($pts as $i => $p) {
        $x = ($i / (count($pts) - 1)) * $w;
        $y = $h - (($p - $min) / $range) * ($h - 8) - 4;
        $d .= ($i === 0 ? 'M' : 'L') . ' ' . round($x, 2) . ' ' . round($y, 2) . ' ';
    }
    $perTopic = [
        ['Mechanics',   92, 18, 'var(--success)'],
        ['Electricity', 81, 14, 'var(--success)'],
        ['Waves',       73, 11, 'var(--gold-500)'],
        ['Thermal',     64, 8,  'var(--gold-500)'],
        ['Atomic',      58, 6,  'var(--warning)'],
        ['Momentum',    50, 9,  'var(--warning)'],
    ];
@endphp
<x-layouts.dashboard role="student" :breadcrumb="['Performance']" pageTitle="Your performance">
    <x-slot:actions>
        <button class="btn btn-ghost btn-sm"><x-icon name="download" size="13"/>Export PDF</button>
    </x-slot:actions>

    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Tests this term" value="23" delta="+4 vs last" icon="clipboard"/>
        <x-stat-card label="Average" value="84%" delta="↑ 6%" icon="target" accent="gold"/>
        <x-stat-card label="Percentile" value="P78" delta="vs all Y12 nationally" icon="trending" accent="gold"/>
        <x-stat-card label="Hours practised" value="46h" icon="clock"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px;">
        <div class="card-elev">
            <div class="flex justify-between items-center" style="margin-bottom: 16px;">
                <div>
                    <h3 class="serif" style="font-size: 18px; font-weight: 600;">Score trend</h3>
                    <div style="font-size: 11px; color: var(--text-faint);">Last 12 attempts · Y12 Physics</div>
                </div>
                <div class="flex" style="gap: 6px;">
                    <button class="chip" style="padding: 4px 10px; font-size: 11px;">Week</button>
                    <button class="chip chip-active" style="padding: 4px 10px; font-size: 11px;">Term</button>
                    <button class="chip" style="padding: 4px 10px; font-size: 11px;">Year</button>
                </div>
            </div>
            <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width: 100%; height: 70px;">
                <path d="{{ $d }} L {{ $w }} {{ $h }} L 0 {{ $h }} Z" fill="var(--emerald-800)" opacity="0.15"/>
                <path d="{{ $d }}" fill="none" stroke="var(--emerald-800)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
            <hr class="divider" style="margin: 18px 0 16px;"/>
            <h4 class="serif" style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Per-topic mastery</h4>
            @foreach($perTopic as [$t, $p, $n, $c])
                <div class="grid items-center" style="grid-template-columns: 160px 1fr 60px 50px; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-soft);">
                    <div style="font-size: 13px; font-weight: 500;">{{ $t }}</div>
                    <div style="height: 6px; background: var(--slate-100); border-radius: 3px; overflow: hidden;">
                        <div style="width: {{ $p }}%; height: 100%; background: {{ $c }};"></div>
                    </div>
                    <div style="font-size: 11px; color: var(--text-faint); text-align: right;">{{ $n }} attempts</div>
                    <div style="font-size: 13px; font-weight: 600; text-align: right; color: {{ $c }};">{{ $p }}%</div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col" style="gap: 16px;">
            <div class="card-elev">
                <h3 class="serif" style="font-size: 17px; font-weight: 600; margin-bottom: 14px;">Strengths</h3>
                @foreach(["Newton's Laws · 96%", 'Kinematics graphs · 91%', "Ohm's law calculations · 89%"] as $s)
                    <div class="flex items-center" style="gap: 10px; padding: 8px 0; font-size: 13px;">
                        <x-icon name="check" size="14" stroke="2.6" style="color: var(--success);"/>{{ $s }}
                    </div>
                @endforeach
            </div>
            <div class="card-elev" style="background: var(--error-soft); border-color: #FECACA;">
                <h3 class="serif" style="font-size: 17px; font-weight: 600; margin-bottom: 14px;">Focus areas</h3>
                @foreach(['Momentum (2D collisions) · 41%', 'Half-life calculations · 52%', 'Specific heat capacity · 58%'] as $s)
                    <div class="flex items-center" style="gap: 10px; padding: 8px 0; font-size: 13px;">
                        <x-icon name="flag" size="14" style="color: var(--error);"/>{{ $s }}
                    </div>
                @endforeach
                <a href="{{ route('student.practice') }}" wire:navigate class="btn btn-primary btn-sm" style="width: 100%; margin-top: 10px;">Practice these now</a>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
