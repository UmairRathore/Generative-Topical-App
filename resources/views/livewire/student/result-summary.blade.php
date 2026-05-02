<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="space-y-1">
        <h1 class="text-2xl font-bold">Session result</h1>
        <p class="text-sm text-neutral-500">
            Mode: {{ ucfirst($session->mode) }}
            @if ($session->paper)
                &middot; {{ $session->paper->paper_code }} {{ $session->paper->session }} {{ $session->paper->year }}
            @elseif ($session->subject)
                &middot; {{ $session->subject->name }}
            @endif
        </p>
    </header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-md border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="text-2xl font-bold">{{ $total }}</div>
            <div class="text-xs uppercase text-neutral-500">Answered</div>
        </div>
        <div class="rounded-md border border-emerald-200 p-4 dark:border-emerald-800">
            <div class="text-2xl font-bold text-emerald-600">{{ $correct }}</div>
            <div class="text-xs uppercase text-neutral-500">Correct</div>
        </div>
        <div class="rounded-md border border-rose-200 p-4 dark:border-rose-800">
            <div class="text-2xl font-bold text-rose-600">{{ $incorrect }}</div>
            <div class="text-xs uppercase text-neutral-500">Incorrect</div>
        </div>
        <div class="rounded-md border border-amber-200 p-4 dark:border-amber-800">
            <div class="text-2xl font-bold text-amber-600">{{ $unscored }}</div>
            <div class="text-xs uppercase text-neutral-500">No answer key</div>
        </div>
    </div>

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Per-question breakdown</h2>
        <ul class="divide-y divide-neutral-200 rounded-md border border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">
            @foreach ($attempts as $attempt)
                <li class="flex items-center justify-between p-3 text-sm">
                    <span>Q{{ $attempt->question?->question_number }}</span>
                    <span class="text-neutral-500">
                        Picked {{ $attempt->selected_answer ?? '—' }}
                        @if ($attempt->correct_answer)
                            &middot; correct {{ $attempt->correct_answer }}
                        @endif
                    </span>
                    <span>
                        @if ($attempt->is_correct === true)
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">correct</span>
                        @elseif ($attempt->is_correct === false)
                            <span class="rounded bg-rose-100 px-2 py-0.5 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">incorrect</span>
                        @else
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">no key</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-sky-600 hover:text-sky-700">&larr; Back home</a>
    </div>
</div>
