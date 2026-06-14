<div class="mx-auto w-full max-w-4xl space-y-8 p-6">
    <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">{{ $subject->code }}</p>
        <h1 class="font-display text-4xl font-semibold tracking-tight text-brand-emerald">{{ $subject->name }}</h1>
        @if ($subject->description)
            <p class="max-w-2xl text-brand-charcoal/70">{{ $subject->description }}</p>
        @endif
        <p class="text-sm text-brand-slate">
            {{ $totalDemoSafe }} demo-safe question{{ $totalDemoSafe === 1 ? '' : 's' }} available.
        </p>
    </header>

    <div class="flex gap-3">
        <a href="{{ route('practice.random', ['slug' => $subject->slug]) }}"
           class="inline-flex items-center rounded-md bg-brand-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest">
            Random practice
        </a>
    </div>

    <section class="space-y-3">
        <h2 class="font-display text-xl font-semibold text-brand-emerald">Imported papers</h2>
        @if ($papers->isEmpty())
            <p class="text-brand-slate">No papers imported yet. Run <code class="rounded bg-brand-surface px-1 py-0.5 text-brand-emerald">php artisan cambpast:import</code>.</p>
        @else
            <ul class="divide-y divide-brand-border rounded-xl border border-brand-border bg-white shadow-sm">
                @foreach ($papers as $paper)
                    <li class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-medium text-brand-charcoal">
                                {{ $paper->paper_code }} / {{ $paper->session }} {{ $paper->year }}
                                @if ($paper->paper_number)
                                    &middot; Paper {{ $paper->paper_number }}@if ($paper->variant) variant {{ $paper->variant }}@endif
                                @endif
                            </div>
                            <div class="text-sm text-brand-slate">
                                {{ $paper->demo_safe_questions_count }} of {{ $paper->total_questions }} demo-safe
                            </div>
                        </div>
                        <a href="{{ route('papers.show', $paper) }}"
                           class="text-sm font-semibold text-brand-emerald transition hover:text-brand-gold">
                            Start paper &rarr;
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
