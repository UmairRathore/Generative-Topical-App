<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's own free-text label on a notes page ("exam-tip", "formula").
 * Curriculum tags (subject/topic/subtopic) are columns on the page itself.
 */
class NotesPageTag extends Model
{
    protected $table = 'v2_notes_page_tags';

    protected $fillable = ['page_id', 'student_id', 'tag'];

    public function page(): BelongsTo { return $this->belongsTo(NotesPage::class, 'page_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
}
