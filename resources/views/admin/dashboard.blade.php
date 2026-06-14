@php
    $reviewQueue = [
        ['id' => 'Q-9702-12-2025-04-NEW', 'subj' => 'Physics · Mechanics',  'by' => 'Dr. S. Iqbal',    'st' => 'pending',   'd' => '2h ago'],
        ['id' => 'Q-9701-22-2025-04-NEW', 'subj' => 'Chemistry · Organic',  'by' => 'Ms. F. Aziz',     'st' => 'pending',   'd' => '4h ago'],
        ['id' => 'Q-9709-31-2024-08-REV', 'subj' => 'Maths · Calculus',     'by' => 'Mr. A. Mahmood',  'st' => 'needs-fix', 'd' => '1d ago'],
        ['id' => 'Q-9700-12-2024-04-NEW', 'subj' => 'Biology · Genetics',   'by' => 'Dr. N. Hassan',   'st' => 'pending',   'd' => '1d ago'],
    ];
    $statusMap = [
        'pending'   => ['Pending',   'badge-review'],
        'needs-fix' => ['Needs fix', 'badge-blocker'],
        'pass'      => ['Pass',      'badge-pass'],
        'approved'  => ['Approved',  'badge-pass'],
    ];
    $topSchools = [
        ['Lahore Grammar School (DHA)',    412, 28],
        ['Aitchison College',               387, 24],
        ['Karachi Grammar School',          354, 22],
        ['Beaconhouse Margalla Campus',     298, 19],
        ['Roots Millennium Lahore',         271, 16],
    ];
@endphp
<x-layouts.dashboard role="admin" :breadcrumb="['Admin','Operations']" pageTitle="Operations console">
    <x-slot:actions>
        <a href="{{ route('admin.questions') }}" wire:navigate class="btn btn-gold btn-sm"><x-icon name="check" size="13"/>Review queue (47)</a>
    </x-slot:actions>

    <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <x-stat-card label="Total Schools"    value="124"   delta="+8 this month"     icon="users"/>
        <x-stat-card label="Active Teachers"  value="1,847" delta="+92 this month"    icon="edit"/>
        <x-stat-card label="Active Students"  value="42,318" delta="+1,204 this month" icon="user"/>
        <x-stat-card label="Question Bank"    value="50,284" delta="+312 in review"    icon="clipboard" accent="gold"/>
        <x-stat-card label="MRR"              value="₨ 8.4M" delta="↑ 14% MoM"        icon="trending"  accent="gold"/>
    </div>

    <div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px;">
        <div class="card-elev">
            <div class="flex justify-between items-center" style="margin-bottom: 16px;">
                <h3 class="serif" style="font-size: 18px; font-weight: 600;">Question review queue</h3>
                <a href="{{ route('admin.questions') }}" wire:navigate class="btn btn-ghost btn-sm">Open queue<x-icon name="chev-r" size="12"/></a>
            </div>
            <table class="tbl">
                <thead><tr><th>ID</th><th>Subject · Topic</th><th>Submitted by</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @foreach($reviewQueue as $r)
                        @php [$lbl, $cls] = $statusMap[$r['st']] ?? [$r['st'], 'badge-soft']; @endphp
                        <tr>
                            <td class="mono" style="font-size: 11px;">{{ $r['id'] }}</td>
                            <td>{{ $r['subj'] }}</td>
                            <td>{{ $r['by'] }}</td>
                            <td><span class="badge {{ $cls }}">{{ $lbl }}</span></td>
                            <td style="color: var(--text-faint);">{{ $r['d'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-elev">
            <h3 class="serif" style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Top schools (this month)</h3>
            @foreach($topSchools as $i => [$name, $students, $teachers])
                <div class="flex items-center" style="gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-soft);">
                    <div class="flex items-center justify-center" style="width: 28px; height: 28px; border-radius: 50%; background: var(--gold-50); color: var(--gold-700); font-weight: 700; font-size: 12px;">{{ $i + 1 }}</div>
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 500;">{{ $name }}</div>
                        <div style="font-size: 11px; color: var(--text-faint);">{{ $teachers }} teachers · {{ $students }} active students</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
