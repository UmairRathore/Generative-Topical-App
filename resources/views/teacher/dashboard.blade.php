@php
    $recentTests = [
        ['name' => 'Mechanics - Mock Set A',    'class' => 'Y12 Physics',   'subject' => 'Physics',     'q' => 40, 'attempts' => 28, 'avg' => 72, 'date' => 'Apr 28', 'status' => 'active'],
        ['name' => 'Organic Chemistry Topical', 'class' => 'Y13 Chemistry', 'subject' => 'Chemistry',   'q' => 25, 'attempts' => 19, 'avg' => 81, 'date' => 'Apr 26', 'status' => 'completed'],
        ['name' => 'Waves & Oscillations',      'class' => 'Y12 Physics',   'subject' => 'Physics',     'q' => 30, 'attempts' => 28, 'avg' => 68, 'date' => 'Apr 22', 'status' => 'completed'],
        ['name' => 'Algebra Foundation',        'class' => 'Y11 Math',      'subject' => 'Mathematics', 'q' => 40, 'attempts' => 32, 'avg' => 75, 'date' => 'Apr 19', 'status' => 'completed'],
        ['name' => 'Electricity - Pre-Mock',    'class' => 'Y13 Physics',   'subject' => 'Physics',     'q' => 40, 'attempts' => 24, 'avg' => 79, 'date' => 'Apr 15', 'status' => 'completed'],
    ];
    $mastery = [
        ['Mechanics',       88, 'Strong'],
        ['Electricity',     79, 'Good'],
        ['Waves',           72, 'Good'],
        ['Thermal Physics', 64, 'Watch'],
        ['Atomic Physics',  52, 'Focus'],
        ['Momentum',        41, 'Weak'],
    ];
@endphp
<x-layouts.dashboard role="teacher" :breadcrumb="['Dashboard']" pageTitle="Good morning, {{ auth()->user()->name ?? 'Dr. Iqbal' }}.">
    <x-slot:actions>
        <div class="flex" style="gap: 10px;">
            <a href="{{ route('teacher.question-picker') }}" wire:navigate class="btn btn-ghost btn-sm"><x-icon name="filter" size="14"/>Build test</a>
            <a href="{{ route('teacher.test-generator') }}" wire:navigate class="btn btn-primary btn-sm"><x-icon name="sparkle" size="14"/>Generate paper</a>
        </div>
    </x-slot:actions>

    {{-- Welcome card --}}
    <div class="relative overflow-hidden" style="background: linear-gradient(120deg, var(--emerald-900), var(--emerald-800) 70%); color: var(--ivory); border-radius: 14px; padding: 28px; margin-bottom: 24px;">
        <div class="grain absolute" style="inset: 0;"></div>
        <div class="absolute" style="right: -40px; bottom: -40px; opacity: 0.08;"><x-crest size="240" variant="mono-light"/></div>
        <div class="relative">
            <div class="uppercase-eyebrow" style="color: var(--accent);">Y12 Physics · Term 2 · Week 9</div>
            <h2 class="serif" style="font-size: 28px; font-weight: 600; margin-top: 8px; letter-spacing: -0.01em; max-width: 580px; line-height: 1.2;">
                12 students completed <em style="color: var(--accent);">Mechanics - Mock Set A.</em> Average 72%, weakest topic: Momentum.
            </h2>
            <div class="flex" style="gap: 12px; margin-top: 20px;">
                <a href="{{ route('teacher.submissions') }}" wire:navigate class="btn btn-gold btn-sm">Review submissions</a>
                <a href="{{ route('teacher.test-generator') }}" wire:navigate class="btn btn-outline-light btn-sm">Generate Momentum focus paper</a>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Tests Generated"  value="28"   delta="+4 this week" icon="clipboard"/>
        <x-stat-card label="Students"          value="84"   delta="3 classes"   icon="users"/>
        <x-stat-card label="Submitted"         value="312"  delta="+47 this week" icon="check"/>
        <x-stat-card label="Avg. Class Score"  value="74.8%" delta="↑ 3.2%"   icon="target" accent="gold"/>
        <x-stat-card label="Weak Topics"       value="3"    delta="Action needed" :positive="false" icon="flag"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.7fr 1fr; gap: 16px;">
        {{-- Recent tests --}}
        <div class="card-elev" style="padding: 0; overflow: hidden;">
            <div class="flex justify-between items-center" style="padding: 20px;">
                <div>
                    <h3 class="serif" style="font-size: 18px; font-weight: 600;">Recent tests</h3>
                    <div style="font-size: 12px; color: var(--text-faint);">Latest assignments and submissions</div>
                </div>
                <a href="{{ route('teacher.submissions') }}" wire:navigate class="btn btn-ghost btn-sm">All submissions <x-icon name="chev-r" size="12"/></a>
            </div>
            <table class="tbl">
                <thead><tr><th>Test</th><th>Class</th><th>Q</th><th>Submitted</th><th>Avg</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    @foreach($recentTests as $t)
                        @php $col = $t['avg'] > 75 ? 'var(--success)' : ($t['avg'] > 60 ? 'var(--gold-500)' : 'var(--warning)'); @endphp
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;">{{ $t['name'] }}</div>
                                <div style="font-size: 11px; color: var(--text-faint);">{{ $t['subject'] }} · {{ $t['status'] }}</div>
                            </td>
                            <td>{{ $t['class'] }}</td>
                            <td class="mono">{{ $t['q'] }}</td>
                            <td>{{ $t['attempts'] }}/{{ $t['attempts'] + ($t['status'] === 'active' ? 4 : 0) }}</td>
                            <td>
                                <div class="flex items-center" style="gap: 6px;">
                                    <span style="font-weight: 600;">{{ $t['avg'] }}%</span>
                                    <div style="width: 50px; height: 4px; background: var(--slate-100); border-radius: 2px; overflow: hidden;">
                                        <div style="width: {{ $t['avg'] }}%; height: 100%; background: {{ $col }};"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color: var(--text-faint);">{{ $t['date'] }}</td>
                            <td style="text-align: right;"><button class="btn btn-ghost btn-sm">View</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Topic mastery --}}
        <div class="card-elev">
            <div class="flex justify-between items-center" style="margin-bottom: 16px;">
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">Topic mastery</h3>
                <span style="font-size: 11px; color: var(--text-faint);">Y12 Physics · 28 students</span>
            </div>
            @foreach($mastery as [$t, $p, $n])
                @php $c = $p >= 75 ? 'var(--success)' : ($p >= 60 ? 'var(--gold-500)' : 'var(--error)'); @endphp
                <div style="margin-bottom: 14px;">
                    <div class="flex justify-between" style="margin-bottom: 5px; font-size: 13px;">
                        <span style="font-weight: 500;">{{ $t }}</span>
                        <span class="flex" style="gap: 8px;">
                            <span style="color: var(--text-faint); font-size: 11px;">{{ $n }}</span>
                            <span style="font-weight: 600; color: {{ $c }};">{{ $p }}%</span>
                        </span>
                    </div>
                    <div style="height: 6px; background: var(--slate-100); border-radius: 3px; overflow: hidden;">
                        <div style="width: {{ $p }}%; height: 100%; background: {{ $c }};"></div>
                    </div>
                </div>
            @endforeach
            <a href="{{ route('admin.analytics') }}" wire:navigate class="btn btn-ghost btn-sm" style="width: 100%; margin-top: 8px;">Detailed analytics <x-icon name="chev-r" size="12"/></a>
        </div>
    </div>
</x-layouts.dashboard>
