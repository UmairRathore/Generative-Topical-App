<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subtopic extends Model
{
    protected $table = 'v2_subtopics';

    protected $fillable = [
        'topic_id',
        'external_id',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'subtopic_id');
    }

    /** Canonical syllabus learning objectives mapped onto this leaf section (may span syllabus versions). */
    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'subtopic_id')->orderBy('objective_number');
    }
}
