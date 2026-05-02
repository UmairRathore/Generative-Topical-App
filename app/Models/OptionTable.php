<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionTable extends Model
{
    protected $fillable = [
        'question_id',
        'headers',
        'rows',
        'image_path',
        'disk',
        'bbox',
        'diagram_labels',
        'use_fallback_image',
        'raw_payload',
    ];

    protected $casts = [
        'headers' => 'array',
        'rows' => 'array',
        'bbox' => 'array',
        'diagram_labels' => 'array',
        'raw_payload' => 'array',
        'use_fallback_image' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
