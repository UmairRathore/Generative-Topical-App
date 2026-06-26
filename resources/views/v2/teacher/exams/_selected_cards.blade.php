{{-- Full gallery render of a set of questions, returned over AJAX. Each card has
     a Report button plus a per-card control chosen by $action:
       'remove' (default) - custom-selection drawer: data-remove hook
       'swap'            - random-generator preview: data-swap hook (re-draw one)
     Both hooks are handled by the host page's Alpine store. --}}
@php
    $sessionLabels = ['m' => 'Feb/Mar', 's' => 'May/Jun', 'w' => 'Oct/Nov'];
    $action = $action ?? 'remove';
@endphp
@foreach ($questions as $i => $q)
    <div class="selq-card" data-card="{{ $q->id }}">
        <div class="selq-head">
            @if ($action === 'swap')
                {{-- Include-in-final tick; state is re-applied client-side after each (re)render. --}}
                <input type="checkbox" class="gen-pick" data-pick="{{ $q->id }}" title="Include this question in the final test">
            @endif
            <span class="sel-num">{{ $i + 1 }}</span>
            <span style="font-size:12.5px; font-weight:700;">{{ $q->source_paper }}</span>
            @if ($q->subject?->level)<span style="font-size:11.5px; font-weight:800; color:var(--ink);">{{ $q->subject->level }}</span>@endif
            <span style="font-size:11px; color:var(--text-faint);">Q{{ $q->question_number }} · {{ $q->year }} · {{ $sessionLabels[$q->paper?->session_code] ?? $q->paper?->session_code }}</span>
            <span style="flex:1;"></span>
            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-soft); padding:3px 8px;"
                    @click.stop.prevent="$dispatch('flag-question', { action: '{{ route('v2.teacher.questions.flag', $q) }}', label: '{{ $q->source_paper }} · Q{{ $q->question_number }}', id: {{ $q->id }} })">
                <x-icon name="flag" size="12"/> Report
            </button>
            @if ($action === 'swap')
                <button type="button" class="sel-swap" data-swap="{{ $q->id }}" title="Replace with another random question"><x-icon name="refresh" size="13"/> Swap</button>
            @else
                <button type="button" class="sel-rm" data-remove="{{ $q->id }}" title="Remove from selection"><x-icon name="x" size="14"/></button>
            @endif
        </div>
        <div class="selq-body">
            @include('v2.partials.question_card', ['q' => $q])
        </div>
    </div>
@endforeach
