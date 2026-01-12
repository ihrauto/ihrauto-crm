<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAudit('created', $model);
        });

        static::updated(function (Model $model) {
            self::logAudit('updated', $model);
        });

        static::deleted(function (Model $model) {
            self::logAudit('deleted', $model);
        });
    }

    protected static function logAudit($action, Model $model)
    {
        // Don't log if running in console (unless explicitly wanted, but usually noisy)
        // keeping it simple for now, logging everything.

        $changes = null;

        if ($action === 'updated') {
            $changes = [
                'before' => $model->getOriginal(),
                'after' => $model->getChanges(),
            ];
            // Filter out timestamps to reduce noise
            unset($changes['before']['updated_at'], $changes['after']['updated_at']);

            // If no meaningful changes, don't log
            if (empty($changes['after'])) {
                return;
            }
        } elseif ($action === 'created') {
            $changes = [
                'attributes' => $model->getAttributes(),
            ];
        }

        AuditLog::create([
            'user_id' => Auth::id() ?? null, // Null if system action or unauthenticated
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
