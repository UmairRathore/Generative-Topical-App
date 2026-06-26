@extends('v2.layouts.branch_admin')
@section('page_title', 'Student Analytics')

@php $tone = fn ($p) => $p >= 60 ? 'var(--ok)' : ($p >= 40 ? 'var(--warn)' : 'var(--bad)'); @endphp

@section('content')
<a href="{{ route('v2.branch.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-soft); text-decoration: none; margin-bottom: 16px;">
    <x-icon name="chev-l" size="14"/> Back to Analytics
</a>

<div class="flex items-start justify-between" style="margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">{{ $student->name }}</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">Roll {{ $student->roll_number }}@if ($student->email) · {{ $student->email }}@endif</p>
    </div>
    {{-- Opens the report modal, which is rendered in the @modals stack (outside the
         animated .fade-in wrapper) so its position:fixed centers on the viewport. --}}
    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-report-modal'))" class="btn btn-primary btn-sm"><x-icon name="sparkle" size="13"/> Generate report</button>
</div>

@push('modals')
    <style>
        [x-cloak]{display:none!important}
        .rp-overlay{position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;padding:16px;}
        .rp-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);}
        .rp-card{position:relative;width:100%;max-width:540px;max-height:92vh;overflow-y:auto;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:0 24px 64px rgba(0,0,0,.35);}
        .rp-chip{padding:10px 8px;border:1px solid var(--border);border-radius:9px;background:var(--surface);font-size:12.5px;font-weight:600;color:var(--text-soft);cursor:pointer;text-align:center;transition:all .12s;user-select:none;}
        .rp-chip:hover{border-color:var(--text-faint);}
        .rp-chip.is-on{background:var(--primary);color:#fff;border-color:var(--primary);}
        .rp-loader{position:absolute;inset:0;z-index:3;background:var(--surface);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;text-align:center;padding:24px;}
        @keyframes rp-pulse{0%,100%{opacity:.35;transform:scale(.9)}50%{opacity:1;transform:scale(1.1)}}
    </style>
    <div x-data="{ open: false, mode: 'preset', sel: 'month', from: '', to: '', loading: false, canSubmit() { return this.mode === 'preset' ? true : (this.from && this.to); } }"
         @open-report-modal.window="open = true; mode = 'preset'; sel = 'month'; from = ''; to = ''; loading = false"
         x-show="open" x-cloak @keydown.escape.window="open = false" class="rp-overlay">
        <div @click="if (!loading) open = false" class="rp-backdrop"></div>
        <div x-show="open" x-transition class="rp-card">

            {{-- AI loader overlay (shown while the POST + OpenAI call runs) --}}
            <div x-show="loading" x-cloak class="rp-loader">
                <div style="display: inline-flex; gap: 7px;">
                    @foreach ([0, 1, 2] as $d)
                        <x-icon name="sparkle" size="20" style="color: var(--primary); animation: rp-pulse 1.1s ease-in-out {{ $d * 0.18 }}s infinite;"/>
                    @endforeach
                </div>
                <div class="serif" style="font-size: 17px; font-weight: 600;">Generating with AI…</div>
                <div style="font-size: 12.5px; color: var(--text-soft); max-width: 320px;">Reading {{ $student->name }}'s results and writing the report - this can take a few seconds.</div>
            </div>

            <div class="flex items-center justify-between" style="padding: 15px 20px; border-bottom: 1px solid var(--border);">
                <span class="flex items-center gap-2 serif" style="font-size: 17px; font-weight: 600;"><x-icon name="sparkle" size="16"/> Generate progress report</span>
                <button type="button" @click="open = false" class="btn btn-ghost btn-sm" aria-label="Close"><x-icon name="x" size="15"/></button>
            </div>

            <form method="POST" action="{{ route('v2.branch.report.generate', $student) }}" @submit="loading = true">
                @csrf
                <input type="hidden" name="duration" :value="mode === 'custom' ? 'custom' : sel">

                <div style="padding: 18px 20px;">
                    <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 10px;">Period for {{ $student->name }}</div>

                    {{-- 3 x 3 selectable preset chips --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        @foreach ([
                            'week' => 'Last week', '2weeks' => 'Last 2 weeks', '3weeks' => 'Last 3 weeks',
                            'month' => 'Last month', '2months' => 'Last 2 months', '3months' => 'Last 3 months',
                            'quarter' => 'Last quarter', '6months' => 'Last 6 months', '10months' => 'Last 10 months',
                        ] as $val => $lbl)
                            <div class="rp-chip" :class="(mode === 'preset' && sel === '{{ $val }}') ? 'is-on' : ''"
                                 @click="mode = 'preset'; sel = '{{ $val }}'">{{ $lbl }}</div>
                        @endforeach
                    </div>

                    {{-- custom range (selecting it deselects the presets) --}}
                    <div style="margin-top: 14px; border: 1.5px solid; border-radius: 10px; padding: 12px 14px; transition: all .12s; cursor: pointer;"
                         :style="mode === 'custom' ? 'border-color: var(--primary); background: var(--soft-surface);' : 'border-color: var(--border);'"
                         @click="mode = 'custom'">
                        <div class="flex items-center gap-2" style="font-size: 12px; font-weight: 700; color: var(--text-soft); margin-bottom: 9px;">
                            <span style="width: 13px; height: 13px; border-radius: 50%; border: 2px solid; flex: none;"
                                  :style="mode === 'custom' ? 'border-color: var(--primary); background: var(--primary); box-shadow: inset 0 0 0 2px var(--surface);' : 'border-color: var(--border);'"></span>
                            Or choose a custom date range
                        </div>
                        <div class="flex items-end gap-2" style="flex-wrap: wrap;">
                            <label style="flex: 1; min-width: 120px;">
                                <span style="display: block; font-size: 10.5px; color: var(--text-soft); margin-bottom: 3px;">From</span>
                                <input type="date" name="from" x-model="from" @focus="mode = 'custom'" max="{{ now()->format('Y-m-d') }}" @click.stop style="width: 100%; padding: 6px 9px; font-size: 13px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); color: var(--text);">
                            </label>
                            <span style="padding-bottom: 7px; color: var(--text-faint);">–</span>
                            <label style="flex: 1; min-width: 120px;">
                                <span style="display: block; font-size: 10.5px; color: var(--text-soft); margin-bottom: 3px;">To</span>
                                <input type="date" name="to" x-model="to" @focus="mode = 'custom'" :min="from" max="{{ now()->format('Y-m-d') }}" @click.stop style="width: 100%; padding: 6px 9px; font-size: 13px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); color: var(--text);">
                            </label>
                        </div>
                    </div>
                </div>

                {{-- centered Generate button --}}
                <div style="padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: center;">
                    <button type="submit" class="btn btn-primary" :disabled="!canSubmit() || loading"
                            :style="(!canSubmit() || loading) ? 'opacity:.55; cursor:not-allowed;' : ''"
                            style="min-width: 240px; justify-content: center;">
                        <x-icon name="sparkle" size="14"/> Generate report
                    </button>
                </div>
            </form>
        </div>
    </div>
@endpush

{{-- Saved reports (PDF rendered from the stored JSON) --}}
@if ($reports->isNotEmpty())
    <style>@keyframes rp-flash{0%{background:var(--ok-soft)}70%{background:var(--ok-soft)}100%{background:transparent}}</style>
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 24px;">
        <div style="padding: 12px 18px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;">Progress reports</div>
        <table class="tbl" style="width: 100%; border-collapse: collapse;">
            <tbody>
                @foreach ($reports as $r)
                    @php $justMade = (string) session('report_ready') === (string) $r->id; @endphp
                    <tr style="border-bottom: 1px solid var(--border); {{ $justMade ? 'animation: rp-flash 4s ease;' : '' }}">
                        <td data-label="Period" style="padding: var(--pad-cell); font-size: 13px; font-weight: 500;">
                            {{ ucfirst($r->period_label) }}
                            @if ($justMade)<span class="badge badge-emerald" style="font-size: 10px; margin-left: 6px;">Just generated</span>@endif
                        </td>
                        <td data-label="Score" style="padding: var(--pad-cell); font-size: 13px;">{{ $r->overall_avg !== null ? $r->overall_avg.'%' : '-' }} · {{ $r->tests_count }} {{ \Illuminate\Support\Str::plural('test', $r->tests_count) }}</td>
                        <td data-label="Source" style="padding: var(--pad-cell); font-size: 12px; color: var(--text-soft);">{{ $r->source === 'openai' ? 'AI Assisted' : 'Generated' }}</td>
                        <td data-label="Generated" style="padding: var(--pad-cell); font-size: 12px; color: var(--text-faint);">{{ $r->created_at->diffForHumans() }}</td>
                        <td data-label="" style="padding: var(--pad-cell); text-align: right;">
                            <a href="{{ route('v2.branch.report.pdf', $r) }}" class="btn btn-ghost btn-sm"><x-icon name="download" size="12"/> PDF</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (session('report_ready'))
        <script>window.addEventListener('DOMContentLoaded', () => document.querySelector('tr[style*="rp-flash"]')?.scrollIntoView({behavior:'smooth', block:'center'}));</script>
    @endif
@endif

@if ($stats['overall']['tests'] === 0)
    <div style="padding: 40px; text-align: center; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); color: var(--text-soft); font-size: 14px;">
        This student hasn't completed any tests yet.
    </div>
@else
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px;">
        @foreach ([
            ['Tests completed', $stats['overall']['tests'], 'clipboard'],
            ['Overall average', $stats['overall']['avg'].'%', 'chart'],
            ['Subjects', count($stats['subjects']), 'book'],
        ] as [$label, $value, $icon])
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
                <div class="flex items-center gap-2" style="color: var(--text-faint); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em;">
                    <x-icon :name="$icon" size="13"/> {{ $label }}
                </div>
                <div class="serif" style="font-size: 24px; font-weight: 600; margin-top: 6px;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($stats['subjects'] as $subject)
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 22px; margin-bottom: 18px;">
            <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                <h3 class="serif" style="font-size: 19px; font-weight: 600;">{{ $subject['subject'] }}</h3>
                <span class="badge badge-emerald" style="font-size: 13px; font-weight: 700;">{{ $subject['avg'] }}%</span>
            </div>
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">By topic</div>
                    @include('v2.partials.topic_bars', ['stats' => $subject['topics'], 'empty' => 'No topic data.'])
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-faint); margin-bottom: 12px;">Tests</div>
                    <div class="space-y-2">
                        @foreach ($subject['tests'] as $test)
                            <a href="{{ route('v2.branch.student_paper', [hid($test['exam_id']), $student]) }}" class="flex items-center justify-between" style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; text-decoration: none; color: inherit;">
                                <div style="min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 500;">{{ $test['title'] }}</div>
                                    <div style="font-size: 11.5px; color: var(--text-faint);">{{ $test['topic'] }} · {{ $test['date']?->diffForHumans() }}</div>
                                </div>
                                <div style="text-align: right; flex: none;">
                                    <span style="font-weight: 700; font-size: 14px; color: {{ $tone($test['percent']) }};">{{ $test['percent'] }}%</span>
                                    <div style="font-size: 11px; color: var(--text-faint);">{{ $test['score'] }}/{{ $test['total'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
