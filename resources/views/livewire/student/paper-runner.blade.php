<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="flex items-center justify-between border-b border-brand-border pb-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Paper mode</div>
            <h1 class="font-display text-2xl font-semibold text-brand-emerald">
                {{ $paper->paper_code }} &middot; {{ $paper->session }} {{ $paper->year }}
                @if ($paper->paper_number) &middot; Paper {{ $paper->paper_number }}@if ($paper->variant)/{{ $paper->variant }}@endif @endif
            </h1>
        </div>
        @if ($total > 0)
            <div class="rounded-full bg-white px-4 py-1.5 text-sm shadow-sm ring-1 ring-brand-border">
                <span class="font-semibold text-brand-emerald">{{ min($index + 1, $total) }}</span>
                <span class="text-brand-slate">/ {{ $total }}</span>
            </div>
        @endif
    </header>

    @if ($data)
        <article class="rounded-xl border border-brand-border bg-white p-6 shadow-sm sm:p-8">
            <x-question.renderer :data="$data" :selected="$selected" :submitted="$submitted" />
        </article>

        <div class="flex justify-end gap-3">
            @if (! $submitted)
                <button type="button" wire:click="submit" @disabled($selected === null)
                        class="rounded-md bg-brand-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest disabled:opacity-50">
                    Submit
                </button>
            @elseif ($index + 1 < $total)
                <button type="button" wire:click="next"
                        class="rounded-md bg-brand-charcoal px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-emerald">
                    Next question
                </button>
            @else
                <button type="button" wire:click="finish"
                        class="rounded-md bg-status-success px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                    Finish paper
                </button>
            @endif

            <button type="button" wire:click="finish"
                    class="rounded-md border border-brand-border bg-white px-5 py-2.5 text-sm font-semibold text-brand-emerald transition hover:border-brand-gold">
                End early
            </button>
        </div>
    @else
        <div class="rounded-xl border border-brand-border bg-white p-8 text-center text-brand-charcoal/80 shadow-sm">
            <p>No demo-safe questions in this paper yet.</p>
        </div>
    @endif
</div>
