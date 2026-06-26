@php
    $meta = [
        'exam_released'    => ['calendar', 'var(--accent)', 'New test'],
        'exam_scheduled'   => ['calendar', 'var(--accent)', 'Scheduled'],
        'exam_due_today'   => ['clock', 'var(--warn)', 'Due today'],
        'results_released' => ['check', 'var(--ok)', 'Results'],
        'exam_missed'      => ['flag', 'var(--bad)', 'Missed'],
    ];
@endphp

<div style="max-width: 920px;">

    {{-- Filter + actions --}}
    <div class="flex items-center" style="gap: 10px; margin-bottom: 18px; flex-wrap: wrap;">
        <div class="flex" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
            <button type="button" wire:click="$set('filter', 'all')" class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-ghost' }}" style="border: 0; border-radius: 0;">All</button>
            <button type="button" wire:click="$set('filter', 'unread')" class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-ghost' }}" style="border: 0; border-radius: 0;">
                Unread
                @if ($this->unreadCount > 0)
                    <span style="margin-left: 6px; min-width: 17px; height: 17px; padding: 0 5px; border-radius: 999px; background: var(--bad); color: #fff; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $this->unreadCount }}</span>
                @endif
            </button>
        </div>
        <div class="flex-1"></div>
        @if ($this->unreadCount > 0)
            <button type="button" wire:click="markAllRead" class="btn btn-ghost btn-sm"><x-icon name="check" size="13"/> Mark all read</button>
        @endif
    </div>

    {{-- Stream --}}
    <div class="space-y-2">
        @forelse ($this->items as $n)
            @php [$icon, $color, $tag] = $meta[$n->type] ?? ['bell', 'var(--text-soft)', 'Update']; $d = $n->data; $url = $d['url'] ?? null; @endphp
            {{-- On mobile this stacks: statement first, then the action buttons. --}}
            <div class="flex flex-col sm:flex-row" style="gap: 14px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 15px 18px; {{ $n->read_at ? '' : 'border-left: 3px solid '.$color.'; background: rgba(var(--accent-rgb),0.04);' }}">
                <div class="flex" style="gap: 14px; flex: 1; min-width: 0;">
                    <span style="flex: none; width: 34px; height: 34px; border-radius: 9px; background: var(--soft-surface); display: flex; align-items: center; justify-content: center; color: {{ $color }};">
                        <x-icon name="{{ $icon }}" size="17"/>
                    </span>
                    <div style="flex: 1; min-width: 0;">
                        <div class="flex items-center" style="gap: 8px;">
                            <span style="font-size: 14px; font-weight: 600;">{{ $d['title'] ?? 'Update' }}</span>
                            <span style="flex: none; font-size: 10.5px; font-weight: 600; padding: 1px 8px; border-radius: 999px; color: {{ $color }}; border: 1px solid {{ $color }};">{{ $tag }}</span>
                            @unless ($n->read_at)
                                <span style="flex: none; font-size: 10.5px; font-weight: 700; color: var(--accent);">• NEW</span>
                            @endunless
                        </div>
                        <div style="font-size: 13px; color: var(--text-soft); margin-top: 3px;">{{ $d['body'] ?? '' }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 6px;">
                            @if (! empty($d['subject'])){{ $d['subject'] }} · @endif
                            @if (! empty($d['teacher'])){{ $d['teacher'] }} · @endif
                            {{ $n->created_at->format('j M Y, g:i A') }} ({{ $n->created_at->diffForHumans() }})
                        </div>
                    </div>
                </div>
                <div class="flex items-center" style="gap: 8px; flex: none;">
                    @if ($url)
                        <a href="{{ $url }}" wire:navigate class="btn btn-ghost btn-sm"><x-icon name="play" size="12"/> Open</a>
                    @endif
                    @if ($n->read_at)
                        <button type="button" wire:click="markUnread({{ $n->id }})" class="btn btn-ghost btn-sm"><x-icon name="eye-off" size="13"/> Mark as unread</button>
                    @else
                        <button type="button" wire:click="markRead({{ $n->id }})" class="btn btn-ghost btn-sm"><x-icon name="check" size="13"/> Mark as read</button>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding: 48px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
                {{ $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}
            </div>
        @endforelse
    </div>

    <div style="margin-top: 18px;">
        {{ $this->items->links() }}
    </div>
</div>
