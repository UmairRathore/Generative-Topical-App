@php
    use App\Enums\UserRole;
    use App\Models\User;
    $users = User::orderByDesc('created_at')->limit(20)->get();
    if ($users->isEmpty()) {
        $users = collect([
            (object)['name' => 'Dr. Saima Iqbal', 'email' => 's.iqbal@isl.edu.pk',    'role' => UserRole::Teacher, 'school' => 'ISL Lahore',           'last' => '2h ago',  'status' => 'Active'],
            (object)['name' => 'Ms. Fariha Aziz', 'email' => 'f.aziz@kgs.edu.pk',     'role' => UserRole::Teacher, 'school' => 'KGS Karachi',          'last' => '1d ago',  'status' => 'Active'],
            (object)['name' => 'Mr. A. Mahmood',  'email' => 'a.mahmood@lgs.edu.pk',  'role' => UserRole::Teacher, 'school' => 'LGS DHA',              'last' => '4d ago',  'status' => 'Active'],
            (object)['name' => 'Aisha Rehman',    'email' => 'aisha@gt.pk',           'role' => UserRole::Admin,   'school' => 'GT HQ',                'last' => 'now',     'status' => 'Active'],
            (object)['name' => 'Ayesha Khan',     'email' => 'ayesha.f@isl.edu.pk',   'role' => UserRole::Student, 'school' => 'ISL Lahore · Y12',     'last' => 'now',     'status' => 'Active'],
            (object)['name' => 'Bilal Ahmed',     'email' => 'bilal.a@isl.edu.pk',    'role' => UserRole::Student, 'school' => 'ISL Lahore · Y12',     'last' => '3h ago',  'status' => 'Active'],
            (object)['name' => 'Faisal Khan',     'email' => 'faisal.k@aitchison.pk', 'role' => UserRole::Student, 'school' => 'Aitchison',            'last' => '1w ago',  'status' => 'Inactive'],
        ]);
    }

    $totalCount = User::count();
    $studentCount = User::where('role', UserRole::Student)->count();
    $teacherCount = User::where('role', UserRole::Teacher)->count();
    $adminCount = User::where('role', UserRole::Admin)->count();
@endphp
<x-layouts.dashboard role="admin" :breadcrumb="['Platform','Users']" pageTitle="User management">
    <x-slot:actions>
        <button class="btn btn-primary btn-sm"><x-icon name="plus" size="13"/>Invite user</button>
    </x-slot:actions>

    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 18px;">
        <x-stat-card label="All users" :value="number_format($totalCount)" icon="users"/>
        <x-stat-card label="Students"  :value="number_format($studentCount)" delta="+1,204 MoM" icon="user"/>
        <x-stat-card label="Teachers"  :value="number_format($teacherCount)" delta="+92 MoM" icon="edit" accent="gold"/>
        <x-stat-card label="Admins"    :value="number_format($adminCount)"   icon="shield"/>
    </div>

    <div class="card-elev" style="padding: 0; overflow: hidden;">
        <div class="flex" style="padding: 14px; gap: 10px; border-bottom: 1px solid var(--border-soft);">
            <div style="flex: 1; position: relative;">
                <x-icon name="search" size="15" class="absolute" style="left: 12px; top: 11px; color: var(--text-faint);"/>
                <input class="input" placeholder="Search users by name, email, or school…" style="padding-left: 36px;"/>
            </div>
            <select class="select" style="width: 140px;"><option>All roles</option><option>Student</option><option>Teacher</option><option>Admin</option></select>
            <select class="select" style="width: 160px;"><option>All schools</option></select>
        </div>
        <table class="tbl">
            <thead><tr><th>User</th><th>Role</th><th>School</th><th>Last active</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($users as $u)
                    @php
                        $roleStr = $u->role instanceof UserRole ? $u->role->value : (string) $u->role;
                        $roleLabel = match($roleStr) { 'admin' => 'Admin', 'teacher' => 'Teacher', default => 'Student' };
                        $cls = $roleStr === 'admin' ? 'badge-gold' : ($roleStr === 'teacher' ? 'badge-emerald' : 'badge-soft');
                        $bg = $roleStr === 'admin' ? 'var(--gold-50)' : ($roleStr === 'teacher' ? 'var(--emerald-50)' : 'var(--slate-100)');
                        $color = $roleStr === 'admin' ? 'var(--gold-700)' : ($roleStr === 'teacher' ? 'var(--emerald-800)' : 'var(--slate-600)');
                        $initials = collect(explode(' ', $u->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                        $isActive = ($u->status ?? 'Active') === 'Active';
                        $school = $u->school ?? '-';
                        $last = $u->last ?? optional($u->created_at)->diffForHumans() ?? '-';
                    @endphp
                    <tr>
                        <td>
                            <div class="flex items-center" style="gap: 10px;">
                                <div class="flex items-center justify-center" style="width: 30px; height: 30px; border-radius: 50%; background: {{ $bg }}; color: {{ $color }}; font-weight: 700; font-size: 11px;">{{ $initials }}</div>
                                <div>
                                    <div style="font-weight: 600; font-size: 13px;">{{ $u->name }}</div>
                                    <div style="font-size: 11px; color: var(--text-faint);">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge {{ $cls }}">{{ $roleLabel }}</span></td>
                        <td>{{ $school }}</td>
                        <td style="color: var(--text-faint);">{{ $last }}</td>
                        <td>
                            <span class="dot" style="background: {{ $isActive ? 'var(--success)' : 'var(--slate-400)' }}; margin-right: 6px;"></span>
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </td>
                        <td><button class="btn btn-ghost btn-sm">Manage</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.dashboard>
