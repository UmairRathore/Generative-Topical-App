@php
    $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)');
    $statusAvg = function ($c, $f) {
        return $c > $f ? ['improving', 'var(--ok)'] : ($c < $f ? ['worsening', 'var(--bad)'] : ['no change', 'var(--warn)']);
    };
@endphp

<div style="max-width: 760px;">

    {{-- Tabs --}}
    <div class="flex" style="gap: 6px; margin-bottom: 18px;">
        <button type="button" wire:click="$set('tab', 'attention')" class="btn btn-sm {{ $tab === 'attention' ? 'btn-primary' : 'btn-ghost' }}"><x-icon name="flag" size="13"/> Needs attention</button>
        <button type="button" wire:click="$set('tab', 'updates')" class="btn btn-sm {{ $tab === 'updates' ? 'btn-primary' : 'btn-ghost' }}"><x-icon name="bell" size="13"/> Updates</button>
        <div class="flex-1"></div>
        <button type="button" wire:click="markAllRead" class="btn btn-ghost btn-sm"><x-icon name="check" size="13"/> Mark all read</button>
    </div>

    {{-- Filters (attention only) --}}
    @if ($tab === 'attention')
        <div class="flex items-center" style="gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
            <div class="flex" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                <button type="button" wire:click="$set('status', 'open')" class="btn btn-sm {{ $status === 'open' ? 'btn-primary' : 'btn-ghost' }}" style="border: 0; border-radius: 0;">Open</button>
                <button type="button" wire:click="$set('status', 'resolved')" class="btn btn-sm {{ $status === 'resolved' ? 'btn-primary' : 'btn-ghost' }}" style="border: 0; border-radius: 0;">Resolved</button>
            </div>
            <select wire:model.live="classId" style="padding: 7px 10px; font-size: 13px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text);">
                <option value="">All classes</option>
                @foreach ($this->classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="subjectId" style="padding: 7px 10px; font-size: 13px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text);">
                <option value="">All subjects</option>
                @foreach ($this->subjects as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- List --}}
    <div class="space-y-2">
        @forelse ($this->items as $n)
            @if ($tab === 'attention')
                @php
                    $d = $n->data;
                    $topics = $d['topics'] ?? [];
                    $count = count($topics);
                    $cavg = (int) ($d['current_avg'] ?? 0);
                    $favg = (int) ($d['flagged_avg'] ?? $cavg);
                    [$stLabel, $stColor] = $statusAvg($cavg, $favg);
                    $tgt = (int) ($d['target'] ?? 50);
                @endphp
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
                    <div class="flex items-start justify-between" style="gap: 12px; margin-bottom: 14px;">
                        <div style="min-width: 0;">
                            <div style="font-size: 14px; font-weight: 600;">{{ $d['title'] ?? 'Student needs attention' }}</div>
                            <div style="font-size: 12.5px; color: var(--text-soft); margin-top: 2px;">{{ $d['subject'] ?? '' }} · {{ $count }} {{ \Illuminate\Support\Str::plural('topic', $count) }} · flagged {{ $n->created_at->format('j M') }}</div>
                        </div>
                        @if ($n->resolved_at)
                            <span class="badge badge-emerald" style="flex: none;"><x-icon name="check" size="11"/> Resolved</span>
                        @else
                            <span style="flex: none; font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 999px; color: {{ $stColor }}; border: 1px solid {{ $stColor }};">{{ $stLabel }}</span>
                        @endif
                    </div>

                    {{-- One block per weak topic, each with its focus subtopics --}}
                    <div class="space-y-2" style="margin-bottom: 14px;">
                        @foreach ($topics as $t)
                            @php $tc = (int) ($t['current'] ?? 0); @endphp
                            <div style="background: var(--soft-surface); border-radius: 9px; padding: 11px 13px;">
                                <div class="flex items-center justify-between">
                                    <span style="font-size: 13px; font-weight: 500;">{{ $t['topic'] }}</span>
                                    <span style="font-size: 12.5px; font-weight: 700; color: {{ $tone($tc) }};">{{ $tc }}%</span>
                                </div>
                                <div style="position: relative; height: 7px; border-radius: 999px; background: var(--border); overflow: hidden; margin: 8px 0 6px;">
                                    <div style="position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px; width: {{ max($tc, 2) }}%; background: {{ $tone($tc) }};"></div>
                                    <div style="position: absolute; top: -2px; bottom: -2px; left: {{ $tgt }}%; width: 0; border-left: 1.5px dashed var(--text-soft);"></div>
                                </div>
                                @if (! empty($t['subtopics']))
                                    <div style="font-size: 11.5px; color: var(--text-soft);">
                                        Focus: @foreach ($t['subtopics'] as $s)<span style="color: var(--text); font-weight: 500;">{{ $s['subtopic'] }}</span> ({{ $s['percent'] }}%)@if (! $loop->last), @endif @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="flex" style="gap: 8px;">
                        @if ($n->student)
                            <a href="{{ route('v2.teacher.students.show', $n->student) }}" class="btn btn-ghost btn-sm"><x-icon name="chart" size="13"/> View analytics</a>
                        @endif
                        @unless ($n->resolved_at)
                            <button type="button" wire:click="markHandled({{ $n->id }})" class="btn btn-ghost btn-sm"><x-icon name="check" size="13"/> Mark handled</button>
                            <button type="button" wire:click="snooze({{ $n->id }})" class="btn btn-ghost btn-sm"><x-icon name="clock" size="13"/> Snooze</button>
                        @endunless
                    </div>
                </div>
            @else
                <div class="flex" style="gap: 12px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 18px; {{ $n->read_at ? '' : 'border-left: 3px solid var(--accent);' }}">
                    <span style="margin-top: 1px; color: var(--accent);"><x-icon name="{{ $n->type === 'results_due' ? 'check' : 'calendar' }}" size="18"/></span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 13.5px;"><span style="font-weight: 500;">{{ $n->data['title'] ?? 'Update' }}</span> — {{ $n->data['body'] ?? '' }}</div>
                        <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 2px;">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                    @unless ($n->read_at)
                        <button type="button" wire:click="markRead({{ $n->id }})" class="btn btn-ghost btn-sm" style="flex: none;">Mark read</button>
                    @endunless
                </div>
            @endif
        @empty
            <div style="padding: 48px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
                {{ $tab === 'attention' ? ($status === 'resolved' ? 'No resolved flags yet.' : 'No students need attention right now.') : 'No updates.' }}
            </div>
        @endforelse
    </div>

    <div style="margin-top: 18px;">
        {{ $this->items->links() }}
    </div>
</div>
