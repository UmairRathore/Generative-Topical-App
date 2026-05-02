<div class="mx-auto w-full max-w-5xl space-y-8 p-6">
    <header>
        <h1 class="text-2xl font-bold">Import summary</h1>
        <p class="text-sm text-neutral-500">Run <code>php artisan cambpast:import</code> from the project root to add new papers.</p>
    </header>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Counts</h2>
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
                <div class="rounded-md border border-neutral-200 p-3 dark:border-neutral-700">
                    <div class="text-xl font-bold">{{ $stats[$key] }}</div>
                    <div class="text-xs uppercase text-neutral-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Recent import batches</h2>
        @if ($batches->isEmpty())
            <p class="text-neutral-500">No batches recorded yet.</p>
        @else
            <ul class="divide-y divide-neutral-200 rounded-md border border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">
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
