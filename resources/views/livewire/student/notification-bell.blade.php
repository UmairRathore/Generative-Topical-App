@php
    // Per-type visual: [icon, color]. Falls back to a neutral bell.
    $meta = [
        'exam_released'    => ['calendar', 'var(--accent)'],
        'exam_scheduled'   => ['calendar', 'var(--accent)'],
        'exam_due_today'   => ['clock', 'var(--warn)'],
        'results_released' => ['check', 'var(--ok)'],
        'exam_missed'      => ['flag', 'var(--bad)'],
    ];
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" style="position: relative;">

    <button type="button" @click="open = !open" aria-label="Notifications"
            style="position: relative; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: transparent; border: 0; color: var(--text-soft); cursor: pointer;">
        <x-icon name="bell" size="18"/>
        @if ($this->unreadCount > 0)
            <span style="position: absolute; top: -3px; right: -3px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px; background: var(--bad); color: #fff; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center;">{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         style="position: fixed; top: 58px; right: 12px; left: auto; width: 380px; max-width: calc(100vw - 24px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: 0 18px 50px rgba(0,0,0,.28); z-index: 50; overflow: hidden;">

        <div class="flex items-center justify-between" style="padding: 12px 14px; border-bottom: 1px solid var(--border);">
            <span style="font-size: 14px; font-weight: 600;">Notifications</span>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead" style="font-size: 12px; background: none; border: 0; color: var(--accent); cursor: pointer;">Mark all read</button>
            @endif
        </div>

        <div style="max-height: 380px; overflow-y: auto;">
            @forelse ($this->items as $n)
                @php [$icon, $color] = $meta[$n->type] ?? ['bell', 'var(--text-soft)']; $d = $n->data; $url = $d['url'] ?? null; @endphp
                {{-- Whole row opens the update (and marks it read). --}}
                <div @if ($url || ! $n->read_at) wire:click="open({{ $n->id }})" @endif
                     class="flex" style="gap: 10px; padding: 11px 14px; border-bottom: 1px solid var(--border); cursor: {{ $url || ! $n->read_at ? 'pointer' : 'default' }}; {{ $n->read_at ? '' : 'background: rgba(var(--accent-rgb),0.06);' }}">
                    <span style="margin-top: 1px; flex: none; color: {{ $color }};"><x-icon name="{{ $icon }}" size="17"/></span>
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-size: 13px; line-height: 1.4;"><span style="font-weight: 600;">{{ $d['title'] ?? 'Update' }}</span></div>
                        <div style="font-size: 12px; color: var(--text-soft); margin-top: 1px;">{{ $d['body'] ?? '' }}</div>
                        <div style="font-size: 11px; color: var(--text-faint); margin-top: 3px;">
                            {{ $d['subject'] ?? '' }}@if (! empty($d['teacher'])) · {{ $d['teacher'] }}@endif · {{ $n->created_at->diffForHumans() }}
                        </div>
                    </div>
                    @unless ($n->read_at)
                        <span style="flex: none; width: 7px; height: 7px; border-radius: 999px; background: var(--accent); margin-top: 6px;"></span>
                    @endunless
                </div>
            @empty
                <div style="padding: 30px 14px; text-align: center; color: var(--text-faint); font-size: 13px;">No notifications yet.</div>
            @endforelse
        </div>

        @if ($this->totalCount > 0)
            <div style="border-top: 1px solid var(--border); text-align: center; padding: 9px;">
                <a href="{{ route('v2.student.notifications.index') }}" wire:navigate style="font-size: 12px; color: var(--accent); text-decoration: none;">View all ({{ $this->totalCount }}) →</a>
            </div>
        @endif
    </div>
</div>
