@php
    $topics = ['Mechanics','Waves','Electricity','Thermal','Atomic','Momentum'];
    $sessions = [
        ['t' => 'Conservation of momentum', 'n' => 12, 'd' => 'Standard'],
        ['t' => 'Elastic vs inelastic collisions', 'n' => 10, 'd' => 'Hard'],
        ['t' => 'Impulse & force-time graphs', 'n' => 8, 'd' => 'Mixed'],
    ];
@endphp
<x-layouts.dashboard role="student" :breadcrumb="['Topic Practice']" pageTitle="Practice a topic">
    <div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px;" x-data="{ topic: 'Momentum', count: 15, difficulty: 'Mixed' }">
        <div class="card-elev" style="padding: 32px;">
            <div class="uppercase-eyebrow">Configure your session</div>
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 8px;">Targeted topic practice</h2>
            <p style="color: var(--text-soft); margin-top: 6px;">Untimed, with instant explanations after each question.</p>

            <label class="label" style="margin-top: 24px;">Subject</label>
            <select class="select"><option>Physics - A Level (9702)</option><option>Chemistry - A Level (9701)</option></select>

            <label class="label" style="margin-top: 18px;">Topic</label>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 8px;">
                @foreach($topics as $t)
                    <button type="button" @click="topic = '{{ $t }}'"
                            class="chip"
                            x-bind:class="topic === '{{ $t }}' ? 'chip-active' : ''"
                            style="padding: 10px 12px; font-size: 13px; justify-content: center;">{{ $t }}</button>
                @endforeach
            </div>

            <label class="label" style="margin-top: 18px;">Number of questions: <b style="color: var(--emerald-800);" x-text="count"></b></label>
            <input type="range" min="5" max="40" step="5" x-model="count" style="width: 100%; accent-color: var(--emerald-800);"/>
            <div class="flex justify-between" style="font-size: 11px; color: var(--text-faint); margin-top: 4px;">
                <span>5</span><span>20</span><span>40</span>
            </div>

            <label class="label" style="margin-top: 18px;">Difficulty</label>
            <div class="flex" style="gap: 6px;">
                @foreach(['Mixed','Easy','Standard','Hard'] as $d)
                    <button type="button" @click="difficulty = '{{ $d }}'" class="chip"
                            x-bind:class="difficulty === '{{ $d }}' ? 'chip-active' : ''"
                            style="padding: 6px 12px; font-size: 12px;">{{ $d }}</button>
                @endforeach
            </div>

            <a href="{{ route('student.tests.show', 1) }}" wire:navigate class="btn btn-primary" style="margin-top: 28px; padding: 14px 22px; font-size: 14px;">
                Start practice <x-icon name="chev-r" size="14" stroke="2.4"/>
            </a>
        </div>

        <div class="card-elev" style="background: var(--gold-50); border-color: var(--gold-100);">
            <div class="uppercase-eyebrow">Recommended for you</div>
            <h3 class="serif" style="font-size: 20px; font-weight: 600; margin-top: 6px;">Momentum needs your attention</h3>
            <p style="font-size: 13px; color: var(--text-soft); line-height: 1.55; margin-top: 8px;">You scored 50% on momentum in your last mock. Targeted practice typically lifts a topic 12–18% in two weeks.</p>
            <hr class="gold-rule" style="margin: 18px 0;"/>
            <h4 class="serif" style="font-size: 15px; font-weight: 600; margin-bottom: 10px;">Suggested sessions</h4>
            @foreach($sessions as $s)
                <button class="flex justify-between items-center" style="width: 100%; padding: 12px 14px; border-radius: 8px; background: var(--surface); border: 1px solid var(--gold-100); margin-bottom: 8px; text-align: left;">
                    <div>
                        <div style="font-size: 13px; font-weight: 600;">{{ $s['t'] }}</div>
                        <div style="font-size: 11px; color: var(--text-faint);">{{ $s['n'] }} questions · {{ $s['d'] }}</div>
                    </div>
                    <x-icon name="chev-r" size="14"/>
                </button>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
