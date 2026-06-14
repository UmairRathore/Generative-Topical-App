@extends('v2.layouts.super_admin')
@section('page_title', 'Audit Log')

@section('content')
<div style="margin-bottom: 24px;">
    <h2 class="serif" style="font-size: 26px; font-weight: 600;">Audit Log</h2>
    <p style="color: var(--text-soft); font-size: 13px; margin-top: 2px;">All platform actions logged here.</p>
</div>

<div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); background: var(--soft-surface);">
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Actor</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Action</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">Target</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">IP</th>
                <th style="padding: var(--pad-cell); text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-faint);">When</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5" style="padding: 48px; text-align: center; color: var(--text-faint); font-size: 14px;">
                    No audit entries yet.
                </td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
