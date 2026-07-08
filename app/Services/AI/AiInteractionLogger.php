<?php

namespace App\Services\AI;

use App\Models\V2\AiInteractionLog;
use Illuminate\Support\Facades\Auth;

/**
 * One v2_ai_interaction_logs row per AI call - success, fallback or error
 * (docs/ai-context/ai/04, rule 10). Mirrors AuditLogger's actor resolution.
 * Callers pass a REDACTED request summary, never the full context payload.
 */
class AiInteractionLogger
{
    public function log(string $feature, array $attrs): AiInteractionLog
    {
        [$actor, $role] = $this->actor();

        return AiInteractionLog::create(array_merge([
            'actor_type' => $actor ? class_basename($actor) : 'System',
            'actor_id'   => $actor?->getKey() ?? 0,
            'role'       => $role ?? 'system',
            'feature'    => $feature,
            'status'     => AiInteractionLog::STATUS_OK,
            'school_id'  => $actor->school_id ?? null,
            'created_at' => now(),
        ], $attrs));
    }

    public static function record(string $feature, array $attrs): AiInteractionLog
    {
        return app(static::class)->log($feature, $attrs);
    }

    /** @return array{0: ?\Illuminate\Database\Eloquent\Model, 1: ?string} */
    private function actor(): array
    {
        foreach (['v2_super_admin', 'v2_school_admin', 'v2_teacher', 'v2_student'] as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                return [$user, $guard];
            }
        }

        return [null, null];
    }
}
