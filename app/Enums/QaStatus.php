<?php

namespace App\Enums;

enum QaStatus: string
{
    case Pass = 'pass';
    case RenderFix = 'render_fix';
    case AcceptableFallback = 'acceptable_fallback';
    case Review = 'review';
    case Failed = 'failed';
    case Blocker = 'blocker';

    /**
     * QA statuses that disqualify a question from public exposure.
     *
     * @return array<int, string>
     */
    public static function publicDisqualifyingValues(): array
    {
        return [
            self::Failed->value,
            self::Blocker->value,
        ];
    }
}
