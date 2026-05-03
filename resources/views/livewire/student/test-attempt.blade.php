@php
    $selected = $answers[$current] ?? null;
    $isFlagged = in_array($current, $flagged);
@endphp
<div style="min-height: 100vh; background: var(--ivory-deep);" x-data="{
        seconds: {{ $remainingSeconds }},
        get fmt() {
            const h = String(Math.floor(this.seconds/3600)).padStart(2,'0');
            const m = String(Math.floor((this.seconds%3600)/60)).padStart(2,'0');
            const s = String(this.seconds%60).padStart(2,'0');
            return (h !== '00' ? h + ':' : '') + m + ':' + s;
        }
     }"
     x-init="setInterval(() => seconds = Math.max(0, seconds - 1), 1000)">
    {{-- Test header --}}
    <header class="flex items-center" style="height: 64px; background: var(--emerald-900); color: var(--ivory); padding: 0 28px; gap: 24px; border-bottom: 1px solid rgba(212,164,55,0.2);">
        <x-crest size="28" variant="mono-light"/>
        <div>
            <div class="serif" style="font-size: 16px; font-weight: 600;">Mechanics — Mock Set A</div>
            <div style="font-size: 11px; color: rgba(250,247,239,0.6);">9702/12 style · Y12 Physics · 60 minutes</div>
        </div>
        <div class="flex-1"></div>
        <div class="flex items-center" style="gap: 8px; padding: 8px 14px; background: rgba(255,255,255,0.06); border-radius: 8px; border: 1px solid rgba(212,164,55,0.2);">
            <x-icon name="clock" size="15" style="color: var(--accent);"/>
            <span class="mono" style="font-size: 16px; font-weight: 600; letter-spacing: 0.04em; color: var(--ivory);" x-text="fmt"></span>
        </div>
        <div style="font-size: 13px; color: rgba(250,247,239,0.7);">Question <b style="color: var(--ivory);">{{ $current }}</b> of {{ $total }}</div>
        <a href="{{ route('student.results.show', 1) }}" wire:navigate class="btn btn-gold btn-sm">Submit test</a>
    </header>

    {{-- Progress bar --}}
    <div style="height: 3px; background: var(--slate-200);">
        <div style="width: {{ $current / $total * 100 }}%; height: 100%; background: var(--accent); transition: width .3s;"></div>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 280px; max-width: 1280px; margin: 0 auto; padding: 32px 28px; gap: 24px;">
        {{-- Question --}}
        <div>
            <div style="background: var(--surface); border-radius: 14px; padding: 40px; border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                <div class="flex justify-between items-center" style="margin-bottom: 24px;">
                    <div style="font-size: 11px; font-weight: 600; color: var(--gold-700); text-transform: uppercase; letter-spacing: 0.08em;">{{ $q['topic'] }}</div>
                    <button wire:click="flag({{ $current }})" class="btn btn-sm"
                        style="background: {{ $isFlagged ? 'var(--warning-soft)' : 'transparent' }};
                               color: {{ $isFlagged ? '#B45309' : 'var(--text-soft)' }};
                               border: 1px solid {{ $isFlagged ? '#FDE68A' : 'var(--border)' }};">
                        <x-icon name="flag" size="13"/>{{ $isFlagged ? 'Flagged' : 'Flag for review' }}
                    </button>
                </div>

                <h2 class="serif" style="font-size: 22px; font-weight: 500; line-height: 1.55; color: var(--text); margin-bottom: 14px;">{{ $q['stem'] }}</h2>
                <p class="serif" style="font-size: 22px; font-weight: 600; line-height: 1.55; color: var(--text); margin-bottom: 28px;">{{ $q['prompt'] }}</p>

                <hr class="gold-rule" style="margin-bottom: 24px;"/>

                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 14px;">
                    @foreach($q['options'] as $o)
                        @php $sel = $selected === $o['l']; @endphp
                        <button type="button" wire:click="answer({{ $current }}, '{{ $o['l'] }}')"
                                class="flex items-center relative" style="padding: 20px 22px; border-radius: 10px; text-align: left;
                                       border: {{ $sel ? '2px solid var(--emerald-800)' : '1.5px solid var(--border)' }};
                                       background: {{ $sel ? 'var(--emerald-50)' : 'var(--surface)' }};
                                       gap: 16px; box-shadow: {{ $sel ? '0 0 0 4px rgba(11,61,46,0.06)' : 'none' }};
                                       transition: all .15s;">
                            <div class="flex items-center justify-center" style="width: 36px; height: 36px; border-radius: 50%;
                                     background: {{ $sel ? 'var(--emerald-800)' : 'transparent' }};
                                     color: {{ $sel ? 'var(--ivory)' : 'var(--text-soft)' }};
                                     border: {{ $sel ? '0' : '1.5px solid var(--slate-300)' }};
                                     font-weight: 700; font-size: 14px; flex: none;">{{ $o['l'] }}</div>
                            <span class="serif" style="font-size: 19px; font-weight: 500;">{{ $o['v'] }}</span>
                            @if($sel)
                                <div class="absolute" style="right: 14px; top: 14px; color: var(--accent);"><x-icon name="check" size="16" stroke="2.6"/></div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Nav buttons --}}
            <div class="flex justify-between" style="margin-top: 20px;">
                <button wire:click="go({{ $current - 1 }})" class="btn btn-ghost" @disabled($current === 1)>
                    <x-icon name="chev-l" size="14"/>Previous
                </button>
                @if($current < $total)
                    <button wire:click="go({{ $current + 1 }})" class="btn btn-primary">
                        Next question <x-icon name="chev-r" size="14" stroke="2.4"/>
                    </button>
                @else
                    <a href="{{ route('student.results.show', 1) }}" wire:navigate class="btn btn-primary">
                        Submit test <x-icon name="chev-r" size="14" stroke="2.4"/>
                    </a>
                @endif
            </div>

            <div class="flex items-center" style="margin-top: 32px; font-size: 12px; color: var(--text-faint); gap: 16px; flex-wrap: wrap;">
                <x-icon name="shield" size="13"/>
                Your answers are autosaved. Connection: stable.
            </div>
        </div>

        {{-- Question navigator --}}
        <aside class="self-start sticky" style="top: 92px;">
            <div class="card-elev" style="padding: 18px;">
                <div class="flex justify-between items-center" style="margin-bottom: 14px;">
                    <h3 class="serif" style="font-size: 15px; font-weight: 600;">Question navigator</h3>
                    <span style="font-size: 11px; color: var(--text-faint);">{{ count($answers) }}/{{ $total }}</span>
                </div>
                <div class="grid" style="grid-template-columns: repeat(8, 1fr); gap: 5px;">
                    @for($n = 1; $n <= $total; $n++)
                        @php
                            $isCurrent = $n === $current;
                            $isAnswered = isset($answers[$n]);
                            $isFlaggedN = in_array($n, $flagged);
                            $bg = 'var(--surface)'; $color = 'var(--text-soft)'; $border = '1px solid var(--border)';
                            if ($isAnswered) { $bg = 'var(--emerald-800)'; $color = 'var(--ivory)'; $border = '0'; }
                            if ($isCurrent) { $bg = 'var(--accent)'; $color = 'var(--emerald-900)'; $border = '0'; }
                        @endphp
                        <button wire:click="go({{ $n }})" class="relative" style="aspect-ratio: 1; border-radius: 6px; padding: 0; font-size: 11px; font-weight: 600; font-family: var(--mono); background: {{ $bg }}; color: {{ $color }}; border: {{ $border }};">
                            {{ $n }}
                            @if($isFlaggedN)
                                <span class="absolute" style="top: -2px; right: -2px; width: 8px; height: 8px; background: var(--warning); border-radius: 50%; border: 1.5px solid var(--surface);"></span>
                            @endif
                        </button>
                    @endfor
                </div>
                <hr class="divider" style="margin: 16px 0;"/>
                <div class="flex flex-col" style="gap: 8px; font-size: 11px; color: var(--text-soft);">
                    <div class="flex items-center" style="gap: 8px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: var(--accent);"></span>Current</div>
                    <div class="flex items-center" style="gap: 8px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: var(--emerald-800);"></span>Answered ({{ count($answers) }})</div>
                    <div class="flex items-center" style="gap: 8px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: var(--surface); border: 1px solid var(--border);"></span>Unattempted ({{ $total - count($answers) }})</div>
                    <div class="flex items-center" style="gap: 8px;"><span style="width: 8px; height: 8px; border-radius: 50%; background: var(--warning);"></span>Flagged ({{ count($flagged) }})</div>
                </div>
                <hr class="divider" style="margin: 16px 0;"/>
                <a href="{{ route('student.results.show', 1) }}" wire:navigate class="btn btn-gold" style="width: 100%;">
                    <x-icon name="check" size="14"/>Submit test
                </a>
                <p class="text-center" style="font-size: 11px; color: var(--text-faint); margin-top: 10px; line-height: 1.5;">
                    You can return to flagged questions before submitting.
                </p>
            </div>
        </aside>
    </div>
</div>
