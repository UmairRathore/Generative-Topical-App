<x-layouts.public>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="mx-auto max-w-6xl px-6 pt-16 pb-20 sm:pt-24 sm:pb-28">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-brand-emerald/15 bg-white px-3 py-1 text-[11px] font-medium uppercase tracking-[0.2em] text-brand-emerald">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-gold"></span>
                        AI-Powered Assessment Platform
                    </div>
                    <h1 class="font-display text-5xl font-semibold leading-tight tracking-tight text-brand-emerald sm:text-6xl">
                        Past papers,<br>
                        <span class="text-brand-gold">organised by topic.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-charcoal/80">
                        Cambridge MCQ practice for serious students. Curated, searchable, and ready for revision &mdash; from O Level to A Level.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="{{ route('subjects.show', 'a-level-physics') }}"
                           class="rounded-md bg-brand-emerald px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest">
                            Browse A-Level Physics
                        </a>
                        <a href="{{ route('practice.random', 'a-level-physics') }}"
                           class="rounded-md border border-brand-emerald/20 bg-white px-6 py-3 text-sm font-semibold text-brand-emerald transition hover:border-brand-gold hover:text-brand-forest">
                            Start random practice
                        </a>
                    </div>
                </div>

                <div class="relative">
                    <div class="rounded-2xl border border-brand-border bg-white p-8 shadow-lg shadow-brand-emerald/5">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Sample question</div>
                            <span class="rounded-full bg-status-success/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-status-success">Pass</span>
                        </div>
                        <p class="text-base leading-relaxed text-brand-charcoal">
                            A particle is projected horizontally from the top of a cliff. Which graph correctly represents the variation of vertical velocity with time?
                        </p>
                        <div class="mt-5 space-y-2">
                            @foreach (['A','B','C','D'] as $letter)
                                <div class="flex items-center gap-3 rounded-lg border {{ $letter === 'B' ? 'border-brand-emerald bg-brand-emerald/5' : 'border-brand-border' }} px-4 py-2.5 text-sm">
                                    <span class="font-semibold {{ $letter === 'B' ? 'text-brand-emerald' : 'text-brand-slate' }}">{{ $letter }}</span>
                                    <span class="text-brand-charcoal/80">Sample option {{ $letter }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="absolute -top-4 -right-4 hidden h-24 w-24 rotate-12 rounded-2xl bg-brand-gold/10 sm:block"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="border-y border-brand-border bg-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-6 py-10 sm:grid-cols-3">
            <div>
                <div class="font-display text-3xl font-semibold text-brand-emerald">107</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Past Papers Imported</div>
            </div>
            <div>
                <div class="font-display text-3xl font-semibold text-brand-emerald">4,280</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Questions Indexed</div>
            </div>
            <div>
                <div class="font-display text-3xl font-semibold text-brand-emerald">2010 → 2025</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-slate">Sessions Covered</div>
            </div>
        </div>
    </section>

    {{-- Auth callout --}}
    @guest
        <section class="mx-auto max-w-6xl px-6 py-12 text-center">
            <p class="text-sm text-brand-slate">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-brand-emerald underline-offset-4 hover:underline">Log in</a>
                &middot;
                <a href="{{ route('register') }}" class="font-semibold text-brand-emerald underline-offset-4 hover:underline">Create account</a>
            </p>
        </section>
    @endguest
</x-layouts.public>
