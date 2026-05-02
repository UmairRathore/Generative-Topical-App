<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="space-y-1">
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Result</div>
        <h1 class="font-display text-3xl font-semibold text-brand-emerald">Session summary</h1>
        <p class="text-sm text-brand-slate">
            Mode: {{ ucfirst($session->mode) }}
            @if ($session->paper)
                &middot; {{ $session->paper->paper_code }} {{ $session->paper->session }} {{ $session->paper->year }}
            @elseif ($session->subject)
                &middot; {{ $session->subject->name }}
            @endif
        </p>
    </header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
            <div class="font-display text-3xl font-semibold text-brand-emerald">{{ $total }}</div>
            <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Answered</div>
        </div>
        <div class="rounded-xl border border-status-success/30 bg-white p-5 shadow-sm">
            <div class="font-display text-3xl font-semibold text-status-success">{{ $correct }}</div>
            <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Correct</div>
        </div>
        <div class="rounded-xl border border-status-error/30 bg-white p-5 shadow-sm">
            <div class="font-display text-3xl font-semibold text-status-error">{{ $incorrect }}</div>
            <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Incorrect</div>
        </div>
        <div class="rounded-xl border border-status-warning/30 bg-white p-5 shadow-sm">
            <div class="font-display text-3xl font-semibold text-status-warning">{{ $unscored }}</div>
            <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">No answer key</div>
        </div>
    </div>

    <section class="space-y-2">
        <h2 class="font-display text-xl font-semibold text-brand-emerald">Per-question breakdown</h2>
        <ul class="divide-y divide-brand-border rounded-xl border border-brand-border bg-white shadow-sm">
            @foreach ($attempts as $attempt)
                <li class="flex items-center justify-between p-3 text-sm">
                    <span class="font-semibold text-brand-charcoal">Q{{ $attempt->question?->question_number }}</span>
                    <span class="text-brand-slate">
                        Picked {{ $attempt->selected_answer ?? '—' }}
                        @if ($attempt->correct_answer)
                            &middot; correct {{ $attempt->correct_answer }}
                        @endif
                    </span>
                    <span>
                        @if ($attempt->is_correct === true)
                            <span class="rounded-full bg-status-success/10 px-2.5 py-0.5 text-xs font-semibold text-status-success">correct</span>
                        @elseif ($attempt->is_correct === false)
                            <span class="rounded-full bg-status-error/10 px-2.5 py-0.5 text-xs font-semibold text-status-error">incorrect</span>
                        @else
                            <span class="rounded-full bg-status-warning/10 px-2.5 py-0.5 text-xs font-semibold text-status-warning">no key</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-brand-emerald transition hover:text-brand-gold">&larr; Back home</a>
    </div>
</div>
