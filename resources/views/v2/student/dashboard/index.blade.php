@extends('v2.layouts.student')
@section('page_title', 'Dashboard')

@php
    $user = auth('v2_student')->user();
    $barColor = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)');
    $overall = $stats['overall'];
    $hasActivity = $overall['total'] > 0 || ! empty($stats['upcoming']);
@endphp

@section('content')
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
    {{-- Overall stat cards --}}
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px;">
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon name="clipboard" size="13"/> Total tests
            </div>
            <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $overall['total'] }}</div>
            <div style="font-size: 11.5px; color: var(--text-soft); margin-top: 2px;">{{ $overall['completed'] }} completed · {{ $overall['missed'] }} missed</div>
        </div>
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon name="chart" size="13"/> Overall average
            </div>
            <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $overall['avg'] }}%</div>
            <div style="font-size: 11.5px; color: var(--text-soft); margin-top: 2px;">across completed tests</div>
        </div>
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
            <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                <x-icon name="book" size="13"/> Subjects
            </div>
            <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $overall['subjects'] }}</div>
            <div style="font-size: 11.5px; color: var(--text-soft); margin-top: 2px;">enrolled</div>
        </div>
    </div>

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
                    <div class="flex items-center justify-between" style="gap: 12px; padding: 11px 13px; border: 1px solid var(--border); border-radius: 9px;">
                        <div style="min-width: 0;">
                            <div class="flex items-center gap-2">
                                <span style="font-size: 13.5px; font-weight: 600;">{{ $u['title'] }}</span>
                                @if ($u['status'] === 'live')
                                    <span style="font-size: 10px; font-weight: 700; color: var(--ok); border: 1px solid var(--ok); border-radius: 999px; padding: 1px 7px;">OPEN NOW</span>
                                @else
                                    <span style="font-size: 10px; font-weight: 700; color: var(--accent); border: 1px solid var(--accent); border-radius: 999px; padding: 1px 7px;">SCHEDULED</span>
                                @endif
                            </div>
                            <div style="font-size: 11.5px; color: var(--text-faint); margin-top: 2px;">
                                {{ $u['subject'] }}@if (! empty($u['teacher'])) · {{ $u['teacher'] }}@endif
                                @if ($u['status'] === 'live' && $u['due']) · due {{ $u['due']->format('j M, g:i A') }}
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
        @endphp
        <div x-data="{ open: false }" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 20px 22px; margin-bottom: 16px;">

            {{-- Header (always visible) --}}
            <button type="button" @click="open = !open" class="flex items-center justify-between" style="width: 100%; background: none; border: 0; padding: 0; text-align: left; cursor: pointer; gap: 12px;">
                <div class="flex items-center gap-3" style="min-width: 0;">
                    <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                    @if (! empty($subject['teacher']))
                        <span style="font-size: 12px; color: var(--text-faint);"><x-icon name="user" size="11"/> {{ $subject['teacher'] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3" style="flex: none;">
                    <span class="badge badge-soft">{{ $subject['tests_count'] }} {{ Str::plural('test', $subject['tests_count']) }}</span>
                    <span style="font-size: 12px; color: var(--text-faint);">avg</span>
                    <span class="badge badge-emerald" style="font-size: 13px; font-weight: 700;">{{ $subject['avg'] }}%</span>
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
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">

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

                    {{-- Tests - scrollable once they exceed the topic-count reference --}}
                    <div>
                        <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                        <div class="space-y-2" @if ($scrollTests) style="max-height: {{ $testsMaxH }}px; overflow-y: auto; padding-right: 6px;" @endif>
                            @forelse ($subject['tests'] as $test)
                                <a href="{{ route('v2.student.exams.result', hid($test['exam_id'])) }}"
                                   class="flex items-center justify-between" style="text-decoration: none; color: inherit; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;">
                                    <div style="min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                        <div style="font-size: 11.5px; color: var(--text-faint);">
                                            {{ $test['date']?->diffForHumans() }}@unless ($test['results']) · result pending @endunless
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
@endunless
@endsection
