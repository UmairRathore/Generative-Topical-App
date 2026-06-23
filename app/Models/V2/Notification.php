<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/*
|--------------------------------------------------------------------------
| V2 in-app notification
|--------------------------------------------------------------------------
| One row = one notification for one recipient (polymorphic). See the
| v2_notifications migration for the two categories (update / attention) and
| their lifecycles. The Report model shares the HasHashid route-key approach.
*/
class Notification extends Model
{
    use HasHashid;

    protected $table = 'v2_notifications';

    protected $fillable = [
        'school_id',
        'notifiable_type',
        'notifiable_id',
        'category',
        'type',
        'student_id',
        'subject_id',
        'dedupe_key',
        'data',
        'read_at',
        'resolved_at',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'data'          => 'array',
            'read_at'       => 'datetime',
            'resolved_at'   => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /* ---- Scopes ---------------------------------------------------------- */

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function scopeAttention(Builder $q): Builder
    {
        return $q->where('category', 'attention');
    }

    public function scopeUpdates(Builder $q): Builder
    {
        return $q->where('category', 'update');
    }

    /** Open cases only: not resolved and not currently snoozed. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('resolved_at')
            ->where(function ($w) {
                $w->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            });
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
