<div class="mx-auto w-full max-w-3xl space-y-6 p-6 lg:p-10">
    <header class="flex items-center justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">Admin</div>
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-emerald">{{ $paper ? 'Edit paper' : 'Create paper' }}</h1>
        </div>
        <a href="{{ route('admin.papers') }}" class="text-sm font-semibold text-brand-emerald transition hover:text-brand-gold">&larr; Papers</a>
    </header>

    @if (session('flash'))
        <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('flash') }}</div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4 rounded-xl border border-brand-border bg-white p-6 shadow-sm">
        <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">Subject</span>
            <select wire:model="subject_id" class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}">{{ $s->qualification->examBoard->name ?? '' }} › {{ $s->qualification->name ?? '' }} › {{ $s->name }} ({{ $s->code }})</option>
                @endforeach
            </select>
            @error('subject_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">source_file (auto-generated if blank)</span>
                <input type="text" wire:model="source_file" placeholder="e.g. manual_a-level-physics_2024_march_2"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('source_file') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">paper_code *</span>
                <input type="text" wire:model="paper_code" placeholder="9702"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('paper_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">session *</span>
                <input type="text" wire:model="session" placeholder="march / may-june / oct-nov"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('session') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">session_code</span>
                <select wire:model="session_code" class="w-full rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">(none)</option>
                    <option value="m">m (Feb/March)</option>
                    <option value="s">s (May/June)</option>
                    <option value="w">w (Oct/Nov)</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">year</span>
                <input type="number" wire:model="year"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">paper_number</span>
                <input type="number" wire:model="paper_number" min="1" max="9"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('paper_number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">variant</span>
                <input type="text" wire:model="variant" maxlength="8"
                       class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                @error('variant') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase text-neutral-500">raw_meta (optional JSON)</span>
            <textarea wire:model="raw_meta_json" rows="4" placeholder='{"notes": "manual entry"}'
                      class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs dark:border-neutral-600 dark:bg-neutral-900"></textarea>
            @error('raw_meta_json') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-brand-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-forest">
                {{ $paper ? 'Save changes' : 'Create paper' }}
            </button>
        </div>
    </form>
</div>
