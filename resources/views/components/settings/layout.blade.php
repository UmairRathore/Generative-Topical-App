@props(['heading' => null, 'subheading' => null])
@php
    $items = [
        ['k' => 'profile',    'l' => 'Profile',           'i' => 'user',     'r' => 'settings.profile'],
        ['k' => 'password',   'l' => 'Password & security','i' => 'shield',   'r' => 'settings.password'],
        ['k' => 'appearance', 'l' => 'Display',            'i' => 'sun',      'r' => 'settings.appearance'],
    ];
    $currentRoute = request()->route()?->getName();
@endphp

<div>
    <x-gt.page-header :breadcrumb="['Account', $heading ?? 'Settings']" title="Account settings" />

    <div class="grid" style="grid-template-columns: 220px 1fr; gap: 16px;">
        <div class="card-elev" style="padding: 8px;">
            @foreach($items as $t)
                @php $active = $currentRoute === $t['r']; @endphp
                <a href="{{ route($t['r']) }}" wire:navigate class="flex items-center" style="gap: 10px; width: 100%; padding: 10px 12px; border-radius: 6px; margin-bottom: 2px; text-decoration: none;
                        background: {{ $active ? 'var(--emerald-50)' : 'transparent' }};
                        color: {{ $active ? 'var(--emerald-800)' : 'var(--text)' }};
                        font-weight: {{ $active ? 600 : 500 }}; font-size: 13px;">
                    <x-icon :name="$t['i']" size="14"/>{{ $t['l'] }}
                </a>
            @endforeach
        </div>

        <div class="card-elev" style="padding: 32px;">
            @if($heading)
                <h2 class="serif" style="font-size: 22px; font-weight: 600; margin-bottom: 6px;">{{ $heading }}</h2>
                @if($subheading)<p style="color: var(--text-soft); margin-bottom: 24px; font-size: 13px;">{{ $subheading }}</p>@endif
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
