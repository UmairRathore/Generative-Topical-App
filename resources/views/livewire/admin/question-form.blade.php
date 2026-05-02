<div class="mx-auto w-full max-w-5xl space-y-6 p-6 lg:p-10">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Question</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">{{ $question ? 'Edit question' : 'Add question' }}</h1>
            <p class="mt-1 text-sm text-brand-slate">{{ $paper->source_file }}</p>
        </div>
        <a href="{{ route('admin.papers.questions', $paper) }}" class="text-sm font-semibold text-brand-emerald transition hover:text-brand-gold">&larr; Back to paper</a>
    </header>

    @if (session('flash'))
        <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('flash') }}</div>
    @endif
    @error('form') <div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950/40 dark:text-rose-100">{{ $message }}</div> @enderror

    <form wire:submit.prevent="save" class="space-y-6">

        {{-- Core fields --}}
        <section class="space-y-4 rounded-xl border border-brand-border bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase text-neutral-500">Core</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Question number *</span>
                    <input type="number" wire:model="question_number" min="1" max="255"
                           class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    @error('question_number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Layout</span>
                    <select wire:model="layout_type" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                        @foreach ($layoutTypes as $lt) <option value="{{ $lt }}">{{ $lt }}</option> @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Correct answer</span>
                    <select wire:model="correct_answer" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                        <option value="">— pending —</option>
                        @foreach (['A','B','C','D'] as $l) <option value="{{ $l }}">{{ $l }}</option> @endforeach
                    </select>
                </label>
            </div>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">question_text</span>
                <textarea wire:model="question_text" rows="3" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"></textarea>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">clean_question_text (optional)</span>
                <textarea wire:model="clean_question_text" rows="2" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"></textarea>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">image_between_question_before_text</span>
                    <textarea wire:model="image_between_question_before_text" rows="2" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"></textarea>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">image_between_question_after_text</span>
                    <textarea wire:model="image_between_question_after_text" rows="2" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"></textarea>
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">qa_status</span>
                    <select wire:model="qa_status" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                        @foreach ($qaStatuses as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">review_status</span>
                    <select wire:model="review_status" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                        @foreach ($reviewStatuses as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">visibility</span>
                    <select wire:model="visibility" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                        @foreach ($visibilities as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                    </select>
                </label>
                <label class="flex items-end gap-2">
                    <input type="checkbox" wire:model="needs_review" class="rounded border-neutral-300">
                    <span class="text-sm">needs_review</span>
                </label>
            </div>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">warnings (one per line)</span>
                <textarea wire:model="warnings_text" rows="2" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs dark:border-neutral-600 dark:bg-neutral-900"></textarea>
            </label>
        </section>

        {{-- Options --}}
        <section class="space-y-4 rounded-xl border border-brand-border bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase text-neutral-500">Options A / B / C / D</h2>
            @foreach (['A','B','C','D'] as $label)
                <div class="rounded-md border border-neutral-200 p-3 dark:border-neutral-700">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="rounded bg-neutral-200 px-2 py-1 text-sm font-semibold dark:bg-neutral-700">{{ $label }}</span>
                        <input type="number" wire:model="options.{{ $label }}.sort_order" min="1" max="9"
                               class="w-16 rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs dark:border-neutral-600 dark:bg-neutral-900" title="sort_order">
                    </div>
                    <textarea wire:model="options.{{ $label }}.text" rows="2" placeholder="Option text"
                              class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900"></textarea>

                    @if ($question)
                        @php $existing = $question->options->firstWhere('label', $label); @endphp
                        @if ($existing && $existing->assets->count())
                            <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
                                @foreach ($existing->assets as $asset)
                                    <label class="block text-xs">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->image_path) }}"
                                             class="aspect-square w-full rounded border border-neutral-200 object-cover dark:border-neutral-700">
                                        <span class="mt-1 flex items-center gap-1">
                                            <input type="checkbox" wire:model="deleteOptionAssetIds" value="{{ $asset->id }}">
                                            <span>delete</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <label class="mt-3 block text-xs">
                        <span class="mb-1 block font-semibold uppercase text-neutral-500">Add image(s) to {{ $label }}</span>
                        <input type="file" wire:model="newOptionImages.{{ $label }}" accept="image/*" multiple
                               class="block w-full text-xs">
                    </label>
                </div>
            @endforeach
        </section>

        {{-- Question-level assets --}}
        <section class="space-y-4 rounded-xl border border-brand-border bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase text-neutral-500">Question-level images</h2>

            @if ($question && $question->assets->whereNull('question_option_id')->count())
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($question->assets->whereNull('question_option_id') as $asset)
                        <div class="rounded border border-neutral-200 p-2 text-xs dark:border-neutral-700">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset->image_path) }}"
                                 class="aspect-square w-full rounded object-cover">
                            <div class="mt-1 truncate text-neutral-500" title="{{ $asset->role }}">{{ $asset->role }}</div>
                            <label class="mt-1 flex items-center gap-1">
                                <input type="checkbox" wire:model="deleteQuestionAssetIds" value="{{ $asset->id }}">
                                <span>delete</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="space-y-2">
                <p class="text-xs text-neutral-500">Add new images:</p>
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center gap-2">
                        <input type="file" wire:model="newQuestionImages.{{ $i }}" accept="image/*" class="block flex-1 text-xs">
                        <select wire:model="newQuestionImageRoles.{{ $i }}" class="rounded-md border border-neutral-300 bg-white px-2 py-1 text-xs dark:border-neutral-600 dark:bg-neutral-900">
                            <option value="">— role —</option>
                            @foreach ($allowedRoles as $r) <option value="{{ $r }}">{{ $r }}</option> @endforeach
                        </select>
                        @error("newQuestionImageRoles.$i") <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                @endfor
            </div>
        </section>

        {{-- Option table --}}
        <section class="space-y-4 rounded-xl border border-brand-border bg-white p-6 shadow-sm">
            <label class="flex items-center gap-2 text-sm font-semibold uppercase text-neutral-500">
                <input type="checkbox" wire:model.live="has_option_table" class="rounded border-neutral-300">
                <span>Option table</span>
            </label>

            @if ($has_option_table)
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">headers (comma-separated)</span>
                    <input type="text" wire:model="option_table_headers_text" placeholder=", X, Y, Z"
                           class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">rows (one per line, pipe-separated)</span>
                    <textarea wire:model="option_table_rows_text" rows="6" placeholder="A | val 1 | val 2&#10;B | val 1 | val 2"
                              class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs dark:border-neutral-600 dark:bg-neutral-900"></textarea>
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="option_table_use_fallback_image" class="rounded border-neutral-300">
                    <span>Use fallback image instead of HTML table</span>
                </label>

                @if ($question?->optionTable?->image_path)
                    <div class="rounded border border-neutral-200 p-2 text-xs dark:border-neutral-700">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->optionTable->image_path) }}" class="max-w-xs rounded">
                        <label class="mt-2 flex items-center gap-2">
                            <input type="checkbox" wire:model="deleteOptionTableImage">
                            <span>delete current fallback image</span>
                        </label>
                    </div>
                @endif

                <label class="block text-xs">
                    <span class="mb-1 block font-semibold uppercase text-neutral-500">Upload fallback image</span>
                    <input type="file" wire:model="newOptionTableImage" accept="image/*" class="block w-full text-xs">
                </label>
            @endif
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-brand-emerald px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest">
                {{ $question ? 'Save changes' : 'Create question' }}
            </button>
        </div>
    </form>

    @if ($previewData)
        <section class="rounded-xl border border-brand-emerald/30 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-brand-emerald">Preview (saved state)</h2>
            <x-question.renderer :data="$previewData" :interactive="false" />
        </section>
    @endif
</div>
