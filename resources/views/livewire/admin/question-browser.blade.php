<div class="mx-auto w-full max-w-6xl space-y-6 p-6 lg:p-10">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Admin</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">Question browser</h1>
            <p class="mt-2 text-sm text-brand-charcoal/70">
                Includes hidden / blocker / review questions. Total matched: <span class="font-semibold text-brand-emerald">{{ $questions->total() }}</span>.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.imports') }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">&larr; Import summary</a>
            <a href="{{ route('admin.papers') }}" class="rounded-md border border-brand-border bg-white px-3 py-2 text-brand-emerald transition hover:border-brand-gold">Manage papers</a>
        </div>
    </header>

    {{-- Filters --}}
    <section class="rounded-xl border border-brand-border bg-white p-5 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block sm:col-span-2 lg:col-span-4">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Search question text, options, paper, captions…"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Year</span>
                <select wire:model.live="year" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Session</span>
                <select wire:model.live="sessionCode" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    <option value="m">Feb / March</option>
                    <option value="s">May / June</option>
                    <option value="w">Oct / Nov</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Paper #</span>
                <select wire:model.live="paperNumber" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($paperNumbers as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Variant</span>
                <select wire:model.live="variant" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($variants as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Layout</span>
                <select wire:model.live="layoutType" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($layoutTypes as $lt)
                        <option value="{{ $lt }}">{{ $lt }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Visibility</span>
                <select wire:model.live="visibility" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($visibilities as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Review status</span>
                <select wire:model.live="reviewStatus" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($reviewStatuses as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">QA status</span>
                <select wire:model.live="qaStatus" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    @foreach ($qaStatuses as $q)
                        <option value="{{ $q }}">{{ $q }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Needs review</span>
                <select wire:model.live="needsReview" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Question images</span>
                <select wire:model.live="hasQuestionImages" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Option images</span>
                <select wire:model.live="hasOptionImages" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Option table</span>
                <select wire:model.live="hasOptionTable" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Topic</span>
                <select wire:model.live="topic" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">All</option>
                    <option value="untagged">Untagged</option>
                    @foreach ($topics as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Per page</span>
                <select wire:model.live="perPage" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>

        <div class="mt-3 flex justify-end">
            <button type="button" wire:click="resetFilters"
                    class="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800">
                Reset filters
            </button>
        </div>
    </section>

    {{-- Pagination top --}}
    <div>{{ $questions->links() }}</div>

    {{-- Results --}}
    <div class="space-y-6">
        @forelse ($questions as $question)
            @php
                $data = $renderable[$question->id];
                $vis = $question->visibility?->value;
                $review = $question->review_status?->value;
                $qa = $question->qa_status?->value;
                $assetCount = $question->assets->count();
                $hasTable = (bool) $question->optionTable;
                $rawExpanded = $expandedRaw[$question->id] ?? false;
                $warnExpanded = $expandedWarnings[$question->id] ?? false;
                $warningCount = is_array($question->warnings) ? count($question->warnings) : 0;
                $visBadge = match ($vis) {
                    'public' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                    'admin_only' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                    'hidden' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                    default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200',
                };
                $qaBadge = in_array($qa, ['blocker', 'failed']) ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'
                    : ($qa === 'review' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                    : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200');
            @endphp

            <article class="rounded-lg border border-neutral-200 dark:border-neutral-700">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="space-y-1 text-sm">
                        <div class="font-semibold">
                            Q{{ $question->question_number }}
                            <span class="text-neutral-500">·</span>
                            <span class="text-neutral-700 dark:text-neutral-200">{{ $question->paper->source_file }}</span>
                        </div>
                        <div class="text-xs text-neutral-500">
                            {{ $question->paper->paper_code }}/{{ $question->paper->paper_number }}{{ $question->paper->variant }}
                            · {{ $question->paper->session }} {{ $question->paper->year }}
                            · layout: {{ $question->layout_type?->value ?? 'unknown' }}
                            · assets: {{ $assetCount }}
                            @if ($hasTable) · option table @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="rounded px-2 py-0.5 font-semibold {{ $visBadge }}">{{ $vis }}</span>
                        <span class="rounded px-2 py-0.5 font-semibold {{ $qaBadge }}">qa: {{ $qa }}</span>
                        <span class="rounded bg-neutral-100 px-2 py-0.5 dark:bg-neutral-800">review: {{ $review }}</span>
                        @if ($question->needs_review)
                            <span class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">needs review</span>
                        @endif
                        @if ($warningCount > 0)
                            <button type="button" wire:click="toggleWarnings({{ $question->id }})"
                                    class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-200">
                                {{ $warningCount }} warning{{ $warningCount === 1 ? '' : 's' }}
                            </button>
                        @endif
                    </div>
                </header>

                @if ($warnExpanded && $warningCount > 0)
                    <ul class="border-b border-neutral-200 bg-amber-50/50 p-3 text-xs dark:border-neutral-700 dark:bg-amber-950/20">
                        @foreach ($question->warnings as $w)
                            <li class="text-amber-900 dark:text-amber-200">· {{ is_string($w) ? $w : json_encode($w) }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="p-4">
                    <x-question.renderer :data="$data" :interactive="false" />

                    <div class="mt-4 flex items-center justify-between text-xs text-neutral-500">
                        <div>
                            @if ($data['has_correct_answer'])
                                Correct answer: <span class="font-semibold text-neutral-700 dark:text-neutral-200">{{ $data['correct_answer'] }}</span>
                            @else
                                <span class="italic">Answer key pending</span>
                            @endif
                        </div>
                        <button type="button" wire:click="toggleRaw({{ $question->id }})"
                                class="rounded border border-neutral-300 px-2 py-1 hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800">
                            {{ $rawExpanded ? 'Hide' : 'View' }} raw payload
                        </button>
                    </div>

                    @if ($rawExpanded)
                        <pre class="mt-3 max-h-96 overflow-auto rounded bg-neutral-900 p-3 text-[11px] leading-snug text-neutral-100">{{ json_encode($question->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-md border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
                No questions match the current filters.
            </div>
        @endforelse
    </div>

    {{-- Pagination bottom --}}
    <div>{{ $questions->links() }}</div>
</div>
