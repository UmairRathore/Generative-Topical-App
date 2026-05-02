<div class="mx-auto w-full max-w-3xl space-y-6 p-6">
    <header class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ $subject->name }} &mdash; Random practice</h1>
        <div class="text-sm text-neutral-500">
            {{ $this->session->correct_count }} / {{ $this->session->total_questions }}
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
            @else
                <button type="button"
                        wire:click="next"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900">
                    Next question
                </button>
            @endif

            <button type="button"
                    wire:click="finish"
                    class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold hover:bg-neutral-50 dark:border-neutral-600 dark:hover:bg-neutral-800">
                Finish &amp; see score
            </button>
        </div>
    @else
        <div class="rounded-md border border-neutral-200 p-6 text-center text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
            <p>No more demo-safe questions to practise.</p>
            <button type="button"
                    wire:click="finish"
                    class="mt-4 rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                See score
            </button>
        </div>
    @endif
</div>
