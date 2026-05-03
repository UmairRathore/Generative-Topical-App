@php
    $statusMap = [
        'pass'                => ['Pass',       'badge-pass'],
        'render_fix'          => ['Render Fix', 'badge-render'],
        'acceptable_fallback' => ['Fallback',   'badge-fallback'],
        'review'              => ['Review',     'badge-review'],
        'failed'              => ['Failed',     'badge-blocker'],
        'blocker'             => ['Blocker',    'badge-blocker'],
    ];
    $qaValue = $question->qa_status?->value;
    [$qaLabel, $qaCls] = $statusMap[$qaValue] ?? ['—', 'badge-soft'];
@endphp
<div>
    <x-gt.page-header
        :breadcrumb="['Admin','Question Bank','Review']"
        :title="'Question review · #'.$question->question_number"
    />

    <a href="{{ route('admin.questions') }}" wire:navigate class="btn btn-ghost btn-sm" style="margin-bottom: 16px;">
        <x-icon name="chev-l" size="13"/>Back to all questions
    </a>

    @if(session('admin.review.flash'))
        <div style="margin-bottom: 16px; padding: 12px 14px; border: 1px solid #BBF7D0; background: var(--success-soft); color: #15803D; border-radius: 8px; font-size: 13px;">
            {{ session('admin.review.flash') }}
        </div>
    @endif

    <div class="grid" style="grid-template-columns: 1fr; gap: 16px;">
        <div class="card-elev" style="padding: 28px;">
            <div class="flex justify-between items-start" style="margin-bottom: 24px;">
                <div>
                    <div style="font-size: 11px; font-weight: 600; color: var(--gold-700); text-transform: uppercase; letter-spacing: 0.08em;">
                        Q-{{ $question->id }} · {{ $question->paper?->subject?->name ?? 'Subject' }} · {{ $question->topics->first()?->name ?? '—' }}
                    </div>
                    <h2 class="serif" style="font-size: 22px; font-weight: 600; margin-top: 6px;">{{ $question->paper?->paper_code ?? 'Paper' }} · Q{{ $question->question_number }}</h2>
                    <div style="font-size: 12px; color: var(--text-faint); margin-top: 4px;">
                        Updated {{ optional($question->updated_at)->diffForHumans() }} · {{ $question->paper?->session ?? '—' }} {{ $question->paper?->year ?? '' }}
                    </div>
                </div>
                <span class="badge {{ $qaCls }}">{{ $qaLabel }}</span>
            </div>

            <p class="serif" style="font-size: 17px; line-height: 1.55; color: var(--text);">
                {{ $question->clean_question_text ?: $question->question_text ?: '—' }}
            </p>

            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 18px;">
                @foreach($question->options as $opt)
                    @php $correct = $opt->label === $question->correct_answer; @endphp
                    <div class="flex items-center" style="padding: 12px 16px; border-radius: 8px; gap: 10px;
                            border: {{ $correct ? '1.5px solid var(--success)' : '1px solid var(--border)' }};
                            background: {{ $correct ? 'var(--success-soft)' : 'var(--surface)' }};">
                        <div class="flex items-center justify-center" style="width: 26px; height: 26px; border-radius: 50%;
                                background: {{ $correct ? 'var(--success)' : 'var(--surface)' }};
                                color: {{ $correct ? 'var(--ivory)' : 'var(--text-soft)' }};
                                border: {{ $correct ? '0' : '1px solid var(--border)' }};
                                font-size: 11px; font-weight: 700;">{{ $opt->label }}</div>
                        <span class="serif" style="font-size: 15px; font-weight: 500;">{{ $opt->text }}</span>
                        @if($correct)
                            <span style="margin-left: auto; font-size: 10px; font-weight: 700; color: #15803D; text-transform: uppercase; letter-spacing: 0.06em;">Correct</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($question->explanation)
                <div style="margin-top: 24px; padding: 18px; background: var(--gold-50); border-radius: 8px; border: 1px solid var(--gold-100);">
                    <div class="uppercase-eyebrow">Worked solution</div>
                    <p class="serif" style="font-size: 14px; line-height: 1.55; margin-top: 8px; color: var(--text);">{{ $question->explanation }}</p>
                </div>
            @endif

            @if(! empty($question->warnings))
                <div style="margin-top: 16px; padding: 14px; background: var(--warning-soft); border-radius: 8px; border: 1px solid #FDE68A;">
                    <div style="font-size: 11px; font-weight: 700; color: #B45309; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px;">Reviewer warnings</div>
                    <ul style="font-size: 12px; color: var(--text); line-height: 1.6;">
                        @foreach($question->warnings as $w)
                            <li>· {{ is_string($w) ? $w : json_encode($w) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 22px;">
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Layout</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">{{ $question->layout_type?->value ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Visibility</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">{{ $question->visibility?->value ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Review status</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">{{ $question->review_status?->value ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size: 10px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.06em;">Source</div>
                    <div style="font-size: 13px; font-weight: 500; margin-top: 4px;">{{ $question->paper?->source_file ?? '—' }}</div>
                </div>
            </div>

            <hr class="divider" style="margin: 28px 0 22px;"/>
            <div class="flex" style="gap: 10px;">
                <button wire:click="approve" class="btn btn-primary"><x-icon name="check" size="14"/>Approve & publish</button>
                <button wire:click="markNeedsReview" class="btn btn-ghost" style="background: var(--warning-soft); color: #B45309; border-color: #FDE68A;"><x-icon name="flag" size="14"/>Request fix</button>
                <button wire:click="useFallback" class="btn btn-ghost"><x-icon name="edit" size="14"/>Use fallback crop</button>
                <div class="flex-1"></div>
                <button wire:click="hide" class="btn btn-ghost"><x-icon name="x" size="14"/>Hide</button>
            </div>
        </div>
    </div>
</div>
