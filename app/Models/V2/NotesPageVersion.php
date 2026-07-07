<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a page (title + document_json) taken on save.
 * Pruned to the newest 20 per page; restore copies a snapshot back after
 * snapshotting the current state first.
 */
class NotesPageVersion extends Model
{
    use HasHashid;

    protected $table = 'v2_notes_page_versions';

    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'student_id', 'title', 'document_json', 'content_version'];

    protected function casts(): array
    {
        return [
            'document_json'   => 'array',
            'content_version' => 'integer',
            'created_at'      => 'datetime',
        ];
    }

    public function page(): BelongsTo { return $this->belongsTo(NotesPage::class, 'page_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
}
