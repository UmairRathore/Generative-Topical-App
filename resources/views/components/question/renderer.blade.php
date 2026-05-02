@props([
    'data',
    'selected' => null,
    'submitted' => false,
])

@php
    $isCorrect = $submitted && $data['has_correct_answer'] && $selected === $data['correct_answer'];
    $isIncorrect = $submitted && $data['has_correct_answer'] && $selected !== null && $selected !== $data['correct_answer'];
@endphp

<div class="space-y-6">
    <div class="text-sm font-semibold text-neutral-500">
        Question {{ $data['number'] }}
    </div>

    @if ($data['has_split_text'])
        @if ($data['text_before'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line dark:prose-invert">
                {{ $data['text_before'] }}
            </div>
        @endif

        @foreach ($data['between_images'] as $img)
            <div>
                <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
            </div>
        @endforeach

        @if ($data['text_after'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line dark:prose-invert">
                {{ $data['text_after'] }}
            </div>
        @endif
    @else
        @if ($data['stem'])
            <div class="prose prose-neutral max-w-none whitespace-pre-line dark:prose-invert">
                {{ $data['stem'] }}
            </div>
        @endif

        @foreach ($data['between_images'] as $img)
            <div>
                <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
            </div>
        @endforeach
    @endif

    @foreach ($data['diagrams'] as $img)
        <div>
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
        </div>
    @endforeach

    @foreach ($data['after_images'] as $img)
        <div>
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
        </div>
    @endforeach

    @foreach ($data['extra_images'] as $img)
        <div>
            <img src="{{ $img['url'] }}" @if($img['caption']) alt="{{ $img['caption'] }}" @endif class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
        </div>
    @endforeach

    @if ($data['option_table'])
        @if ($data['option_table']['use_fallback_image'] && $data['option_table']['image_url'])
            <img src="{{ $data['option_table']['image_url'] }}" alt="Options table" class="max-w-full rounded-md border border-neutral-200 dark:border-neutral-700">
        @elseif (!empty($data['option_table']['rows']))
            <table class="w-full border-collapse text-sm">
                @if (!empty($data['option_table']['headers']))
                    <thead>
                        <tr>
                            @foreach ($data['option_table']['headers'] as $h)
                                <th class="border border-neutral-300 px-2 py-1 text-left">{{ is_string($h) ? $h : json_encode($h) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody>
                    @foreach ($data['option_table']['rows'] as $row)
                        <tr>
                            @foreach ((array) $row as $cell)
                                <td class="border border-neutral-300 px-2 py-1">{{ is_scalar($cell) ? $cell : json_encode($cell) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <div class="space-y-2">
        @foreach ($data['options'] as $option)
            @php
                $isSelected = $selected === $option['label'];
                $isAnswer = $submitted && $data['has_correct_answer'] && $option['label'] === $data['correct_answer'];
                $isWrongPick = $submitted && $isSelected && $data['has_correct_answer'] && !$isAnswer;
                $base = 'flex w-full items-start gap-3 rounded-lg border px-4 py-3 text-left transition';
                $state = match (true) {
                    $isAnswer => 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40',
                    $isWrongPick => 'border-rose-400 bg-rose-50 dark:bg-rose-950/40',
                    $isSelected => 'border-sky-400 bg-sky-50 dark:bg-sky-950/40',
                    default => 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-700 dark:hover:border-neutral-500',
                };
            @endphp
            <button
                type="button"
                wire:click="selectAnswer('{{ $option['label'] }}')"
                @disabled($submitted)
                class="{{ $base }} {{ $state }}"
            >
                <span class="font-semibold text-neutral-700 dark:text-neutral-200">{{ $option['label'] }}</span>
                <span class="flex-1 text-neutral-800 dark:text-neutral-100">
                    @if ($option['text'])
                        <span class="block whitespace-pre-line">{{ $option['text'] }}</span>
                    @endif
                    @foreach ($option['images'] as $img)
                        <img src="{{ $img }}" alt="Option {{ $option['label'] }}" class="mt-2 max-w-full rounded border border-neutral-200 dark:border-neutral-700">
                    @endforeach
                </span>
            </button>
        @endforeach
    </div>

    @if ($submitted)
        <div class="rounded-md border px-4 py-3 text-sm
            @if (!$data['has_correct_answer']) border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100
            @elseif ($isCorrect) border-emerald-300 bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100
            @else border-rose-300 bg-rose-50 text-rose-900 dark:bg-rose-950/40 dark:text-rose-100 @endif">
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
