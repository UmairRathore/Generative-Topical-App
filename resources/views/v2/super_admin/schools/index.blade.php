@extends('v2.layouts.super_admin')
@section('page_title', 'Schools')

@section('content')
<div class="flex items-center justify-between" style="margin-bottom: 24px;">
    <div>
        <h2 class="serif" style="font-size: 26px; font-weight: 600;">Schools</h2>
        <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">All registered schools on the platform.</p>
    </div>
    <a href="{{ route('v2.super_admin.schools.create') }}" class="btn btn-primary">
        <x-icon name="plus" size="14"/> Add School
    </a>
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">School</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Contact</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Status</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Tier</th>
                <th style="padding: var(--pad-cell); text-align: right; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
                    No schools yet. <a href="{{ route('v2.super_admin.schools.create') }}" style="color: var(--gold-700); font-weight: 600; text-decoration: none;">Add the first school →</a>
                </td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
