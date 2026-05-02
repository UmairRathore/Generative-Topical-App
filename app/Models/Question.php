<?php

namespace App\Models;

use App\Enums\LayoutType;
use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
    protected $fillable = [
        'paper_id',
        'question_number',
        'question_text',
        'clean_question_text',
        'image_between_question_before_text',
        'image_between_question_after_text',
        'correct_answer',
        'explanation',
        'page_start',
        'page_end',
        'layout_type',
        'qa_status',
        'review_status',
        'visibility',
        'needs_review',
        'warnings',
        'raw_payload',
    ];

    protected $casts = [
        'warnings' => 'array',
        'raw_payload' => 'array',
        'needs_review' => 'boolean',
        'layout_type' => LayoutType::class,
        'qa_status' => QaStatus::class,
        'review_status' => QuestionReviewStatus::class,
        'visibility' => QuestionVisibility::class,
        'page_start' => 'integer',
        'page_end' => 'integer',
        'question_number' => 'integer',
    ];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(QuestionAsset::class)->orderBy('sort_order');
    }

    public function optionTable(): HasOne
    {
        return $this->hasOne(OptionTable::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'question_topic')
            ->withPivot(['confidence', 'source'])
            ->withTimestamps();
    }

    /**
     * Demo-safe scope: only questions safe for student-facing UI.
     *
     * Rules (per CLAUDE.md / docs):
     *   visibility = public
     *   review_status in {pass, render_fix, acceptable_fallback, approved}
     *   qa_status not in {failed, blocker}
     */
    public function scopeDemoSafe(Builder $query): Builder
    {
        return $query
            ->where('visibility', QuestionVisibility::Public->value)
            ->whereIn('review_status', QuestionReviewStatus::publicEligibleValues())
            ->whereNotIn('qa_status', QaStatus::publicDisqualifyingValues());
    }
}
