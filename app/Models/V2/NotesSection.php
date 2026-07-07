<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Middle level of the Notes tree: groups pages inside a notebook
 * ("Electricity" in "O Level Physics").
 */
class NotesSection extends Model
{
    use HasHashid;

    protected $table = 'v2_notes_sections';

    protected $fillable = [
        'notebook_id', 'student_id', 'school_id', 'topic_id', 'title', 'sort_order', 'is_archived',
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
                    $builder->where('v2_notes_sections.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function notebook(): BelongsTo { return $this->belongsTo(NotesNotebook::class, 'notebook_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class, 'topic_id'); }
    public function pages(): HasMany { return $this->hasMany(NotesPage::class, 'section_id')->orderBy('sort_order'); }
}
