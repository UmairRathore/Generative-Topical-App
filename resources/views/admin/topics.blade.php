@php
    use App\Models\Topic;
    $topics = Topic::withCount('questions')->orderBy('name')->limit(8)->get();
    if ($topics->isEmpty()) {
        $topics = collect([
            (object)['id' => 1, 'name' => 'Mechanics',       'questions_count' => 487, 'slug' => 'mechanics'],
            (object)['id' => 2, 'name' => 'Waves',           'questions_count' => 264, 'slug' => 'waves'],
            (object)['id' => 3, 'name' => 'Electricity',     'questions_count' => 412, 'slug' => 'electricity'],
            (object)['id' => 4, 'name' => 'Thermal Physics', 'questions_count' => 198, 'slug' => 'thermal'],
            (object)['id' => 5, 'name' => 'Atomic Physics',  'questions_count' => 173, 'slug' => 'atom'],
            (object)['id' => 6, 'name' => 'Fields',          'questions_count' => 221, 'slug' => 'field'],
            (object)['id' => 7, 'name' => 'Oscillations',    'questions_count' => 156, 'slug' => 'osc'],
            (object)['id' => 8, 'name' => 'Quantum Physics', 'questions_count' => 142, 'slug' => 'quan'],
        ]);
    }
    $subtopics = [
        ["Newton's Laws", 86], ['Kinematics', 72], ['Dynamics', 64], ['Forces & Equilibrium', 58],
        ['Work, Energy, Power', 51], ['Momentum', 48], ['Circular Motion', 38], ['Gravitational Fields', 34],
        ['SHM', 29], ['Linear collisions', 24], ['2D motion', 18], ['Mass & Weight', 5],
    ];
@endphp
<x-layouts.dashboard role="admin" :breadcrumb="['Question Bank','Topics']" pageTitle="Topic management">
    <x-slot:actions>
        <button class="btn btn-primary btn-sm"><x-icon name="plus" size="13"/>New topic</button>
    </x-slot:actions>

    <div class="grid" style="grid-template-columns: 260px 1fr; gap: 16px;">
        <div class="card-elev" style="padding: 14px;">
            <select class="select" style="margin-bottom: 10px;">
                <option>Physics - A Level</option>
                <option>Chemistry - A Level</option>
            </select>
            <div class="flex flex-col" style="gap: 2px;">
                @foreach($topics as $i => $t)
                    <button class="flex justify-between items-center" style="padding: 10px 12px; border-radius: 6px; border: 0; text-align: left;
                            background: {{ $i === 0 ? 'var(--emerald-50)' : 'transparent' }};
                            color: {{ $i === 0 ? 'var(--emerald-800)' : 'var(--text)' }};
                            font-weight: {{ $i === 0 ? 600 : 500 }}; font-size: 13px;">
                        <span>{{ $t->name }}</span>
                        <span class="mono" style="font-size: 11px; color: var(--text-faint);">{{ $t->questions_count ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="card-elev" style="padding: 28px;">
            <div class="uppercase-eyebrow">Topic · Physics</div>
            <h2 class="serif" style="font-size: 26px; font-weight: 600; margin-top: 6px;">Mechanics</h2>
            <p style="color: var(--text-soft); margin-top: 6px; max-width: 600px;">
                Newton's Laws, kinematics, dynamics, equilibrium, work, energy and momentum.
            </p>

            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 24px;">
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Total questions</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">487</div>
                </div>
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Subtopics</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">12</div>
                </div>
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Avg. correct rate</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">68% (across 14k attempts)</div>
                </div>
            </div>

            <hr class="divider" style="margin: 24px 0;"/>
            <h3 class="serif" style="font-size: 17px; font-weight: 600; margin-bottom: 14px;">Subtopics</h3>
            <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: 8px;">
                @foreach($subtopics as [$n, $c])
                    <div class="flex justify-between items-center" style="padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                        <span>{{ $n }}</span>
                        <span class="mono" style="color: var(--text-faint); font-size: 11px;">{{ $c }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.dashboard>
