@php
    $rows = [
        ['name' => 'Ayesha Khan',    'reg' => 'Y12-002', 'score' => 92,   'time' => '47m', 'flagged' => 0, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:22'],
        ['name' => 'Bilal Ahmed',    'reg' => 'Y12-007', 'score' => 84,   'time' => '53m', 'flagged' => 2, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:18'],
        ['name' => 'Hira Nawaz',     'reg' => 'Y12-014', 'score' => 78,   'time' => '59m', 'flagged' => 1, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:31'],
        ['name' => 'Ibrahim Sheikh', 'reg' => 'Y12-021', 'score' => 71,   'time' => '60m', 'flagged' => 3, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:35'],
        ['name' => 'Mariam Riaz',    'reg' => 'Y12-009', 'score' => 66,   'time' => '49m', 'flagged' => 0, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:24'],
        ['name' => 'Omar Tariq',     'reg' => 'Y12-018', 'score' => 62,   'time' => '55m', 'flagged' => 1, 'status' => 'Submitted',   'date' => 'Apr 28 · 14:29'],
        ['name' => 'Sana Iftikhar',  'reg' => 'Y12-003', 'score' => null, 'time' => '-',   'flagged' => 0, 'status' => 'Not started', 'date' => '-'],
        ['name' => 'Usman Akram',    'reg' => 'Y12-011', 'score' => null, 'time' => '32m', 'flagged' => 0, 'status' => 'In progress', 'date' => 'Now'],
    ];
    $worst = [
        ['Q11', 'Momentum', 5, 6],
        ['Q23', 'Thermal',  4, 6],
        ['Q07', 'Momentum', 4, 6],
        ['Q19', 'Atomic',   3, 6],
        ['Q31', 'Waves',    3, 6],
    ];
@endphp
<x-layouts.dashboard role="teacher" :breadcrumb="['Submissions','Mechanics - Mock Set A']" pageTitle="Mechanics - Mock Set A">
    <x-slot:actions>
        <div class="flex" style="gap: 10px;">
            <button class="btn btn-ghost btn-sm"><x-icon name="download" size="13"/>Export CSV</button>
            <button class="btn btn-primary btn-sm">Send results to students</button>
        </div>
    </x-slot:actions>

    <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Submitted"      value="6 / 8" delta="75% submission rate" icon="check"/>
        <x-stat-card label="Class average"  value="75.5%" delta="↑ 4.2 vs last test" icon="target" accent="gold"/>
        <x-stat-card label="Highest score"  value="92%"   delta="Ayesha Khan" icon="trophy" accent="gold"/>
        <x-stat-card label="Lowest"          value="62%"   delta="Omar Tariq · needs support" :positive="false" icon="flag"/>
        <x-stat-card label="Avg. time"      value="53m"   delta="of 60m allowed" icon="clock"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.6fr 1fr; gap: 16px;">
        <div class="card-elev" style="padding: 0; overflow: hidden;">
            <div class="flex justify-between items-center" style="padding: 16px 20px; border-bottom: 1px solid var(--border-soft);">
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">Student attempts</h3>
                <div class="flex" style="gap: 6px;">
                    <button class="chip chip-active" style="padding: 4px 10px; font-size: 11px;">All (8)</button>
                    <button class="chip" style="padding: 4px 10px; font-size: 11px;">Submitted (6)</button>
                    <button class="chip" style="padding: 4px 10px; font-size: 11px;">In progress (1)</button>
                </div>
            </div>
            <table class="tbl">
                <thead><tr><th>Student</th><th>Score</th><th>Time</th><th>Flagged</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                    @foreach($rows as $r)
                        @php
                            $col = $r['score'] !== null ? ($r['score'] > 75 ? 'var(--success)' : ($r['score'] > 60 ? 'var(--gold-500)' : 'var(--warning)')) : null;
                            $stCls = $r['status'] === 'Submitted' ? 'badge-emerald' : ($r['status'] === 'In progress' ? 'badge-gold' : 'badge-soft');
                            $initials = collect(explode(' ', $r['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center" style="gap: 10px;">
                                    <div class="flex items-center justify-center" style="width: 30px; height: 30px; border-radius: 50%; background: var(--emerald-50); color: var(--emerald-800); font-weight: 700; font-size: 11px;">{{ $initials }}</div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 13px;">{{ $r['name'] }}</div>
                                        <div class="mono" style="font-size: 10px; color: var(--text-faint);">{{ $r['reg'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($r['score'] !== null)
                                    <div class="flex items-center" style="gap: 8px;">
                                        <span style="font-weight: 600;">{{ $r['score'] }}%</span>
                                        <div style="width: 60px; height: 4px; background: var(--slate-100); border-radius: 2px; overflow: hidden;">
                                            <div style="width: {{ $r['score'] }}%; height: 100%; background: {{ $col }};"></div>
                                        </div>
                                    </div>
                                @else
                                    <span style="color: var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td class="mono" style="font-size: 12px;">{{ $r['time'] }}</td>
                            <td>
                                @if($r['flagged'] > 0)
                                    <span class="badge badge-review">{{ $r['flagged'] }}</span>
                                @else
                                    <span style="color: var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $stCls }}">{{ $r['status'] }}</span></td>
                            <td style="color: var(--text-faint); font-size: 12px;">{{ $r['date'] }}</td>
                            <td><button class="btn btn-ghost btn-sm">View</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-elev">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 14px;">Question difficulty (class)</h3>
            <p style="font-size: 12px; color: var(--text-soft); margin-bottom: 14px;">Most-missed questions - consider re-teaching.</p>
            @foreach($worst as [$q, $topic, $w, $of])
                <div class="flex items-center" style="gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-soft);">
                    <div class="mono" style="font-size: 11px; font-weight: 700; color: var(--gold-700); width: 32px;">{{ $q }}</div>
                    <div style="flex: 1;">
                        <div style="font-size: 12px; font-weight: 500;">{{ $topic }}</div>
                        <div style="height: 4px; background: var(--slate-100); border-radius: 2px; margin-top: 4px; overflow: hidden;">
                            <div style="width: {{ ($w / $of) * 100 }}%; height: 100%; background: var(--error);"></div>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: var(--error); font-weight: 600;">{{ $w }}/{{ $of }}</div>
                </div>
            @endforeach
            <a href="{{ route('teacher.test-generator') }}" wire:navigate class="btn btn-gold btn-sm" style="width: 100%; margin-top: 14px;"><x-icon name="sparkle" size="13"/>Generate remediation paper</a>
        </div>
    </div>
</x-layouts.dashboard>
