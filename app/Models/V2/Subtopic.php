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
}
