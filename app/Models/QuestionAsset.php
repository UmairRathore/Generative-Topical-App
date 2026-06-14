<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAsset extends Model
{
    protected $fillable = [
        'question_id',
        'question_option_id',
        'role',
        'image_path',
        'disk',
        'page',
        'bbox',
        'caption',
        'ocr_text',
        'confidence',
        'diagram_labels',
        'sort_order',
        'raw_payload',
    ];

    protected $casts = [
        'bbox' => 'array',
        'diagram_labels' => 'array',
        'raw_payload' => 'array',
        'confidence' => 'decimal:4',
        'page' => 'integer',
        'sort_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }
}
