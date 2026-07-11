<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Save-to-Notes provenance row: one per successful import,
 * identifying the exact source object (see the migration). Never updated,
 * never deduplicated.
 */
class NotesImport extends Model
{
    protected $table = 'v2_notes_imports';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'page_id', 'source', 'source_id', 'question_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id'   => 'integer',
            'question_id' => 'integer',
            'created_at'  => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(NotesPage::class, 'page_id');
    }
}
