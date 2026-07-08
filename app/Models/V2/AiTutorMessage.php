<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTutorMessage extends Model
{
    protected $table = 'v2_ai_tutor_messages';

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'chat_id', 'role', 'content',
        'model', 'prompt_tokens', 'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens'     => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function chat(): BelongsTo { return $this->belongsTo(AiTutorChat::class, 'chat_id'); }
}
