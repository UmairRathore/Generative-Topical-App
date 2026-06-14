<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusSubtopic extends Model
{
    protected $fillable = [
        'syllabus_topic_id',
        'external_id',
        'title',
        'sort_order',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'sort_order' => 'integer',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class, 'syllabus_topic_id');
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(SyllabusLearningObjective::class)->orderBy('objective_number');
    }
}
