@php
    $STEPS = ['Subject & level', 'Topics', 'Question allocation', 'Settings', 'Preview & generate'];
    $TOPICS = [
        ['mech',  'Mechanics',       487],
        ['wave',  'Waves',           264],
        ['elec',  'Electricity',     412],
        ['therm', 'Thermal Physics', 198],
        ['atom',  'Atomic Physics',  173],
        ['field', 'Fields',          221],
        ['osc',   'Oscillations',    156],
        ['quan',  'Quantum Physics', 142],
    ];
    $stepIdx = $step - 1;
    $total = $this->totalQuestions();
    $valid = $total === 40;
@endphp

<div>
    <x-gt.page-header :breadcrumb="['Generate Paper']" title="Generate 40-question paper" />

    {{-- Stepper --}}
    <div class="card-elev" style="padding: 20px; margin-bottom: 16px;">
        <div class="flex items-center" style="gap: 0;">
            @foreach($STEPS as $i => $label)
                <div class="flex items-center" style="gap: 10px; opacity: {{ $i > $stepIdx + 1 ? 0.5 : 1 }};">
                    <div class="flex items-center justify-center" style="width: 30px; height: 30px; border-radius: 50%;
                            background: {{ $i < $stepIdx ? 'var(--success)' : ($i === $stepIdx ? 'var(--emerald-800)' : 'var(--surface)') }};
                            color: {{ $i <= $stepIdx ? 'var(--ivory)' : 'var(--text-soft)' }};
                            border: {{ $i > $stepIdx ? '1.5px solid var(--border)' : '0' }};
                            font-size: 12px; font-weight: 700; flex: none;">
                        @if($i < $stepIdx)
                            <x-icon name="check" size="14" stroke="2.4"/>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </div>
                    <span style="font-size: 13px; font-weight: {{ $i === $stepIdx ? 600 : 500 }}; color: {{ $i === $stepIdx ? 'var(--text)' : 'var(--text-soft)' }};">{{ $label }}</span>
                </div>
                @if($i < count($STEPS) - 1)
                    <div style="flex: 1; height: 1px; background: {{ $i < $stepIdx ? 'var(--success)' : 'var(--border)' }}; margin: 0 14px;"></div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="card-elev" style="padding: 32px; min-height: 480px;">
        @if($step === 1)
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 6px;">Choose your subject</h2>
            <p style="color: var(--text-soft); margin-bottom: 28px;">Pick the Cambridge subject and level you want to generate a paper for.</p>
            <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 28px;">
                @forelse($this->subjects as $s)
                    @php $sel = $subjectId === $s->id; @endphp
                    <button wire:click="$set('subjectId', {{ $s->id }})" type="button"
                            style="padding: 18px; border-radius: 10px; text-align: left;
                                   border: {{ $sel ? '1.5px solid var(--emerald-800)' : '1px solid var(--border)' }};
                                   background: {{ $sel ? 'var(--emerald-50)' : 'var(--surface)' }};">
                        <div class="mono" style="font-size: 11px; color: var(--gold-700); font-weight: 600;">{{ $s->slug }}</div>
                        <div class="serif" style="font-size: 18px; font-weight: 600; margin-top: 4px;">{{ $s->name }}</div>
                        <div style="font-size: 11px; color: var(--text-faint); margin-top: 2px;">A Level</div>
                    </button>
                @empty
                    <p style="color: var(--text-soft); grid-column: 1/-1;">No subjects seeded yet.</p>
                @endforelse
            </div>
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 12px;">Level</h3>
            <div class="flex" style="gap: 10px;">
                @foreach(['O Level','A Level','IGCSE'] as $l)
                    <button class="chip {{ $l === 'A Level' ? 'chip-active' : '' }}" style="padding: 8px 16px; font-size: 13px;">{{ $l }}</button>
                @endforeach
            </div>

        @elseif($step === 2)
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 6px;">Select topics</h2>
            <p style="color: var(--text-soft); margin-bottom: 24px;">Choose the topics to include. Question counts per topic come next.</p>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: 10px;">
                @forelse($this->topics as $t)
                    @php $sel = isset($allocations[$t->id]); @endphp
                    <label class="flex items-center" style="gap: 12px; padding: 14px 18px; border-radius: 8px;
                            border: {{ $sel ? '1.5px solid var(--emerald-800)' : '1px solid var(--border)' }};
                            background: {{ $sel ? 'var(--emerald-50)' : 'var(--surface)' }};">
                        <input type="checkbox" wire:click="toggleTopic({{ $t->id }})" @checked($sel) style="accent-color: var(--emerald-800);"/>
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">{{ $t->name }}</div>
                            <div style="font-size: 11px; color: var(--text-faint);">{{ $t->questions_count ?? 0 }} questions available</div>
                        </div>
                        @if($sel)<span class="badge badge-emerald">Selected</span>@endif
                    </label>
                @empty
                    <p style="color: var(--text-soft); grid-column: 1/-1;">No topics seeded for this subject. Run the topic seeder.</p>
                @endforelse
            </div>

        @elseif($step === 3)
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 6px;">Allocate questions</h2>
            <p style="color: var(--text-soft); margin-bottom: 16px;">Distribute 40 questions across your selected topics.</p>

            <div class="flex justify-between items-center"
                 style="padding: 16px 20px; margin-bottom: 20px; border-radius: 10px;
                        background: {{ $valid ? 'var(--success-soft)' : ($total > 40 ? 'var(--error-soft)' : 'var(--gold-50)') }};
                        border: 1px solid {{ $valid ? '#86efac' : ($total > 40 ? '#FECACA' : 'var(--gold-100)') }};">
                <div class="flex items-center" style="gap: 12px;">
                    <x-icon :name="$valid ? 'check' : 'flag'" size="18" style="color: {{ $valid ? 'var(--success)' : ($total > 40 ? 'var(--error)' : 'var(--gold-700)') }};"/>
                    <div>
                        <div style="font-size: 13px; font-weight: 600; color: {{ $valid ? '#166534' : ($total > 40 ? '#991B1B' : 'var(--gold-700)') }};">
                            {{ $valid ? 'Ready to generate' : ($total > 40 ? 'Remove '.($total - 40).' questions' : 'Add '.(40 - $total).' more questions') }}
                        </div>
                        <div style="font-size: 11px; color: var(--text-soft); margin-top: 2px;">Cambridge papers must contain exactly 40 questions</div>
                    </div>
                </div>
                <div class="serif" style="font-size: 32px; font-weight: 600; color: {{ $valid ? '#166534' : ($total > 40 ? '#991B1B' : 'var(--gold-700)') }};">
                    {{ $total }}<span style="font-size: 18px; opacity: 0.6;">/40</span>
                </div>
            </div>

            <div class="flex flex-col" style="gap: 8px;">
                @foreach($this->topics as $t)
                    @if(isset($allocations[$t->id]))
                        <div class="grid items-center" style="grid-template-columns: 200px 1fr 80px 100px; gap: 16px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 500;">{{ $t->name }}</div>
                                <div style="font-size: 11px; color: var(--text-faint);">{{ $t->questions_count ?? 0 }} available</div>
                            </div>
                            <input type="range" min="0" max="20" wire:model.live="allocations.{{ $t->id }}" style="accent-color: var(--emerald-800);"/>
                            <input type="number" min="0" max="20" wire:model.live="allocations.{{ $t->id }}" class="input" style="text-align: center; padding: 6px 8px; font-weight: 600;"/>
                            <div style="font-size: 11px; color: var(--text-faint); text-align: right;">{{ round($allocations[$t->id] / 40 * 100) }}% of paper</div>
                        </div>
                    @endif
                @endforeach
            </div>

        @elseif($step === 4)
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 6px;">Test settings</h2>
            <p style="color: var(--text-soft); margin-bottom: 28px;">Configure timing, shuffling, and student behaviour.</p>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px;">
                <div>
                    <label class="label">Time limit (minutes)</label>
                    <input class="input" type="number" wire:model="duration"/>
                    <div style="font-size: 11px; color: var(--text-faint); margin-top: 6px;">Cambridge MCQ standard: 60 minutes</div>
                </div>
                <div>
                    @foreach([
                        ['shuffle',         'Shuffle question order',        'Each student gets a different sequence'],
                        ['showAnswersAfter','Show correct answers after submit', 'Useful for practice; off for mocks'],
                    ] as [$k, $l, $d])
                        @php $on = $k === 'shuffle' ? $shuffle : $showAnswersAfter; @endphp
                        <div class="flex items-center" style="gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border-soft);">
                            <button type="button" wire:click="$toggle('{{ $k }}')" class="flex items-center" style="width: 38px; height: 22px; border-radius: 999px; border: 0; padding: 2px;
                                   background: {{ $on ? 'var(--emerald-800)' : 'var(--slate-300)' }}; flex: none;">
                                <div style="width: 18px; height: 18px; background: white; border-radius: 50%; transform: {{ $on ? 'translateX(16px)' : 'translateX(0)' }}; transition: transform .2s;"></div>
                            </button>
                            <div style="flex: 1;">
                                <div style="font-size: 13px; font-weight: 500;">{{ $l }}</div>
                                <div style="font-size: 11px; color: var(--text-faint);">{{ $d }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @else
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-bottom: 6px;">Preview & generate</h2>
            <p style="color: var(--text-soft); margin-bottom: 24px;">Review your paper. The system will randomly select questions matching your criteria.</p>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px;">
                <div>
                    <label class="label">Test name</label>
                    <input class="input" wire:model="testName"/>
                    <label class="label" style="margin-top: 18px;">Assign to class</label>
                    <select class="select"><option>Y12 Physics A (28 students)</option><option>Y12 Physics B (24 students)</option><option>Y13 Physics (32 students)</option></select>
                    <label class="label" style="margin-top: 18px;">Available from / Due</label>
                    <div class="flex" style="gap: 8px;">
                        <input class="input" type="date" value="{{ now()->format('Y-m-d') }}"/>
                        <input class="input" type="date" value="{{ now()->addDays(4)->format('Y-m-d') }}"/>
                    </div>
                </div>
                <div style="background: var(--ivory-deep); border-radius: 10px; padding: 20px; border: 1px solid var(--border);">
                    <div style="font-size: 11px; font-weight: 600; color: var(--gold-700); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px;">Paper summary</div>
                    <div class="serif" style="font-size: 22px; font-weight: 600; margin-bottom: 4px;">A Level Physics</div>
                    <div style="font-size: 12px; color: var(--text-faint); margin-bottom: 18px;">{{ $total }} questions · {{ $duration }} min</div>
                    <hr class="gold-rule" style="margin-bottom: 14px;"/>
                    @foreach($this->topics as $t)
                        @if(($allocations[$t->id] ?? 0) > 0)
                            <div class="flex justify-between" style="padding: 8px 0; font-size: 13px; border-bottom: 1px solid var(--border-soft);">
                                <span>{{ $t->name }}</span>
                                <span style="font-weight: 600;">{{ $allocations[$t->id] }} questions</span>
                            </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between" style="padding: 12px 0 0; font-size: 14px; font-weight: 600; color: var(--emerald-800);">
                        <span>Total</span><span>{{ $total }} questions</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Wizard footer --}}
    <div class="flex justify-between items-center" style="margin-top: 16px;">
        <button wire:click="back" class="btn btn-ghost">
            <x-icon name="chev-l" size="14"/>{{ $step > 1 ? 'Previous' : 'Cancel' }}
        </button>
        <div style="font-size: 12px; color: var(--text-faint);">Step {{ $step }} of {{ count($STEPS) }}</div>
        @if($step < count($STEPS))
            <button wire:click="next" class="btn btn-primary" @disabled($step === 3 && ! $valid)>
                Continue <x-icon name="chev-r" size="14" stroke="2.4"/>
            </button>
        @else
            <button wire:click="generate" class="btn btn-gold">
                <x-icon name="sparkle" size="14"/>Generate paper
            </button>
        @endif
    </div>
</div>
