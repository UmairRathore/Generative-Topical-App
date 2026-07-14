<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

/**
 * One structured "Needs Revision" submission from the Super Admin visual
 * authoring review. Additive-only: a feedback record binds the exact lesson
 * SHA it reviewed and is never edited or deleted; the offline Stage B
 * authoring workflow reads it and produces a NEW artifact (new SHA).
 * Internal-only model, no HasHashid.
 */
class AuthoringReviewFeedback extends Model
{
    protected $table = 'v2_authoring_review_feedback';

    protected $fillable = [
        'artifact_id',
        'lesson_sha256',
        'scope',
        'reviewer',
        'severity',
        'overall_note',
        'items',
    ];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}
