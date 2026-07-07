<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Top level of a student's Notes tree: Notebook -> Section -> Page.
 * Student-personal; archiving hides without deleting.
 */
class NotesNotebook extends Model
{
    use HasHashid;

    protected $table = 'v2_notes_notebooks';

    protected $fillable = [
        'student_id', 'school_id', 'subject_id', 'title', 'emoji', 'color', 'sort_order', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'  => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Same-school isolation for the authenticated portal guard (mirrors StudentMistake).
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_notes_notebooks.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class, 'subject_id'); }
    public function sections(): HasMany { return $this->hasMany(NotesSection::class, 'notebook_id')->orderBy('sort_order'); }
    public function pages(): HasManyThrough { return $this->hasManyThrough(NotesPage::class, NotesSection::class, 'notebook_id', 'section_id'); }
}
