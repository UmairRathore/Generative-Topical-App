@extends('v2.layouts.super_admin')
@section('page_title', 'Version history')

@section('content')
<div style="max-width: 920px;">
    <a href="{{ route('v2.super_admin.question_bank.edit', $question) }}" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-soft);text-decoration:none;margin-bottom:14px;">
        <x-icon name="chev-l" size="14"/> Back to editor
    </a>

    <div style="margin-bottom: 18px;">
        <div style="font-size: 11px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);">Question Management</div>
        <h2 class="serif" style="font-size: 24px; font-weight: 600; margin-top: 4px;">Version history</h2>
        <p style="color: var(--text-soft); margin-top: 2px; font-size: 13.5px;">
            {{ $question->source_paper }} · Q{{ $question->question_number }} · every saved version is immutable; historical exams render the version they froze.
        </p>
    </div>

    @foreach ($rows as $row)
        @php $v = $row['version']; @endphp
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 18px 20px; margin-bottom: 16px; {{ $row['is_current'] ? 'border-left: 3px solid var(--ok);' : '' }}">
            <div class="flex items-center justify-between" style="gap: 12px; flex-wrap: wrap; margin-bottom: 12px;">
                <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                    <span class="badge badge-emerald" style="font-weight: 700;">v{{ $v->version_number }}</span>
                    @if ($row['is_current'])<span class="badge badge-pass">Current</span>@endif
                    @if ($v->quality_review_id)<span class="badge badge-review">Quality Review #{{ $v->quality_review_id }}</span>@endif
                    <span style="font-size: 12.5px; color: var(--text-soft);">{{ $v->change_summary }}</span>
                </div>
                <span style="font-size: 11.5px; color: var(--text-faint);">{{ $v->created_at?->format('j M Y, g:i A') }}</span>
            </div>

            @if (! empty($row['diff']))
                <div style="font-size: 12px; background: var(--soft-surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                    <div style="font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: .06em; font-size: 10.5px; margin-bottom: 6px;">Changes vs current</div>
                    @foreach ($row['diff'] as $d)
                        <div style="margin-bottom: 4px;">
                            <span style="font-weight: 600;">{{ $d['field'] }}:</span>
                            <span style="color: var(--bad); text-decoration: line-through;">{{ \Illuminate\Support\Str::limit((string) $d['old'], 120) ?: '-' }}</span>
                            <span style="color: var(--text-faint);">→</span>
                            <span style="color: var(--ok);">{{ \Illuminate\Support\Str::limit((string) $d['new'], 120) ?: '-' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <details>
                <summary style="cursor: pointer; font-size: 12.5px; color: var(--accent);">Preview this version</summary>
                <div style="margin-top: 12px; border: 1px solid var(--border); border-radius: 10px; padding: 16px;">
                    @include('v2.partials.question_card', ['q' => $row['rendered']])
                </div>
            </details>
        </div>
    @endforeach
</div>
@endsection
