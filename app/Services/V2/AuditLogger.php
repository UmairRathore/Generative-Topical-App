<?php

namespace App\Services\V2;

use App\Models\V2\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $target = null,
        ?array $details = null,
        ?Model $actor = null
    ): AuditLog {
        $actor ??= Auth::guard('v2_super_admin')->user()
            ?? Auth::guard('v2_school_admin')->user()
            ?? Auth::guard('v2_teacher')->user()
            ?? Auth::guard('v2_student')->user();

        return AuditLog::create([
            'actor_type'  => $actor ? class_basename($actor) : 'System',
            'actor_id'    => $actor?->getKey() ?? 0,
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'details'     => $details,
            'ip_address'  => request()->ip(),
        ]);
    }

    public static function record(
        string $action,
        ?Model $target = null,
        ?array $details = null,
        ?Model $actor = null
    ): AuditLog {
        return app(static::class)->log($action, $target, $details, $actor);
    }
}
