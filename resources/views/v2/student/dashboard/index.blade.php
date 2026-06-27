@extends('v2.layouts.student')
@section('page_title', 'Dashboard')

@php
    $user = auth('v2_student')->user();
    $barColor = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)');
    $overall = $stats['overall'];
    $hasActivity = $overall['total'] > 0 || ! empty($stats['upcoming']);
@endphp

@section('content')
<style>[x-cloak]{display:none!important}.tx-modal{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px}
.topic-chip{display:inline-flex;align-items:center;gap:5px;border:0;background:none;cursor:pointer;font:inherit;}
.subj-detail{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}
@media (max-width:700px){.subj-detail{grid-template-columns:1fr;gap:20px;}}</style>

<div style="margin-bottom: 24px;">
    <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Student Dashboard</div>
    <h2 class="serif" style="font-size: 30px; font-weight: 600; margin-top: 4px;">Welcome, {{ $user?->name }}</h2>
    <p style="color: var(--text-soft); margin-top: 2px; font-size: 14px;">{{ $user?->school?->name ?? 'Your School' }}</p>
</div>

@unless ($hasActivity)
    <div style="padding: 44px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg);">
        <p style="color: var(--text-soft); font-size: 14px;">No tests have been set for your class yet. You'll be notified the moment your teacher releases one.</p>
        <a href="{{ route('v2.student.exams.index') }}" class="btn btn-primary btn-sm" style="margin-top: 14px;"><x-icon name="play" size="12"/> Go to My Exams</a>
    </div>
@else
    {{-- Combined overview hero: totals + overall-average donut --}}
    @php
        $avg = $overall['avg'];
        $r = 52; $c = 2 * M_PI * $r; $dash = $c * $avg / 100; $avgColor = $barColor($avg);
    @endphp
    <div class="flex items-center" style="gap: 28px; flex-wrap: wrap; justify-content: space-between; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px 26px; margin-bottom: 24px;">
        <div class="flex" style="gap: 44px; flex-wrap: wrap;">
            <div>
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                    <x-icon name="clipboard" size="13"/> Total tests
                </div>
                <div class="serif" style="font-size: 34px; font-weight: 600; margin-top: 6px; line-height: 1;">{{ $overall['total'] }}</div>
                <div style="font-size: 11.5px; color: var(--text-soft); margin-top: 6px;">{{ $overall['completed'] }} completed · {{ $overall['missed'] }} missed</div>
            </div>
            <div>
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                    <x-icon name="book" size="13"/> Subjects
                </div>
                <div class="serif" style="font-size: 34px; font-weight: 600; margin-top: 6px; line-height: 1;">{{ $overall['subjects'] }}</div>
                <div style="font-size: 11.5px; color: var(--text-soft); margin-top: 6px;">enrolled</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <svg width="124" height="124" viewBox="0 0 130 130">
                <circle cx="65" cy="65" r="{{ $r }}" fill="none" stroke="var(--soft-surface)" stroke-width="13"/>
                @if ($avg > 0)
                    <circle cx="65" cy="65" r="{{ $r }}" fill="none" stroke="{{ $avgColor }}" stroke-width="13" stroke-linecap="round"
                            stroke-dasharray="{{ $dash }} {{ $c }}" transform="rotate(-90 65 65)"/>
                @endif
                <text x="65" y="63" text-anchor="middle" style="font-size: 27px; font-weight: 700; fill: var(--text);">{{ $avg }}%</text>
                <text x="65" y="82" text-anchor="middle" style="font-size: 10px; fill: var(--text-faint); text-transform: uppercase; letter-spacing: .06em;">avg score</text>
            </svg>
        </div>
    </div>

    <div x-data="{ tOpen: false, tTitle: '', tTopics: [] }">

    {{-- Upcoming / due exams --}}
    @if (! empty($stats['upcoming']))
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px 22px; margin-bottom: 18px;">
            <div class="flex items-center gap-2" style="margin-bottom: 14px;">
                <x-icon name="calendar" size="15"/>
                <h3 class="serif" style="font-size: 17px; font-weight: 600;">What's next</h3>
                <span class="badge badge-soft">{{ count($stats['upcoming']) }}</span>
            </div>
            <div class="space-y-2">
                @foreach ($stats['upcoming'] as $u)
                    @php
                        $topics = $u['topics'] ?? [];
                        $topicLabel = count($topics) === 1 ? $topics[0] : (count($topics) > 1 ? 'Mixed' : 'Mixed');
                    @endphp
                    <div class="flex items-center justify-between" style="gap: 12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 9px;">
                        <div style="min-width: 0;">
                            <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                                <span style="font-size: 13.5px; font-weight: 600;">{{ $u['title'] }}</span>
                                @if ($u['status'] === 'live')
                                    <span style="font-size: 10px; font-weight: 700; color: var(--ok); border: 1px solid var(--ok); border-radius: 999px; padding: 1px 7px;">OPEN NOW</span>
                                @else
                                    <span style="font-size: 10px; font-weight: 700; color: var(--accent); border: 1px solid var(--accent); border-radius: 999px; padding: 1px 7px;">SCHEDULED</span>
                                @endif
                                @if (count($topics))
                                    <button type="button" class="topic-chip badge badge-emerald" style="font-size: 10.5px;"
                                            @click="tTitle = @js($u['title']); tTopics = @js($topics); tOpen = true" title="View topics">
                                        {{ $topicLabel }}@if (count($topics) > 1) <span style="opacity:.8;">· {{ count($topics) }}</span>@endif
                                        <x-icon name="list" size="10"/>
                                    </button>
                                @endif
                            </div>
                            <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 4px;">
                                {{ $u['subject'] }}@if (! empty($u['teacher'])) · {{ $u['teacher'] }}@endif
                                · {{ $u['questions'] ?? 0 }} questions
                                @if ($u['status'] === 'live' && $u['due']) · expires {{ $u['due']->format('j M, g:i A') }}
                                @elseif ($u['status'] === 'scheduled' && $u['available_from']) · opens {{ $u['available_from']->format('j M, g:i A') }}
                                @endif
                            </div>
                        </div>
                        @if ($u['status'] === 'live')
                            <a href="{{ route('v2.student.exams.take', hid($u['exam_id'])) }}" class="btn btn-primary btn-sm" style="flex: none;"><x-icon name="play" size="12"/> Start</a>
                        @else
                            <span style="flex: none; font-size: 11.5px; color: var(--text-faint);">{{ $u['available_from']?->diffForHumans() }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Per subject (collapsible) --}}
    @foreach ($stats['subjects'] as $subject)
        @php
            $rowH = 46;
            $scrollTests = $subject['tests_count'] > $subject['total_topics'] && $subject['total_topics'] > 0;
            $testsMaxH = max($subject['total_topics'], 1) * $rowH;
            $subjColor = $barColor($subject['avg']);
        @endphp
        <div x-data="{ open: false }" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px 22px; margin-bottom: 16px;">

            {{-- Header (always visible) --}}
            <button type="button" @click="open = !open" class="flex items-center justify-between" style="width: 100%; background: none; border: 0; padding: 0; text-align: left; cursor: pointer; gap: 12px;">
                <div class="flex items-center gap-3" style="min-width: 0; flex-wrap: wrap;">
                    <span style="flex: none; width: 34px; height: 34px; border-radius: 9px; background: var(--soft-surface); display: flex; align-items: center; justify-content: center; color: var(--accent);"><x-icon name="book" size="17"/></span>
                    <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                    @if (! empty($subject['teacher']))
                        <span class="flex items-center gap-1" style="font-size: 12px; color: var(--text-faint);"><x-icon name="user" size="11"/> {{ $subject['teacher'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3" style="flex: none;">
                    <span class="badge badge-soft">{{ $subject['tests_count'] }} {{ Str::plural('test', $subject['tests_count']) }}</span>
                    <span style="font-size: 12px; color: var(--text-faint);">avg</span>
                    <span class="badge" style="font-size: 13px; font-weight: 700; color: {{ $subjColor }}; border: 1px solid {{ $subjColor }};">{{ $subject['avg'] }}%</span>
                    <span class="flex" style="color: var(--text-soft); transition: transform .2s;" :style="open ? 'transform: rotate(180deg);' : ''"><x-icon name="chev-d" size="16"/></span>
                </div>
            </button>

            {{-- Summary chips (always visible) --}}
            <div class="flex items-center" style="gap: 8px; flex-wrap: wrap; margin-top: 14px;">
                <span class="badge badge-soft" style="font-size: 11.5px;"><x-icon name="check" size="11"/> {{ $subject['tests_count'] }} attempted</span>
                <span class="badge badge-soft" style="font-size: 11.5px; color: {{ $subject['missed_count'] ? 'var(--bad)' : 'var(--text-soft)' }};"><x-icon name="flag" size="11"/> {{ $subject['missed_count'] }} missed</span>
                <span class="badge badge-soft" style="font-size: 11.5px;"><x-icon name="book" size="11"/> {{ $subject['attempted_topics'] }}/{{ $subject['total_topics'] }} topics practised</span>
                <span class="badge badge-soft" style="font-size: 11.5px;">{{ $subject['examined_topics'] }} examined</span>
            </div>

            {{-- Detail (expanded) --}}
            <div x-show="open" x-cloak x-transition.opacity style="margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--border);">
                <div class="subj-detail">

                    {{-- By topic - every syllabus topic (the coverage reference) --}}
                    <div>
                        <div class="flex items-center justify-between" style="margin-bottom: 12px;">
                            <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint);">By topic</span>
                            <span style="font-size: 11px; color: var(--text-faint);">{{ $subject['total_topics'] }} topics</span>
                        </div>
                        @forelse ($subject['topics'] as $t)
                            <div style="margin-bottom: 12px;">
                                <div class="flex items-center justify-between" style="font-size: 12.5px; margin-bottom: 5px;">
                                    <span style="font-weight: 500; {{ $t['attempted'] ? '' : 'color: var(--text-faint);' }}">{{ $t['topic'] }}</span>
                                    @if ($t['attempted'])
                                        <span style="color: var(--text-soft);">{{ $t['correct'] }}/{{ $t['total'] }} · {{ $t['percent'] }}%</span>
                                    @elseif ($t['examined'])
                                        <span style="color: var(--text-faint); font-size: 11px;">examined · not attempted</span>
                                    @else
                                        <span style="color: var(--text-faint); font-size: 11px;">not yet examined</span>
                                    @endif
                                </div>
                                @if ($t['attempted'])
                                    <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); overflow: hidden;">
                                        <div style="height: 100%; width: {{ $t['percent'] }}%; border-radius: 99px; background: {{ $barColor($t['percent']) }};"></div>
                                    </div>
                                @else
                                    <div style="height: 7px; border-radius: 99px; background: var(--soft-surface); {{ $t['examined'] ? '' : 'opacity: .5;' }} border: 1px dashed var(--border);"></div>
                                @endif
                            </div>
                        @empty
                            <p style="font-size: 12.5px; color: var(--text-faint);">No syllabus topics found for this subject.</p>
                        @endforelse
                    </div>

                    {{-- Tests - numbered, newest first, scrollable once they exceed the topic-count reference --}}
                    <div>
                        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                        <div class="space-y-2" @if ($scrollTests) style="max-height: {{ $testsMaxH }}px; overflow-y: auto; padding-right: 6px;" @endif>
                            @forelse ($subject['tests'] as $test)
                                <a href="{{ route('v2.student.exams.result', hid($test['exam_id'])) }}"
                                   class="flex items-center justify-between" style="text-decoration: none; color: inherit; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; gap: 10px;">
                                    <div class="flex items-center" style="gap: 10px; min-width: 0;">
                                        <span style="flex: none; width: 22px; height: 22px; border-radius: 6px; background: var(--soft-surface); color: var(--text-faint); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $loop->iteration }}</span>
                                        <div style="min-width: 0;">
                                            <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                            <div style="font-size: 11.5px; color: var(--text-faint);">
                                                {{ $test['date']?->diffForHumans() }}@unless ($test['results']) · result pending @endunless
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right; flex: none;">
                                        @if ($test['results'])
                                            <span style="font-weight: 700; font-size: 14px; color: {{ $barColor($test['percent']) }};">{{ $test['percent'] }}%</span>
                                            <div style="font-size: 11px; color: var(--text-faint);">{{ $test['score'] }}/{{ $test['total'] }}</div>
                                        @else
                                            <span style="font-size: 11.5px; color: var(--text-faint);">pending</span>
                                        @endif
                                    </div>
                                </a>
                            @empty
                                <p style="font-size: 12.5px; color: var(--text-faint);">No tests completed in this subject yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Topics modal (shared) - teleported so the content column's transform can't trap it --}}
    <template x-teleport="body">
    <div x-show="tOpen" x-cloak class="tx-modal" @keydown.escape.window="tOpen = false" style="display:none;">
        <div @click="tOpen = false" style="position: absolute; inset: 0; background: rgba(0,0,0,.5);"></div>
        <div x-show="tOpen" x-transition
             style="position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; width: 100%; max-width: 420px; box-shadow: 0 24px 64px rgba(0,0,0,.35);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <h3 class="serif" style="font-size: 17px; font-weight: 600;">Topics covered</h3>
                <button type="button" @click="tOpen = false" style="border: 0; background: none; cursor: pointer; color: var(--text-soft); padding: 4px;"><x-icon name="x" size="16"/></button>
            </div>
            <p style="font-size: 12.5px; color: var(--text-soft); margin-bottom: 14px;" x-text="tTitle"></p>
            <div style="max-height: 320px; overflow-y: auto;">
                <template x-for="(t, i) in tTopics" :key="i">
                    <div class="flex items-center gap-2" style="padding: 9px 0; border-top: 1px solid var(--border);">
                        <span style="flex: none; width: 20px; height: 20px; border-radius: 6px; background: var(--soft-surface); color: var(--text-faint); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;" x-text="i + 1"></span>
                        <span style="font-size: 13px;" x-text="t"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </template>

    </div>
@endunless
@endsection
