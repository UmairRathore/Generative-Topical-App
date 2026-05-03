@props([
    'title' => 'Coming soon',
    'body' => 'Detailed screen — wireframed in the design canvas.',
    'backLabel' => 'Back to dashboard',
    'backHref' => null,
])
<div class="card-elev text-center" style="padding: 60px;">
    <x-icon name="layers" size="36" stroke="1.2" style="color: var(--text-faint); margin: 0 auto 14px;"/>
    <h3 class="serif" style="font-size: 22px; font-weight: 600;">{{ $title }}</h3>
    <p style="color: var(--text-soft); max-width: 480px; margin: 10px auto 0; line-height: 1.55;">{{ $body }}</p>
    @if($backHref)
        <a href="{{ $backHref }}" wire:navigate class="btn btn-ghost btn-sm" style="margin-top: 18px;">
            <x-icon name="chev-l" size="12"/>{{ $backLabel }}
        </a>
    @endif
</div>
