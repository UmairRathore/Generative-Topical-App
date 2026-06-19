@php
    $topics = [
        ["Newton's Laws",   8, 8, 100],
        ['Forces & Motion', 7, 8, 88],
        ['Momentum',        4, 8, 50],
        ['Work & Energy',   7, 8, 88],
        ['Circular Motion', 6, 8, 75],
    ];
@endphp
<x-layouts.dashboard role="student" :breadcrumb="['My Tests','Mechanics - Mock Set A','Result']">
    {{-- Hero --}}
    <div class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--emerald-900), var(--emerald-800)); color: var(--ivory); border-radius: 14px; padding: 40px; margin-bottom: 24px;">
        <div class="grain absolute" style="inset: 0;"></div>
        <div class="absolute" style="right: -40px; top: -40px; opacity: 0.07;"><x-crest size="300" variant="mono-light"/></div>
        <div class="grid items-center relative" style="grid-template-columns: 1fr 1fr; gap: 40px;">
            <div>
                <div class="uppercase-eyebrow" style="color: var(--accent);">Test complete</div>
                <h1 class="serif" style="font-size: 38px; font-weight: 600; margin-top: 8px; line-height: 1.1;">Mechanics - Mock Set A</h1>
                <p style="margin-top: 12px; color: rgba(250,247,239,0.7); font-size: 14px;">Submitted just now · 39 of 40 attempted · 47 min 32 sec</p>
                <div class="flex" style="gap: 12px; margin-top: 24px;">
                    <a href="{{ route('student.review') }}" wire:navigate class="btn btn-gold"><x-icon name="eye" size="14"/>Review mistakes</a>
                    <a href="{{ route('student.dashboard') }}" wire:navigate class="btn btn-outline-light">Back to dashboard</a>
                </div>
            </div>
            <div class="flex justify-center">
                <x-score-ring :score="32" :total="40" :inverted="true"/>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Correct" value="32" delta="+5 vs. last attempt" icon="check"/>
        <x-stat-card label="Incorrect" value="7" icon="x"/>
        <x-stat-card label="Skipped" value="1" icon="flag"/>
        <x-stat-card label="Class rank" value="6 / 28" delta="Top quartile" icon="trophy" accent="gold"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px;">
        {{-- Topic breakdown --}}
        <div class="card-elev">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Topic breakdown</h3>
            @foreach($topics as [$t, $p, $of, $c])
                @php $col = $c >= 75 ? 'var(--success)' : ($c >= 50 ? 'var(--gold-500)' : 'var(--warning)'); @endphp
                <div class="grid items-center" style="grid-template-columns: 1fr 60px 50px; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-soft);">
                    <div>
                        <div style="font-size: 13px; font-weight: 500; margin-bottom: 4px;">{{ $t }}</div>
                        <div style="height: 6px; background: var(--slate-100); border-radius: 3px; overflow: hidden;">
                            <div style="width: {{ $c }}%; height: 100%; background: {{ $col }};"></div>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: var(--text-soft); text-align: right;">{{ $p }}/{{ $of }}</div>
                    <div style="font-size: 13px; font-weight: 600; text-align: right; color: {{ $col }};">{{ $c }}%</div>
                </div>
            @endforeach
        </div>

        {{-- Focus + teacher note --}}
        <div class="flex flex-col" style="gap: 16px;">
            <div class="card-elev" style="background: var(--gold-50); border-color: var(--gold-100);">
                <div class="uppercase-eyebrow">Focus area</div>
                <h3 class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">Momentum - 50%</h3>
                <p style="font-size: 13px; color: var(--text-soft); line-height: 1.55; margin-top: 8px;">
                    You missed 4 of 8 momentum questions. Most errors involved conservation of momentum in 2D collisions.
                </p>
                <a href="{{ route('student.practice') }}" wire:navigate class="btn btn-gold btn-sm" style="margin-top: 14px; width: 100%;">
                    Practice 12 momentum questions
                </a>
            </div>
            <div class="card-elev">
                <div class="uppercase-eyebrow">Teacher note</div>
                <p class="serif" style="font-size: 16px; font-style: italic; line-height: 1.5; margin-top: 8px; color: var(--text);">
                    "Strong improvement on energy and forces. Spend the weekend on momentum - see Worked Example 4.7."
                </p>
                <div class="flex items-center" style="gap: 8px; margin-top: 14px;">
                    <div class="flex items-center justify-center" style="width: 28px; height: 28px; border-radius: 50%; background: var(--emerald-50); color: var(--emerald-800); font-size: 11px; font-weight: 700;">SI</div>
                    <div style="font-size: 12px;">
                        <div style="font-weight: 600;">Dr. Saima Iqbal</div>
                        <div style="color: var(--text-faint);">Y12 Physics · ISL Lahore</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
