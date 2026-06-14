@extends('v2.layouts.school_admin')
@section('page_title', 'Bulk Add Students')

@section('content')
<div style="max-width: 700px;">
    <a href="{{ route('v2.school.students.index') }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px;">
        <x-icon name="arrow-left" size="14" /> Back to Students
    </a>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px;">
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 24px;">Add up to 100 students at once. A unique temporary password is generated for each.</p>

        <form method="POST" action="{{ route('v2.school.students.bulk_store') }}" id="bulkForm" class="space-y-4">
            @csrf

            <div id="studentRows" class="space-y-3">
                <div class="student-row grid gap-3" style="grid-template-columns: 2fr 2fr 1fr;">
                    <input type="text"  name="students[0][name]"        placeholder="Full name"         required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
                    <input type="email" name="students[0][email]"       placeholder="Email address"     required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
                    <input type="text"  name="students[0][roll_number]" placeholder="Roll no."                   style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
                </div>
            </div>

            <button type="button" id="addRow"
                    style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer;">
                <x-icon name="plus" size="14" /> Add Row
            </button>

            <button type="submit"
                    style="display: block; width: 100%; padding: 11px; border-radius: 8px; background: var(--emerald-700); color: #fff; font-size: 14px; font-weight: 600; border: none; cursor: pointer; margin-top: 8px;">
                Create Students
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('addRow').addEventListener('click', () => {
    const rows = document.querySelectorAll('.student-row');
    const idx  = rows.length;
    const div  = document.createElement('div');
    div.className = 'student-row grid gap-3';
    div.style.gridTemplateColumns = '2fr 2fr 1fr';
    div.innerHTML = `
        <input type="text"  name="students[${idx}][name]"        placeholder="Full name"     required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
        <input type="email" name="students[${idx}][email]"       placeholder="Email address" required style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
        <input type="text"  name="students[${idx}][roll_number]" placeholder="Roll no."               style="padding:9px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg);font-size:13px;color:var(--text);">
    `;
    document.getElementById('studentRows').appendChild(div);
});
</script>
@endsection
