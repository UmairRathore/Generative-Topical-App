<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusLearningObjective extends Model
{
    protected $fillable = [
        'syllabus_subtopic_id',
        'objective_number',
        'text',
        'sub_items',
        'raw_json',
    ];

    protected $casts = [
        'sub_items' => 'array',
        'raw_json' => 'array',
        'objective_number' => 'integer',
    ];

    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(SyllabusSubtopic::class, 'syllabus_subtopic_id');
    }
}
