<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'v2_audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'details',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'details'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function log(
        Model $actor,
        string $action,
        ?Model $target = null,
        ?array $details = null
    ): static {
        return static::create([
            'actor_type'  => class_basename($actor),
            'actor_id'    => $actor->getKey(),
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'details'     => $details,
            'ip_address'  => request()->ip(),
        ]);
    }
}
