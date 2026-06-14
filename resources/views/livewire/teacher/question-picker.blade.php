<div>
    <x-gt.page-header :breadcrumb="['Build Test']" title="Build a custom test">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm" @disabled(count($picked) === 0)>Save as test ({{ count($picked) }})</button>
        </x-slot:actions>
    </x-gt.page-header>

    <div class="grid items-start" style="grid-template-columns: 1fr 360px; gap: 16px;">
        {{-- Filterable bank --}}
        <div>
            <div class="card-elev" style="padding: 14px; margin-bottom: 12px;">
                <div class="flex flex-wrap" style="gap: 10px;">
                    <div style="flex: 1; min-width: 240px; position: relative;">
                        <x-icon name="search" size="15" class="absolute" style="left: 12px; top: 12px; color: var(--text-faint);"/>
                        <input class="input" wire:model.live.debounce.300ms="search" placeholder="Search questions…" style="padding-left: 36px;"/>
                    </div>
                    <select class="select" wire:model.live="topicSlug" style="width: 160px;">
                        <option value="">Topic: All</option>
                        @foreach($this->topics as $t)<option value="{{ $t->slug }}">{{ $t->name }}</option>@endforeach
                    </select>
                    <select class="select" style="width: 130px;"><option>Difficulty</option></select>
                    <select class="select" style="width: 130px;"><option>Year/Session</option></select>
                </div>
            </div>

            <div class="flex flex-col" style="gap: 10px;">
                @forelse($rows as $q)
                    @php $inCart = in_array($q->id, $picked, true); @endphp
                    <div class="card-elev flex items-start" style="padding: 16px; gap: 14px;
                            border: {{ $inCart ? '1.5px solid var(--gold-500)' : '1px solid var(--border)' }};
                            background: {{ $inCart ? 'var(--gold-50)' : 'var(--surface)' }};">
                        <div class="flex items-center justify-center mono" style="width: 44px; height: 44px; border-radius: 6px; background: var(--ivory-deep); border: 1px solid var(--border); flex: none; font-size: 9px; color: var(--text-faint);">Q</div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="flex items-center" style="gap: 8px; margin-bottom: 4px; font-size: 11px;">
                                <span class="mono" style="color: var(--gold-700); font-weight: 600;">Q-{{ $q->id }}</span>
                                @if($q->paper)<span class="chip" style="padding: 2px 6px; font-size: 10px;">{{ $q->paper->paper_code }}</span>@endif
                                <span class="badge badge-pass">Pass</span>
                            </div>
                            <div style="font-size: 13px; line-height: 1.5; color: var(--text);">{{ Str::limit($q->clean_question_text ?: $q->question_text, 220) }}</div>
                        </div>
                        <button wire:click="toggle({{ $q->id }})" class="btn btn-sm {{ $inCart ? 'btn-gold' : 'btn-ghost' }}">
                            @if($inCart)
                                <x-icon name="check" size="13"/>Added
                            @else
                                <x-icon name="plus" size="13"/>Add
                            @endif
                        </button>
                    </div>
                @empty
                    <div class="card-elev text-center" style="padding: 60px;">
                        <x-icon name="filter" size="28" stroke="1.2" style="opacity: 0.4; margin-bottom: 8px; color: var(--text-faint);"/>
                        <p style="color: var(--text-faint); font-size: 13px;">No questions match your filters.</p>
                    </div>
                @endforelse
            </div>

            @if($rows->hasPages())
                <div style="margin-top: 16px;">{{ $rows->links() }}</div>
            @endif
        </div>

        {{-- Cart --}}
        <div class="card-elev sticky" style="top: 84px;">
            <div class="flex justify-between items-center" style="margin-bottom: 12px;">
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">Selected ({{ count($picked) }})</h3>
                <button wire:click="$set('picked', [])" class="btn btn-ghost btn-sm">Clear</button>
            </div>
            @if($pickedQuestions->isEmpty())
                <div class="text-center" style="padding: 30px 0; color: var(--text-faint); font-size: 13px;">
                    <x-icon name="filter" size="28" stroke="1.2" style="opacity: 0.4;"/>
                    <p style="margin-top: 8px;">Pick questions from the bank to build your test.</p>
                </div>
            @else
                <div class="flex flex-col" style="gap: 8px; margin-bottom: 16px; max-height: 320px; overflow-y: auto;">
                    @foreach($pickedQuestions as $i => $q)
                        <div class="flex items-center" style="gap: 10px; padding: 8px 10px; border-radius: 6px; background: var(--soft-surface);">
                            <span class="mono" style="font-size: 11px; color: var(--gold-700); font-weight: 700; width: 18px;">{{ $i + 1 }}.</span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 11px; font-weight: 600;">Q-{{ $q->id }}</div>
                                <div class="truncate" style="font-size: 11px; color: var(--text-faint);">{{ $q->clean_question_text ?: $q->question_text }}</div>
                            </div>
                            <button wire:click="toggle({{ $q->id }})" style="background: transparent; border: 0; color: var(--text-faint); padding: 2px;"><x-icon name="x" size="13"/></button>
                        </div>
                    @endforeach
                </div>
            @endif
            <hr class="divider" style="margin: 14px 0;"/>
            <label class="label">Test name</label>
            <input class="input" placeholder="e.g. Topical Practice — Mechanics"/>
            <label class="label" style="margin-top: 12px;">Assign to</label>
            <select class="select"><option>Y12 Physics A</option><option>Y13 Physics</option></select>
            <button class="btn btn-primary" style="width: 100%; margin-top: 16px;" @disabled(count($picked) === 0)>
                Save & assign test
            </button>
            <button class="btn btn-ghost" style="width: 100%; margin-top: 6px;">Save as draft</button>
        </div>
    </div>
</div>
