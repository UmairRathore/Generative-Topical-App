<?php

namespace App\Enums;

enum QuestionReviewStatus: string
{
    case Pass = 'pass';
    case RenderFix = 'render_fix';
    case AcceptableFallback = 'acceptable_fallback';
    case Approved = 'approved';
    case Review = 'review';
    case Rejected = 'rejected';

    /**
     * Statuses that are eligible for public/student exposure.
     *
     * @return array<int, string>
     */
    public static function publicEligibleValues(): array
    {
        return [
            self::Pass->value,
            self::RenderFix->value,
            self::AcceptableFallback->value,
            self::Approved->value,
        ];
    }
}
