<div class="mx-auto w-full max-w-6xl space-y-6 p-6 lg:p-10">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Admin</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">Papers</h1>
            <p class="mt-2 text-sm text-brand-charcoal/70">Manual entry &amp; correction. Total: <span class="font-semibold text-brand-emerald">{{ $papers->total() }}</span>.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.imports') }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">&larr; Import summary</a>
            <a href="{{ route('admin.questions') }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">Question browser</a>
            <a href="{{ route('admin.papers.create') }}" class="rounded-md bg-brand-emerald px-4 py-2 font-semibold text-white shadow-sm transition hover:bg-brand-forest">+ Create paper</a>
        </div>
    </header>

    <div>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search source_file / paper_code / session / year…"
               class="w-full rounded-md border border-brand-border bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/15 focus:outline-none">
    </div>

    <div class="overflow-x-auto rounded-xl border border-brand-border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-brand-surface text-left text-[11px] uppercase tracking-[0.18em] text-brand-slate">
                <tr>
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">Source / Code</th>
                    <th class="px-3 py-2">Session / Year</th>
                    <th class="px-3 py-2">Paper / Var</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-right">Demo-safe</th>
                    <th class="px-3 py-2 text-right">Hidden</th>
                    <th class="px-3 py-2 text-right">Admin only</th>
                    <th class="px-3 py-2 text-right">Needs review</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($papers as $paper)
                    @php
                        $rows = $statusCounts->get($paper->id, collect());
                        $hidden = $rows->where('visibility', 'hidden')->sum('c');
                        $admin = $rows->where('visibility', 'admin_only')->sum('c');
                        $needs = $rows->where('needs_review', 1)->sum('c');
                    @endphp
                    <tr>
                        <td class="px-3 py-2 text-neutral-500">{{ $paper->id }}</td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ $paper->source_file }}</div>
                            <div class="text-xs text-neutral-500">{{ $paper->paper_code }} · {{ $paper->subject?->name }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $paper->session }} {{ $paper->year }}</td>
                        <td class="px-3 py-2">{{ $paper->paper_number }}{{ $paper->variant }}</td>
                        <td class="px-3 py-2 text-right">{{ $paper->total_questions }}</td>
                        <td class="px-3 py-2 text-right">{{ $paper->demo_safe_questions_count }}</td>
                        <td class="px-3 py-2 text-right">{{ $hidden }}</td>
                        <td class="px-3 py-2 text-right">{{ $admin }}</td>
                        <td class="px-3 py-2 text-right">{{ $needs }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('admin.papers.questions', $paper) }}" class="rounded bg-brand-emerald px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-brand-forest">Manage</a>
                            <a href="{{ route('admin.papers.edit', $paper) }}" class="rounded border border-brand-border px-2.5 py-1 text-xs text-brand-emerald transition hover:border-brand-gold">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="p-6 text-center text-neutral-500">No papers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $papers->links() }}</div>
</div>
