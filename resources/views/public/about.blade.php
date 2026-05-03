<x-layouts.public>
    <section class="mx-auto max-w-4xl px-6 pt-20 pb-12 text-center">
        <p class="text-xs uppercase tracking-[0.2em] font-semibold text-brand-gold">About us</p>
        <h1 class="mt-2 font-display text-4xl md:text-5xl font-semibold tracking-tight text-brand-emerald">A platform built around one question:<br>
            <span class="text-brand-gold">does this student actually understand?</span></h1>
        <p class="mt-6 text-brand-charcoal/70 text-lg leading-8">
            Generative Topical was created by educators frustrated with messy past-paper PDFs and shallow practice apps.
            We rebuilt the Cambridge revision experience from the ground up — every question hand-tagged, every diagram
            preserved, every mark scheme mapped.
        </p>
    </section>

    <section class="mx-auto max-w-6xl px-6 py-12 grid md:grid-cols-3 gap-6">
        @foreach([
            ['12+ years', 'Past papers indexed', 'shield-check'],
            ['Topic-graded', 'Every question, every variant', 'tag'],
            ['Audit trail', 'PASS / REVIEW / BLOCKER per item', 'document-magnifying-glass'],
        ] as [$v, $l, $i])
            <x-gt.card padding="p-6" class="text-center">
                <div class="mx-auto size-12 rounded-lg flex items-center justify-center mb-3 bg-brand-emerald/10 text-brand-emerald border border-brand-emerald/20">
                    <flux:icon :name="$i" class="size-6" variant="outline" />
                </div>
                <p class="font-display text-2xl font-semibold text-brand-emerald">{{ $v }}</p>
                <p class="text-sm text-brand-slate mt-1">{{ $l }}</p>
            </x-gt.card>
        @endforeach
    </section>

    <section class="mx-auto max-w-4xl px-6 py-16">
        <h2 class="font-display text-3xl font-semibold text-brand-emerald">Our principles</h2>
        <div class="mt-6 grid md:grid-cols-2 gap-6">
            @foreach([
                ['Rigour over rapid', 'We will never ship a question that has not passed our QA pipeline. A wrong answer is worse than no question.'],
                ['Teachers know best', 'Our tools follow how teachers actually teach — by topic, by paper, by misconception.'],
                ['Fair to every learner', 'No tracking ads. No stealth upsells. Students see exactly what their teacher sees.'],
                ['Built for institutions', 'Schools demand reliability. Our platform is auditable, exportable, and compliant.'],
            ] as [$h, $p])
                <div>
                    <h3 class="font-display text-lg font-semibold text-brand-emerald">{{ $h }}</h3>
                    <p class="text-sm text-brand-charcoal/70 mt-1">{{ $p }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
