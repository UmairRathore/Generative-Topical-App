@props(['question' => null, 'visibility' => null, 'reviewStatus' => null])
{{--
    Derives the publish-side label from a Question (preferred) or from
    visibility + review_status. Maps to: Approved | Hidden | Needs Review | Fallback.
--}}
@php
    if ($question instanceof \App\Models\Question) {
        $vis = $question->visibility?->value;
        $rev = $question->review_status?->value;
    } else {
        $vis = $visibility instanceof \BackedEnum ? $visibility->value : $visibility;
        $rev = $reviewStatus instanceof \BackedEnum ? $reviewStatus->value : $reviewStatus;
    }

    [$label, $tone, $icon] = match (true) {
        $vis === 'hidden'                                       => ['Hidden',       'neutral', 'eye-slash'],
        in_array($rev, ['review', 'rejected'], true)            => ['Needs Review', 'warning', 'flag'],
        $rev === 'acceptable_fallback'                          => ['Fallback',     'gold',    'photo'],
        in_array($rev, ['pass', 'approved', 'render_fix'], true) && $vis === 'public' => ['Approved', 'emerald', 'check-badge'],
        default                                                 => ['—',            'neutral', 'question-mark-circle'],
    };
@endphp
<x-gt.badge :tone="$tone" :icon="$icon" {{ $attributes }}>{{ $label }}</x-gt.badge>
