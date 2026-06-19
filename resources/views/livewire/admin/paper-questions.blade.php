<div class="mx-auto w-full max-w-6xl space-y-6 p-6 lg:p-10">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Paper</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">{{ $paper->source_file }}</h1>
            <p class="mt-1 text-sm text-brand-charcoal/70">
                {{ $paper->paper_code }} · {{ $paper->session }} {{ $paper->year }}
                @if ($paper->paper_number) · Paper {{ $paper->paper_number }}{{ $paper->variant }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.papers') }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">&larr; Papers</a>
            <a href="{{ route('admin.papers.edit', $paper) }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">Edit paper</a>
            <a href="{{ route('admin.papers.questions.create', $paper) }}" class="rounded-md bg-brand-emerald px-4 py-2 font-semibold text-white shadow-sm transition hover:bg-brand-forest">+ Add question</a>
        </div>
    </header>

    @if (session('flash'))
        <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('flash') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([
            ['total', 'Total'],
            ['public', 'Public'],
            ['admin_only', 'Admin only'],
            ['hidden', 'Hidden'],
            ['needs_review', 'Needs review'],
        ] as [$k, $label])
            <div class="rounded-xl border border-brand-border bg-white p-4 shadow-sm">
                <div class="font-display text-2xl font-semibold text-brand-emerald">{{ $stats[$k] }}</div>
                <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-brand-slate">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-brand-border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-brand-surface text-left text-[11px] uppercase tracking-[0.18em] text-brand-slate">
                <tr>
                    <th class="px-3 py-2">Q#</th>
                    <th class="px-3 py-2">Layout</th>
                    <th class="px-3 py-2">QA</th>
                    <th class="px-3 py-2">Review</th>
                    <th class="px-3 py-2">Visibility</th>
                    <th class="px-3 py-2">Needs review</th>
                    <th class="px-3 py-2">Answer</th>
                    <th class="px-3 py-2 text-right">Assets</th>
                    <th class="px-3 py-2">Table</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($questions as $q)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $q->question_number }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->layout_type?->value }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->qa_status?->value }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->review_status?->value }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->visibility?->value }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->needs_review ? 'yes' : 'no' }}</td>
                        <td class="px-3 py-2 text-xs">
                            @if ($q->correct_answer)
                                <span class="font-semibold">{{ $q->correct_answer }}</span>
                            @else
                                <span class="italic text-neutral-500">pending</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ $q->assets->count() }}</td>
                        <td class="px-3 py-2 text-xs">{{ $q->optionTable ? 'yes' : '-' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-right">
                            <a href="{{ route('admin.papers.questions.edit', [$paper, $q]) }}" class="rounded bg-brand-emerald px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-brand-forest">Edit</a>
                            <button type="button" wire:click="preview({{ $q->id }})" class="rounded border border-brand-border px-2.5 py-1 text-xs text-brand-emerald transition hover:border-brand-gold">{{ $previewId === $q->id ? 'Hide' : 'Preview' }}</button>
                            <button type="button" wire:click="deleteQuestion({{ $q->id }})" wire:confirm="Delete Q{{ $q->question_number }}?" class="rounded border border-status-error/40 px-2.5 py-1 text-xs text-status-error transition hover:bg-status-error/5">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="p-6 text-center text-neutral-500">No questions yet. Click "Add question".</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($previewData)
        <section class="rounded-xl border border-brand-emerald/30 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-brand-emerald">Preview · Q{{ $previewData['number'] }}</h2>
            <x-question.renderer :data="$previewData" :interactive="false" />
        </section>
    @endif
</div>
