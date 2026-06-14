@props(['status' => null])
{{-- Accepts: App\Enums\QaStatus instance OR string value --}}
@php
    $value = $status instanceof \App\Enums\QaStatus ? $status->value : (string) $status;
    $map = [
        'pass'                => ['tone' => 'success', 'icon' => 'check-circle',     'label' => 'PASS'],
        'render_fix'          => ['tone' => 'info',    'icon' => 'wrench-screwdriver','label' => 'RENDER_FIX'],
        'acceptable_fallback' => ['tone' => 'gold',    'icon' => 'photo',            'label' => 'FALLBACK_OK'],
        'review'              => ['tone' => 'warning', 'icon' => 'eye',              'label' => 'REVIEW'],
        'failed'              => ['tone' => 'error',   'icon' => 'x-circle',         'label' => 'FAILED'],
        'blocker'             => ['tone' => 'error',   'icon' => 'no-symbol',        'label' => 'BLOCKER'],
    ];
    $cfg = $map[$value] ?? ['tone' => 'neutral', 'icon' => 'question-mark-circle', 'label' => strtoupper($value ?: 'UNKNOWN')];
@endphp
<x-gt.badge :tone="$cfg['tone']" :icon="$cfg['icon']" {{ $attributes }}>{{ $cfg['label'] }}</x-gt.badge>
