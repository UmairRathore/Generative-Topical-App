<?php

namespace App\Services\Import;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;

class QuestionStatusResolver
{
    /**
     * Derive (qa_status, review_status, visibility, needs_review) from extractor payload.
     *
     * @param  array<string,mixed>  $question  The raw question payload from questions.json.
     * @return array{qa_status: string, review_status: string, visibility: string, needs_review: bool}
     */
    public function resolve(array $question): array
    {
        $rawQa = $this->stringOrNull($question['qa_status'] ?? null);
        $rawReview = $this->stringOrNull($question['review_status'] ?? null);
        $needsReview = (bool) ($question['needs_review'] ?? false);
        $warnings = is_array($question['warnings'] ?? null) ? $question['warnings'] : [];
        $forceBlocker = (bool) ($question['__force_blocker'] ?? false);

        if ($forceBlocker) {
            return [
                'qa_status' => QaStatus::Blocker->value,
                'review_status' => QuestionReviewStatus::Rejected->value,
                'visibility' => QuestionVisibility::Hidden->value,
                'needs_review' => true,
            ];
        }

        $qa = $this->normalizeQa($rawQa, $warnings, $needsReview);
        $review = $this->normalizeReview($rawReview, $qa, $needsReview);
        $visibility = $this->resolveVisibility($qa, $review);

        return [
            'qa_status' => $qa->value,
            'review_status' => $review->value,
            'visibility' => $visibility->value,
            'needs_review' => $needsReview || $review === QuestionReviewStatus::Review,
        ];
    }

    private function normalizeQa(?string $raw, array $warnings, bool $needsReview): QaStatus
    {
        if ($raw !== null) {
            $candidate = QaStatus::tryFrom($raw);
            if ($candidate) {
                return $candidate;
            }
        }

        if ($this->hasBlocker($warnings)) {
            return QaStatus::Blocker;
        }

        if ($needsReview || ! empty($warnings)) {
            return QaStatus::Review;
        }

        return QaStatus::Pass;
    }

    private function normalizeReview(?string $raw, QaStatus $qa, bool $needsReview): QuestionReviewStatus
    {
        if ($raw !== null) {
            $candidate = QuestionReviewStatus::tryFrom($raw);
            if ($candidate) {
                return $candidate;
            }
        }

        return match ($qa) {
            QaStatus::Pass => QuestionReviewStatus::Pass,
            QaStatus::RenderFix => QuestionReviewStatus::RenderFix,
            QaStatus::AcceptableFallback => QuestionReviewStatus::AcceptableFallback,
            QaStatus::Failed, QaStatus::Blocker => QuestionReviewStatus::Rejected,
            QaStatus::Review => QuestionReviewStatus::Review,
        };
    }

    private function resolveVisibility(QaStatus $qa, QuestionReviewStatus $review): QuestionVisibility
    {
        if (in_array($qa->value, QaStatus::publicDisqualifyingValues(), true)) {
            return QuestionVisibility::Hidden;
        }

        if (in_array($review->value, QuestionReviewStatus::publicEligibleValues(), true)) {
            return QuestionVisibility::Public;
        }

        return QuestionVisibility::AdminOnly;
    }

    private function hasBlocker(array $warnings): bool
    {
        foreach ($warnings as $warning) {
            $code = is_array($warning) ? ($warning['code'] ?? $warning['type'] ?? null) : $warning;
            if (is_string($code) && str_contains(strtolower($code), 'blocker')) {
                return true;
            }
        }

        return false;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
