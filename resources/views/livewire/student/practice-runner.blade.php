<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="flex items-center justify-between border-b border-brand-border pb-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Random practice</div>
            <h1 class="font-display text-2xl font-semibold text-brand-emerald">{{ $subject->name }}</h1>
        </div>
        <div class="rounded-full bg-white px-4 py-1.5 text-sm shadow-sm ring-1 ring-brand-border">
            <span class="font-semibold text-brand-emerald">{{ $this->session->correct_count }}</span>
            <span class="text-brand-slate">/ {{ $this->session->total_questions }}</span>
        </div>
    </header>

    @if ($data)
        <article class="rounded-xl border border-brand-border bg-white p-6 shadow-sm sm:p-8">
            <x-question.renderer :data="$data" :selected="$selected" :submitted="$submitted" />
        </article>

        <div class="flex justify-end gap-3">
            @if (! $submitted)
                <button type="button"
                        wire:click="submit"
                        @disabled($selected === null)
                        class="rounded-md bg-brand-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest disabled:opacity-50">
                    Submit
                </button>
            @else
                <button type="button"
                        wire:click="next"
                        class="rounded-md bg-brand-charcoal px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-emerald">
                    Next question
                </button>
            @endif

            <button type="button"
                    wire:click="finish"
                    class="rounded-md border border-brand-border bg-white px-5 py-2.5 text-sm font-semibold text-brand-emerald transition hover:border-brand-gold">
                Finish &amp; see score
            </button>
        </div>
    @else
        <div class="rounded-xl border border-brand-border bg-white p-8 text-center shadow-sm">
            <p class="text-brand-charcoal/80">No more demo-safe questions to practise.</p>
            <button type="button"
                    wire:click="finish"
                    class="mt-4 rounded-md bg-brand-emerald px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-forest">
                See score
            </button>
        </div>
    @endif
</div>
