@props([
    'breadcrumb' => [],
    'title' => null,
])
<div style="padding: 0 0 16px;">
    @if(!empty($breadcrumb))
        <div class="flex items-center" style="font-size: 12px; color: var(--text-faint); gap: 6px; margin-bottom: 6px;">
            @foreach($breadcrumb as $i => $crumb)
                @if($i > 0)<x-icon name="chev-r" size="11" stroke="2"/>@endif
                <span style="color: {{ $i === count($breadcrumb) - 1 ? 'var(--text)' : 'var(--text-faint)' }}; font-weight: {{ $i === count($breadcrumb) - 1 ? 500 : 400 }};">{{ $crumb }}</span>
            @endforeach
        </div>
    @endif
    @if($title)
        <div class="flex items-end" style="gap: 16px;">
            <h1 class="serif" style="font-size: 28px; font-weight: 600; color: var(--text); letter-spacing: -0.015em;">{{ $title }}</h1>
            <div class="flex-1"></div>
            @isset($actions){{ $actions }}@endisset
        </div>
    @endif
</div>
