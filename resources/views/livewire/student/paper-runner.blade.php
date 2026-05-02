<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">
            {{ $paper->paper_code }} &middot; {{ $paper->session }} {{ $paper->year }}
            @if ($paper->paper_number) &middot; Paper {{ $paper->paper_number }}@if ($paper->variant)/{{ $paper->variant }}@endif @endif
        </h1>
        <div class="text-sm text-neutral-500">
            @if ($total > 0)
                {{ min($index + 1, $total) }} / {{ $total }}
            @endif
        </div>
    </header>

    @if ($data)
        <x-question.renderer :data="$data" :selected="$selected" :submitted="$submitted" />

        <div class="flex justify-end gap-3">
            @if (! $submitted)
                <button type="button"
                        wire:click="submit"
                        @disabled($selected === null)
                        class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    Submit
                </button>
            @elseif ($index + 1 < $total)
                <button type="button"
                        wire:click="next"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900">
                    Next question
                </button>
            @else
                <button type="button"
                        wire:click="finish"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Finish paper
                </button>
            @endif

            <button type="button"
                    wire:click="finish"
                    class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold hover:bg-neutral-50 dark:border-neutral-600 dark:hover:bg-neutral-800">
                End early
            </button>
        </div>
    @else
        <div class="rounded-md border border-neutral-200 p-6 text-center text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
            <p>No demo-safe questions in this paper yet.</p>
        </div>
    @endif
</div>
