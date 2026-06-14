<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Qualification extends Model
{
    protected $fillable = ['exam_board_id', 'name', 'slug', 'level_order'];

    public function examBoard(): BelongsTo
    {
        return $this->belongsTo(ExamBoard::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
