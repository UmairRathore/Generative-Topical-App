<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per AI call (ok, fallback or error) - cost/debug/audit trail.
 * Written by App\Services\AI\AiInteractionLogger; append-only.
 */
class AiInteractionLog extends Model
{
    protected $table = 'v2_ai_interaction_logs';

    public $timestamps = false;

    public const STATUS_OK = 'ok';
    public const STATUS_FALLBACK = 'fallback';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'actor_type', 'actor_id', 'role', 'feature',
        'source_context_type', 'source_context_id',
        'provider', 'model',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'cost_usd', 'latency_ms', 'status', 'error',
        'request_json', 'response_json', 'school_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_json'  => 'array',
            'response_json' => 'array',
            'cost_usd'      => 'decimal:6',
            'created_at'    => 'datetime',
        ];
    }
}
