@php
    use App\Models\Paper;
    $papers = Paper::with('subject')->orderByDesc('updated_at')->limit(20)->get();
    if ($papers->isEmpty()) {
        $papers = collect([
            (object)['paper_code' => '9702/12', 'subject' => (object)['name' => 'Physics'],   'session' => 'May/June', 'year' => 2024, 'total_questions' => 40, 'updated_at' => now()->subDays(2), 'status' => 'Published'],
            (object)['paper_code' => '9702/22', 'subject' => (object)['name' => 'Physics'],   'session' => 'May/June', 'year' => 2024, 'total_questions' => 40, 'updated_at' => now()->subDays(5), 'status' => 'Published'],
            (object)['paper_code' => '9701/12', 'subject' => (object)['name' => 'Chemistry'], 'session' => 'Oct/Nov',  'year' => 2024, 'total_questions' => 40, 'updated_at' => now()->subDays(11), 'status' => 'Published'],
            (object)['paper_code' => '9709/31', 'subject' => (object)['name' => 'Maths'],     'session' => 'May/June', 'year' => 2024, 'total_questions' => 30, 'updated_at' => now()->subDays(20), 'status' => 'Reviewing'],
            (object)['paper_code' => '9700/12', 'subject' => (object)['name' => 'Biology'],   'session' => 'Oct/Nov',  'year' => 2024, 'total_questions' => 40, 'updated_at' => now()->subDays(30), 'status' => 'Published'],
            (object)['paper_code' => '9708/12', 'subject' => (object)['name' => 'Economics'], 'session' => 'May/June', 'year' => 2025, 'total_questions' => 30, 'updated_at' => now()->subWeek(), 'status' => 'Draft'],
        ]);
    }
@endphp
<x-layouts.dashboard role="admin" :breadcrumb="['Question Bank','Past Papers']" pageTitle="Past papers">
    <x-slot:actions>
        <button class="btn btn-primary btn-sm"><x-icon name="upload" size="13"/>Upload paper</button>
    </x-slot:actions>

    <div class="card-elev" style="padding: 0; overflow: hidden;">
        <div class="flex" style="padding: 14px; gap: 10px; border-bottom: 1px solid var(--border-soft);">
            <div style="flex: 1; position: relative;">
                <x-icon name="search" size="15" class="absolute" style="left: 12px; top: 11px; color: var(--text-faint);"/>
                <input class="input" placeholder="Search papers (e.g. 9702 May 2024)…" style="padding-left: 36px;"/>
            </div>
            <select class="select" style="width: 160px;"><option>All subjects</option></select>
            <select class="select" style="width: 160px;"><option>All sessions</option></select>
        </div>
        <table class="tbl">
            <thead><tr><th>Paper code</th><th>Subject</th><th>Session</th><th>Questions</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
                @foreach($papers as $p)
                    @php
                        $status = $p->status ?? 'Published';
                        $cls = $status === 'Published' ? 'badge-emerald' : ($status === 'Reviewing' ? 'badge-review' : 'badge-soft');
                    @endphp
                    <tr>
                        <td class="mono" style="font-weight: 600; color: var(--gold-700);">{{ $p->paper_code }}</td>
                        <td style="font-weight: 500;">{{ $p->subject?->name ?? '-' }}</td>
                        <td>{{ $p->session }} {{ $p->year }}</td>
                        <td>{{ $p->total_questions }}</td>
                        <td><span class="badge {{ $cls }}">{{ $status }}</span></td>
                        <td style="color: var(--text-faint);">{{ optional($p->updated_at)->diffForHumans() }}</td>
                        <td><button class="btn btn-ghost btn-sm">View questions</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.dashboard>
