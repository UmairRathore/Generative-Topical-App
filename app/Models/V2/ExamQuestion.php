<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $table = 'v2_exam_questions';

    protected $fillable = ['exam_id', 'question_id', 'sort_order', 'marks'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'marks' => 'integer'];
    }

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class, 'exam_id'); }
    // withTrashed: a frozen exam must still render its questions even if the source
    // question was later soft-deleted from the bank (historical papers stay intact).
    public function question(): BelongsTo { return $this->belongsTo(Question::class, 'question_id')->withTrashed(); }
}
