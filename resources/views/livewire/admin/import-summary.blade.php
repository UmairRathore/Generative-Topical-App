<div class="mx-auto w-full max-w-5xl space-y-8 p-6 lg:p-10">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Admin</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">Import summary</h1>
            <p class="mt-2 text-sm text-brand-charcoal/70">Run <code class="rounded bg-brand-surface px-1 py-0.5 text-brand-emerald">php artisan cambpast:import</code> from the project root to add new papers.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.papers') }}"
               class="rounded-md border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-brand-emerald transition hover:border-brand-gold">
                Manage papers &rarr;
            </a>
            <a href="{{ route('admin.questions') }}"
               class="rounded-md bg-brand-emerald px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest">
                Browse questions &rarr;
            </a>
        </div>
    </header>

    <section>
        <h2 class="mb-3 font-display text-xl font-semibold text-brand-emerald">Counts</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['papers',       'Papers'],
                ['questions',    'Questions'],
                ['demo_safe',    'Demo-safe'],
                ['hidden',       'Hidden'],
                ['admin_only',   'Admin only'],
                ['review',       'Awaiting review'],
                ['rejected',     'Rejected'],
                ['blocker',      'Blocker'],
                ['failed',       'Failed'],
                ['needs_review', 'Needs review'],
            ] as [$key, $label])
                <div class="rounded-xl border border-brand-border bg-white p-4 shadow-sm">
                    <div class="font-display text-2xl font-semibold text-brand-emerald">{{ $stats[$key] }}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-brand-slate">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="mb-3 font-display text-xl font-semibold text-brand-emerald">Recent import batches</h2>
        @if ($batches->isEmpty())
            <p class="text-brand-slate">No batches recorded yet.</p>
        @else
            <ul class="divide-y divide-brand-border rounded-xl border border-brand-border bg-white shadow-sm">
                @foreach ($batches as $batch)
                    <li class="space-y-1 p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">#{{ $batch->id }} &middot; {{ $batch->status }}</span>
                            <span class="text-neutral-500">{{ $batch->finished_at?->diffForHumans() ?? 'in progress' }}</span>
                        </div>
                        <div class="text-neutral-600 dark:text-neutral-300">
                            papers={{ $batch->total_papers }} &middot;
                            questions={{ $batch->total_questions }} &middot;
                            imported={{ $batch->imported_questions }} &middot;
                            skipped={{ $batch->skipped_questions }}
                        </div>
                        <div class="truncate text-xs text-neutral-500">{{ $batch->source_path }}</div>
                        @if (is_array($batch->errors) && count($batch->errors))
                            <details class="text-xs">
                                <summary class="cursor-pointer text-rose-600">{{ count($batch->errors) }} error(s)</summary>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($batch->errors as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
