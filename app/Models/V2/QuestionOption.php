<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $table = 'v2_question_options';

    protected $fillable = [
        'question_id',
        'label',
        'text',
        'has_image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_image'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
