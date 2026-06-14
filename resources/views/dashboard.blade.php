<x-layouts.dashboard>
    <div class="mx-auto w-full max-w-6xl space-y-8 p-6 lg:p-10">
        <header>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Welcome back</div>
            <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-brand-emerald">{{ auth()->user()->name }}</h1>
            <p class="mt-2 text-sm text-brand-charcoal/70">Cambridge assessment platform · Phase 1 demo</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-3">
            @php
                $papers = \App\Models\Paper::count();
                $questions = \App\Models\Question::count();
                $demoSafe = \App\Models\Question::demoSafe()->count();
            @endphp
            <a href="{{ route('admin.papers') }}" class="group rounded-xl border border-brand-border bg-white p-6 shadow-sm transition hover:border-brand-gold hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Papers</div>
                <div class="mt-2 font-display text-3xl font-semibold text-brand-emerald">{{ $papers }}</div>
                <div class="mt-3 text-xs text-brand-emerald/70 group-hover:text-brand-forest">Manage papers →</div>
            </a>
            <a href="{{ route('admin.questions') }}" class="group rounded-xl border border-brand-border bg-white p-6 shadow-sm transition hover:border-brand-gold hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Questions</div>
                <div class="mt-2 font-display text-3xl font-semibold text-brand-emerald">{{ $questions }}</div>
                <div class="mt-3 text-xs text-brand-emerald/70 group-hover:text-brand-forest">Browse questions →</div>
            </a>
            <a href="{{ route('subjects.show', 'a-level-physics') }}" class="group rounded-xl border border-brand-border bg-white p-6 shadow-sm transition hover:border-brand-gold hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Demo-safe</div>
                <div class="mt-2 font-display text-3xl font-semibold text-brand-emerald">{{ $demoSafe }}</div>
                <div class="mt-3 text-xs text-brand-emerald/70 group-hover:text-brand-forest">Open public site →</div>
            </a>
        </section>

        <section class="rounded-xl border border-brand-border bg-white p-8 shadow-sm">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h2 class="font-display text-xl font-semibold text-brand-emerald">Quick actions</h2>
                    <p class="mt-1 text-sm text-brand-charcoal/70">Common admin tasks for the current session.</p>
                </div>
                <span class="rounded-full bg-brand-gold/15 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand-emerald">Phase 1</span>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('admin.imports') }}" class="rounded-lg border border-brand-border px-4 py-3 text-sm transition hover:border-brand-gold">
                    <div class="font-semibold text-brand-emerald">Import summary</div>
                    <div class="text-xs text-brand-slate">Recent batches, blocker counts, asset stats</div>
                </a>
                <a href="{{ route('admin.papers.create') }}" class="rounded-lg border border-brand-border px-4 py-3 text-sm transition hover:border-brand-gold">
                    <div class="font-semibold text-brand-emerald">Create manual paper</div>
                    <div class="text-xs text-brand-slate">Add a paper outside the importer</div>
                </a>
            </div>
        </section>
    </div>
</x-layouts.dashboard>
