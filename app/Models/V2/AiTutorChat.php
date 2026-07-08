<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Student AI Tutor conversation. One active chat per
 * (student, mistake, source_type); V1 chats are always mistake-anchored.
 */
class AiTutorChat extends Model
{
    use HasHashid;

    protected $table = 'v2_ai_tutor_chats';

    public const SOURCE_QUESTION = 'question';
    public const SOURCE_MISTAKE = 'mistake';
    public const SOURCE_SUBTOPIC = 'subtopic';
    public const SOURCE_TOPIC = 'topic';
    public const SOURCE_SUBJECT = 'subject';

    /** Sources a student can open in V1 (schema supports the rest for later). */
    public const ACTIVE_SOURCES = [self::SOURCE_QUESTION, self::SOURCE_MISTAKE];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'student_id', 'school_id', 'source_type',
        'question_id', 'student_mistake_id',
        'subject_id', 'topic_id', 'subtopic_id',
        'status', 'title', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Same-school isolation for the authenticated portal guard (mirrors StudentMistake).
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_ai_tutor_chats.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function mistake(): BelongsTo { return $this->belongsTo(StudentMistake::class, 'student_mistake_id'); }
    public function messages(): HasMany { return $this->hasMany(AiTutorMessage::class, 'chat_id')->orderBy('id'); }
    public function contextItems(): HasMany { return $this->hasMany(AiTutorContextItem::class, 'chat_id'); }
    public function quizzes(): HasMany { return $this->hasMany(AiTutorQuiz::class, 'chat_id'); }
}
