<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'label',
        'option_text',
        'sort_order',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'sort_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(QuestionAsset::class);
    }
}
