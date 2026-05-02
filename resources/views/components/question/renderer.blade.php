@props([
    'data',
    'selected' => null,
    'submitted' => false,
    'interactive' => true,
])

@php
    $isCorrect = $submitted && $data['has_correct_answer'] && $selected === $data['correct_answer'];
    $isIncorrect = $submitted && $data['has_correct_answer'] && $selected !== null && $selected !== $data['correct_answer'];
@endphp

<div class="space-y-6">
    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-brand-slate">
        <span class="inline-block h-1 w-6 bg-brand-gold"></span>
        Question {{ $data['number'] }}
    </div>

    @if ($data['has_split_text'])
        @if ($data['text_before'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line text-brand-charcoal">
                {{ $data['text_before'] }}
            </div>
        @endif

        @foreach ($data['between_images'] as $img)
            <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
                <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="mx-auto max-w-full">
            </div>
        @endforeach

        @if ($data['text_after'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line text-brand-charcoal">
                {{ $data['text_after'] }}
            </div>
        @endif
    @else
        @if ($data['stem'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line text-brand-charcoal">
                {{ $data['stem'] }}
            </div>
        @endif

        @foreach ($data['between_images'] as $img)
            <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
                <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="mx-auto max-w-full">
            </div>
        @endforeach
    @endif

    @foreach ($data['diagrams'] as $img)
        <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="mx-auto max-w-full">
        </div>
    @endforeach

    @foreach ($data['after_images'] as $img)
        <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="mx-auto max-w-full">
        </div>
    @endforeach

    @foreach ($data['extra_images'] as $img)
        <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="mx-auto max-w-full">
        </div>
    @endforeach

    @if ($data['option_table'])
        @if ($data['option_table']['use_fallback_image'] && $data['option_table']['image_url'])
            <div class="overflow-hidden rounded-lg border border-brand-border bg-white p-2">
                <img src="{{ $data['option_table']['image_url'] }}" alt="Options table" class="mx-auto max-w-full">
            </div>
        @elseif (!empty($data['option_table']['rows']))
            <div class="overflow-x-auto rounded-lg border border-brand-border bg-white">
                <table class="w-full border-collapse text-sm">
                    @if (!empty($data['option_table']['headers']))
                        <thead class="bg-brand-surface text-xs uppercase tracking-wider text-brand-slate">
                            <tr>
                                @foreach ($data['option_table']['headers'] as $h)
                                    <th class="border-b border-brand-border px-3 py-2 text-left font-semibold">{{ is_string($h) ? $h : json_encode($h) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                    @endif
                    <tbody>
                        @foreach ($data['option_table']['rows'] as $row)
                            <tr class="border-b border-brand-border last:border-b-0">
                                @foreach ((array) $row as $cell)
                                    <td class="px-3 py-2">{{ is_scalar($cell) ? $cell : json_encode($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    <div class="space-y-2">
        @foreach ($data['options'] as $option)
            @php
                $isSelected = $selected === $option['label'];
                $isAnswer = $submitted && $data['has_correct_answer'] && $option['label'] === $data['correct_answer'];
                $isWrongPick = $submitted && $isSelected && $data['has_correct_answer'] && !$isAnswer;
                $base = 'flex w-full items-start gap-3 rounded-lg border bg-white px-4 py-3 text-left transition';
                $state = match (true) {
                    $isAnswer => 'border-status-success bg-status-success/5',
                    $isWrongPick => 'border-status-error bg-status-error/5',
                    $isSelected => 'border-brand-emerald bg-brand-emerald/5 shadow-sm',
                    default => 'border-brand-border hover:border-brand-emerald/50 hover:shadow-sm',
                };
                $letterState = match (true) {
                    $isAnswer => 'bg-status-success text-white',
                    $isWrongPick => 'bg-status-error text-white',
                    $isSelected => 'bg-brand-emerald text-white',
                    default => 'bg-brand-surface text-brand-emerald',
                };
            @endphp
            <{{ $interactive ? 'button' : 'div' }}
                @if ($interactive)
                    type="button"
                    wire:click="selectAnswer('{{ $option['label'] }}')"
                    @disabled($submitted)
                @endif
                class="{{ $base }} {{ $state }}"
            >
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-sm font-semibold {{ $letterState }}">{{ $option['label'] }}</span>
                <span class="flex-1 text-brand-charcoal">
                    @if ($option['text'])
                        <span class="block whitespace-pre-line">{{ $option['text'] }}</span>
                    @endif
                    @foreach ($option['images'] as $img)
                        <img src="{{ $img }}" alt="Option {{ $option['label'] }}" class="mt-2 max-w-full rounded-md border border-brand-border">
                    @endforeach
                </span>
            </{{ $interactive ? 'button' : 'div' }}>
        @endforeach
    </div>

    @if ($submitted)
        <div class="rounded-md border px-4 py-3 text-sm
            @if (!$data['has_correct_answer']) border-status-warning/40 bg-status-warning/10 text-status-warning
            @elseif ($isCorrect) border-status-success/40 bg-status-success/10 text-status-success
            @else border-status-error/40 bg-status-error/10 text-status-error @endif">
            @if (!$data['has_correct_answer'])
                Answer key not available yet.
            @elseif ($isCorrect)
                Correct.
            @else
                Incorrect. Correct answer: {{ $data['correct_answer'] }}.
            @endif
        </div>
    @endif
</div>
