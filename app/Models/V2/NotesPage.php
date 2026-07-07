<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One BlockNote document per page. document_json is the editor's native
 * block array stored verbatim; plain_text + block_types_json are rebuilt on
 * every save so search never parses the document. content_version guards
 * against a stale autosave overwriting a newer document (409, not clobber).
 */
class NotesPage extends Model
{
    use HasHashid;

    protected $table = 'v2_notes_pages';

    public const SAVE_SAVED = 'saved';
    public const SAVE_ERROR = 'error';

    protected $fillable = [
        'section_id', 'student_id', 'school_id', 'title', 'icon',
        'document_json', 'plain_text', 'block_types_json',
        'subject_id', 'topic_id', 'subtopic_id',
        'is_pinned', 'is_favorite', 'is_archived', 'sort_order',
        'save_status', 'content_version', 'last_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'document_json'    => 'array',
            'block_types_json' => 'array',
            'is_pinned'        => 'boolean',
            'is_favorite'      => 'boolean',
            'is_archived'      => 'boolean',
            'sort_order'       => 'integer',
            'content_version'  => 'integer',
            'last_edited_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Same-school isolation for the authenticated portal guard (mirrors StudentMistake).
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_notes_pages.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function section(): BelongsTo { return $this->belongsTo(NotesSection::class, 'section_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class, 'subject_id'); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class, 'topic_id'); }
    public function subtopic(): BelongsTo { return $this->belongsTo(Subtopic::class, 'subtopic_id'); }
    public function tags(): HasMany { return $this->hasMany(NotesPageTag::class, 'page_id'); }
    public function versions(): HasMany { return $this->hasMany(NotesPageVersion::class, 'page_id')->orderByDesc('id'); }
}
