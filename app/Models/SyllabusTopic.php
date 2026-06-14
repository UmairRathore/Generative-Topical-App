<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusTopic extends Model
{
    protected $fillable = [
        'syllabus_code',
        'external_id',
        'title',
        'level',
        'intro_note',
        'sort_order',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'sort_order' => 'integer',
    ];

    public function subtopics(): HasMany
    {
        return $this->hasMany(SyllabusSubtopic::class)->orderBy('sort_order');
    }
}
