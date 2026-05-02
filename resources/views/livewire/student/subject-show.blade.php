<div class="mx-auto w-full max-w-4xl space-y-8 p-6">
    <header class="space-y-2">
        <p class="text-sm text-neutral-500">{{ $subject->code }}</p>
        <h1 class="text-3xl font-bold">{{ $subject->name }}</h1>
        @if ($subject->description)
            <p class="text-neutral-600 dark:text-neutral-300">{{ $subject->description }}</p>
        @endif
        <p class="text-sm text-neutral-500">
            {{ $totalDemoSafe }} demo-safe question{{ $totalDemoSafe === 1 ? '' : 's' }} available.
        </p>
    </header>

    <div class="flex gap-3">
        <a href="{{ route('practice.random', ['slug' => $subject->slug]) }}"
           class="inline-flex items-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            Random practice
        </a>
    </div>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Imported papers</h2>
        @if ($papers->isEmpty())
            <p class="text-neutral-500">No papers imported yet. Run <code>php artisan cambpast:import</code>.</p>
        @else
            <ul class="divide-y divide-neutral-200 rounded-md border border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">
                @foreach ($papers as $paper)
                    <li class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-medium">
                                {{ $paper->paper_code }} / {{ $paper->session }} {{ $paper->year }}
                                @if ($paper->paper_number)
                                    &middot; Paper {{ $paper->paper_number }}@if ($paper->variant) variant {{ $paper->variant }}@endif
                                @endif
                            </div>
                            <div class="text-sm text-neutral-500">
                                {{ $paper->demo_safe_questions_count }} of {{ $paper->total_questions }} demo-safe
                            </div>
                        </div>
                        <a href="{{ route('papers.show', $paper) }}"
                           class="text-sm font-semibold text-sky-600 hover:text-sky-700">
                            Start paper &rarr;
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
