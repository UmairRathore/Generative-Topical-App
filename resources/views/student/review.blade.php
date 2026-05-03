@php
    $items = [
        ['n' => 7,  's' => 'B',  'c' => 'C', 'topic' => 'Momentum',         'difficulty' => 'Standard', 'q' => 'Two trolleys of equal mass collide elastically along a frictionless track…',   'explanation' => 'In an elastic collision between equal masses, the velocities are exchanged. Trolley A stops; trolley B moves at v.'],
        ['n' => 11, 's' => 'A',  'c' => 'D', 'topic' => 'Momentum',         'difficulty' => 'Hard',     'q' => 'A 0.20 kg ball strikes a wall at 12 m s⁻¹ and rebounds at 8 m s⁻¹…',           'explanation' => 'Change in momentum Δp = m(v_f − v_i) = 0.20 × (−8 − 12) = −4.0 N·s. Magnitude 4.0 N·s.'],
        ['n' => 19, 's' => null, 'c' => 'C', 'topic' => 'Atomic Physics',   'difficulty' => 'Standard', 'q' => 'Half-life of an isotope is 4.5 min. Initial 8.0 × 10²⁰ atoms…',                'explanation' => 'After 18 min = 4 half-lives, fraction remaining = (1/2)⁴ = 1/16. 8.0 × 10²⁰ / 16 = 5.0 × 10¹⁹.'],
        ['n' => 23, 's' => 'C',  'c' => 'B', 'topic' => 'Thermal Physics',  'difficulty' => 'Hard',     'q' => 'Specific heat capacity of water is 4180 J kg⁻¹ K⁻¹…',                          'explanation' => 'Heat = mcΔT = 0.50 × 4180 × 20 = 41,800 J ≈ 42 kJ.'],
    ];
@endphp
<x-layouts.dashboard role="student"
    :breadcrumb="['My Tests','Mechanics — Mock Set A','Review mistakes']"
    pageTitle="Review your mistakes">
    <x-slot:actions>
        <div class="flex" style="gap: 10px;">
            <a href="{{ route('student.results.show', 1) }}" wire:navigate class="btn btn-ghost btn-sm"><x-icon name="chev-l" size="13"/>Back to result</a>
            <a href="{{ route('student.practice') }}" wire:navigate class="btn btn-primary btn-sm">Practice these topics</a>
        </div>
    </x-slot:actions>

    <div class="flex flex-col" style="gap: 16px;">
        @foreach($items as $it)
            <div class="card-elev" style="padding: 0; overflow: hidden;">
                <div class="flex items-center" style="padding: 16px 24px; gap: 12px; border-bottom: 1px solid var(--border-soft); background: var(--soft-surface);">
                    <div class="flex items-center justify-center" style="width: 32px; height: 32px; border-radius: 6px; background: var(--error-soft); color: var(--error); font-weight: 700; font-size: 12px; flex: none;">
                        {{ $it['n'] }}
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 600;">Question {{ $it['n'] }}</div>
                        <div style="font-size: 11px; color: var(--text-faint);">{{ $it['topic'] }} · {{ $it['difficulty'] }}</div>
                    </div>
                    <span class="badge" style="background: var(--error-soft); color: #B91C1C; border: 1px solid #FECACA;">{{ $it['s'] ? 'Incorrect' : 'Skipped' }}</span>
                </div>
                <div style="padding: 24px;">
                    <p class="serif" style="font-size: 17px; font-weight: 500; line-height: 1.55; margin-bottom: 18px; color: var(--text);">{{ $it['q'] }}</p>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div style="padding: 14px 16px; border-radius: 8px; border: 1.5px solid #FECACA; background: var(--error-soft);">
                            <div style="font-size: 10px; font-weight: 700; color: #B91C1C; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Your answer</div>
                            <div class="serif" style="font-size: 18px; font-weight: 600;">{{ $it['s'] ? $it['s'].' — Incorrect' : 'Skipped' }}</div>
                        </div>
                        <div style="padding: 14px 16px; border-radius: 8px; border: 1.5px solid #BBF7D0; background: var(--success-soft);">
                            <div style="font-size: 10px; font-weight: 700; color: #15803D; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px;">Correct answer</div>
                            <div class="serif" style="font-size: 18px; font-weight: 600;">{{ $it['c'] }}</div>
                        </div>
                    </div>
                    <div style="padding: 16px; background: var(--gold-50); border-radius: 8px; border: 1px solid var(--gold-100);">
                        <div style="font-size: 10px; font-weight: 700; color: var(--gold-700); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px;">Explanation</div>
                        <p class="serif" style="font-size: 14px; line-height: 1.6; color: var(--text);">{{ $it['explanation'] }}</p>
                    </div>
                    <div class="flex" style="margin-top: 14px; gap: 8px;">
                        <button class="btn btn-ghost btn-sm">Practice similar <x-icon name="chev-r" size="12"/></button>
                        <button class="btn btn-ghost btn-sm"><x-icon name="flag" size="13"/>Save for later</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.dashboard>
