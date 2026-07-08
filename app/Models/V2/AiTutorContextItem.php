<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One context ingredient (asset, stem, stats, ...) that grounded an
 * assistant turn. Append-only; created_at is set by the database.
 */
class AiTutorContextItem extends Model
{
    protected $table = 'v2_ai_tutor_context_items';

    public $timestamps = false;

    protected $fillable = [
        'chat_id', 'message_id', 'item_type', 'ref_table', 'ref_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function chat(): BelongsTo { return $this->belongsTo(AiTutorChat::class, 'chat_id'); }
    public function message(): BelongsTo { return $this->belongsTo(AiTutorMessage::class, 'message_id'); }
}
