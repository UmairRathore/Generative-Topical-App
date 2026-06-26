@php
    $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)');
    $statusAvg = function ($c, $f) {
        return $c > $f ? ['improving', 'var(--ok)'] : ($c < $f ? ['worsening', 'var(--bad)'] : ['no change', 'var(--warn)']);
    };
@endphp

<div x-data="{ open: false, tab: 'attention', detail: null }"
     @keydown.escape.window="open = false"
     style="position: relative;">

    <button type="button" @click="open = !open" aria-label="Notifications"
            style="position: relative; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: transparent; border: 0; color: var(--text-soft); cursor: pointer;">
        <x-icon name="bell" size="18"/>
        @if ($this->unreadCount > 0)
            <span style="position: absolute; top: -3px; right: -3px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px; background: var(--bad); color: #fff; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center;">{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         style="position: fixed; top: 58px; right: 12px; left: auto; width: 380px; max-width: calc(100vw - 24px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: 0 18px 50px rgba(0,0,0,.28); z-index: 50; overflow: hidden;">

        <div class="flex items-center justify-between" style="padding: 12px 14px;">
            <span style="font-size: 14px; font-weight: 600;">Notifications</span>
            <button type="button" wire:click="markAllRead" style="font-size: 12px; background: none; border: 0; color: var(--accent); cursor: pointer;">Mark all read</button>
        </div>

        {{-- Segmented button tabs (same control as Open/Resolved) --}}
        <div style="padding: 8px 12px 10px; border-bottom: 1px solid var(--border);">
            <div class="flex" style="border: 1px solid var(--border); border-radius: 9px; overflow: hidden;">
                <button type="button" @click="tab = 'updates'; detail = null"
                        :class="tab === 'updates' ? 'btn-primary' : 'btn-ghost'"
                        class="btn btn-sm" style="flex: 1; border: 0; border-radius: 0; justify-content: center; gap: 7px;">
                    Updates
                    <span :style="tab === 'updates' ? ' color: #fff;' : ' color: var(--text-soft);'" style="min-width: 17px; height: 17px; padding: 0 5px; border-radius: 999px; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $this->updateCount }}</span>
                </button>
                <button type="button" @click="tab = 'attention'; detail = null"
                        :class="tab === 'attention' ? 'btn-primary' : 'btn-ghost'"
                        class="btn btn-sm" style="flex: 1; border: 0; border-radius: 0; justify-content: center; gap: 7px;">
                    Needs attention
                    <span :style="tab === 'attention' ? ' color: #fff;' : ' color: var(--text-soft);'" style="min-width: 17px; height: 17px; padding: 0 5px; border-radius: 999px; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $this->attentionCount }}</span>
                </button>
            </div>
        </div>

        {{-- LISTS (capped preview, scrollable). Older items aren't deleted - they live on the full page. --}}
        <div x-show="!detail" style="max-height: 360px; overflow-y: auto;">

            {{-- Updates --}}
            <div x-show="tab === 'updates'">
                @forelse ($this->updates as $n)
                    @php $url = $n->data['url'] ?? null; $icon = $n->type === 'question_flag' ? 'flag' : ($n->type === 'results_due' ? 'check' : 'calendar'); @endphp
                    <a @if ($url) href="{{ $url }}" @elseif (! $n->read_at) wire:click="markRead({{ $n->id }})" @endif
                       class="flex" style="gap: 10px; padding: 11px 14px; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; cursor: {{ $url || ! $n->read_at ? 'pointer' : 'default' }}; {{ $n->read_at ? '' : 'background: rgba(var(--accent-rgb),0.05);' }}">
                        <span style="margin-top: 1px; color: var(--accent);"><x-icon name="{{ $icon }}" size="17"/></span>
                        <div style="min-width: 0;">
                            <div style="font-size: 13px; line-height: 1.45;"><span style="font-weight: 500;">{{ $n->data['title'] ?? 'Update' }}</span> - {{ $n->data['body'] ?? '' }}</div>
                            <div style="font-size: 11px; color: var(--text-faint); margin-top: 2px;">{{ $n->created_at->diffForHumans() }}</div>
                        </div>
                    </a>
                @empty
                    <div style="padding: 26px 14px; text-align: center; color: var(--text-faint); font-size: 13px;">No updates.</div>
                @endforelse
            </div>

            {{-- Needs attention --}}
            <div x-show="tab === 'attention'">
                @forelse ($this->attention as $n)
                    @php
                        $d = $n->data;
                        $topics = $d['topics'] ?? [];
                        $count = count($topics);
                        $cavg = (int) ($d['current_avg'] ?? 0);
                        $favg = (int) ($d['flagged_avg'] ?? $cavg);
                        [$stLabel, $stColor] = $statusAvg($cavg, $favg);
                        $payload = [
                            'id' => $n->id,
                            'title' => $d['title'] ?? 'Student needs attention',
                            'subject' => $d['subject'] ?? '',
                            'flaggedAt' => $n->created_at->format('j M'),
                            'status' => $stLabel,
                            'statusColor' => $stColor,
                            'target' => (int) ($d['target'] ?? 50),
                            'url' => $n->student ? route('v2.teacher.students.show', $n->student) : null,
                            'topics' => array_map(fn ($t) => [
                                'topic' => $t['topic'],
                                'current' => (int) $t['current'],
                                'tone' => $tone((int) $t['current']),
                                'subtopics' => $t['subtopics'] ?? [],
                            ], $topics),
                        ];
                    @endphp
                    <button type="button" @click="detail = {{ \Illuminate\Support\Js::from($payload) }}@if (! $n->read_at); $wire.markRead({{ $n->id }})@endif"
                            class="flex" style="width: 100%; text-align: left; gap: 10px; padding: 11px 14px; border: 0; border-bottom: 1px solid var(--border); background: {{ $n->read_at ? 'transparent' : 'rgba(var(--accent-rgb),0.05)' }}; cursor: pointer;">
                        <span style="width: 30px; height: 30px; flex: none; border-radius: 50%; background: var(--soft-surface); display: flex; align-items: center; justify-content: center; color: {{ $tone($cavg) }};"><x-icon name="flag" size="15"/></span>
                        <span style="flex: 1; min-width: 0;">
                            <span class="flex items-center justify-between" style="gap: 8px;">
                                <span style="font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $payload['title'] }}</span>
                                <span style="flex: none; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 999px; color: {{ $stColor }}; border: 1px solid {{ $stColor }};">{{ $stLabel }}</span>
                            </span>
                            <span style="display: block; font-size: 12px; color: var(--text-soft); margin-top: 3px;">{{ $payload['subject'] }} · {{ $count }} {{ \Illuminate\Support\Str::plural('topic', $count) }} to work on</span>
                        </span>
                    </button>
                @empty
                    <div style="padding: 26px 14px; text-align: center; color: var(--text-faint); font-size: 13px;">No students need attention.</div>
                @endforelse
            </div>
        </div>

        {{-- DETAIL: every weak topic in the subject, each with its focus subtopics --}}
        <div x-show="detail" x-cloak style="padding: 12px 14px;">
            <button type="button" @click="detail = null" style="font-size: 12px; background: none; border: 0; color: var(--text-soft); cursor: pointer; padding: 0 0 10px; display: inline-flex; align-items: center; gap: 4px;"><x-icon name="chev-l" size="14"/> Back</button>

            <div style="font-size: 14px; font-weight: 600;" x-text="detail?.title"></div>
            <div class="flex items-center justify-between" style="margin: 2px 0 12px;">
                <span style="font-size: 12px; color: var(--text-soft);"><span x-text="detail?.subject"></span> · <span x-text="detail?.topics?.length"></span> topics · flagged <span x-text="detail?.flaggedAt"></span></span>
                <span style="font-size: 10.5px; font-weight: 600; padding: 1px 8px; border-radius: 999px;" :style="{ color: detail?.statusColor, border: '1px solid ' + detail?.statusColor }" x-text="detail?.status"></span>
            </div>

            <div style="max-height: 270px; overflow-y: auto; margin-bottom: 12px;">
                <template x-for="(t, i) in detail?.topics" :key="i">
                    <div style="background: var(--soft-surface); border-radius: 9px; padding: 10px 11px; margin-bottom: 8px;">
                        <div class="flex items-center justify-between">
                            <span style="font-size: 12.5px; font-weight: 500;" x-text="t.topic"></span>
                            <span style="font-size: 12px; font-weight: 700;" :style="{ color: t.tone }"><span x-text="t.current"></span>%</span>
                        </div>
                        <div style="position: relative; height: 7px; border-radius: 999px; background: var(--border); overflow: hidden; margin: 7px 0 5px;">
                            <div style="position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px;" :style="{ width: Math.max(t.current, 2) + '%', background: t.tone }"></div>
                            <div style="position: absolute; top: -2px; bottom: -2px; width: 0; border-left: 1.5px dashed var(--text-soft);" :style="{ left: (detail?.target ?? 50) + '%' }"></div>
                        </div>
                        <template x-if="t.subtopics?.length">
                            <div style="font-size: 11.5px; color: var(--text-soft);">
                                Focus: <template x-for="(s, j) in t.subtopics" :key="j"><span><span x-show="j > 0">, </span><span style="color: var(--text); font-weight: 500;" x-text="s.subtopic"></span> (<span x-text="s.percent"></span>%)</span></template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex" style="gap: 8px;">
                <a x-show="detail?.url" :href="detail?.url" class="btn btn-ghost btn-sm" style="flex: 1; justify-content: center;"><x-icon name="chart" size="13"/> Analytics</a>
                <button type="button" @click="$wire.markHandled(detail.id); detail = null" class="btn btn-ghost btn-sm" style="flex: 1; justify-content: center;"><x-icon name="check" size="13"/> Handled</button>
                <button type="button" @click="$wire.snooze(detail.id); detail = null" class="btn btn-ghost btn-sm" style="flex: none;" aria-label="Snooze"><x-icon name="clock" size="14"/></button>
            </div>
        </div>

        <div style="border-top: 1px solid var(--border); text-align: center; padding: 9px;">
            <a :href="'{{ route('v2.teacher.notifications.index') }}?tab=' + tab" wire:navigate style="font-size: 12px; color: var(--accent); text-decoration: none;">View all ({{ $this->attentionCount + $this->updateCount }}) →</a>
        </div>
    </div>
</div>
