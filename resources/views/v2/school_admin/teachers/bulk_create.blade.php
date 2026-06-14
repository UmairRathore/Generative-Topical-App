@extends('v2.layouts.school_admin')
@section('page_title', 'Bulk Add Teachers')

@section('content')
<div style="max-width: 700px;">
    <a href="{{ route('v2.school.teachers.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back to Teachers
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 24px;">Add up to 50 teachers at once. A unique temporary password is generated for each.</p>

        <form method="POST" action="{{ route('v2.school.teachers.bulk_store') }}" id="bulkForm" class="space-y-4">
            @csrf

            <div id="teacherRows" class="space-y-3">
                <div class="teacher-row grid grid-cols-3 gap-3">
                    <input type="text" name="teachers[0][name]" placeholder="Full name" required
                           style="padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);">
                    <input type="email" name="teachers[0][email]" placeholder="Email address" required
                           style="padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);">
                    <input type="text" name="teachers[0][employee_id]" placeholder="Employee ID (optional)"
                           style="padding: 9px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); font-size: 13px; color: var(--text);">
                </div>
            </div>

            <button type="button" id="addRow"
                    style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer;">
                <x-icon name="plus" size="14" /> Add Row
            </button>

            <button type="submit"
                    style="display: block; width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer; margin-top: 8px;">
                Create Teachers
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('addRow').addEventListener('click', () => {
    const rows = document.querySelectorAll('.teacher-row');
    const idx  = rows.length;
    const div  = document.createElement('div');
    div.className = 'teacher-row grid grid-cols-3 gap-3';
    div.innerHTML = `
        <input type="text"  name="teachers[${idx}][name]"        placeholder="Full name"             required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
        <input type="email" name="teachers[${idx}][email]"       placeholder="Email address"         required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
        <input type="text"  name="teachers[${idx}][employee_id]" placeholder="Employee ID (optional)"         style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
    `;
    document.getElementById('teacherRows').appendChild(div);
});
</script>
@endsection
