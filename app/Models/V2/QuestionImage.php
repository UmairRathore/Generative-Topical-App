<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImage extends Model
{
    protected $table = 'v2_question_images';

    protected $fillable = [
        'question_id',
        'external_id',
        'image_path',
        'role',
        'option_label',
        'page',
        'bbox',
        'caption',
        'ocr_text',
        'confidence',
        'diagram_labels',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bbox'           => 'array',
            'diagram_labels' => 'array',
            'page'           => 'integer',
            'confidence'     => 'float',
            'sort_order'     => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
