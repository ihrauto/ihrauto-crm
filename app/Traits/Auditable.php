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

        // Audit-M-24: scrub fields the model marks $hidden so password
        // hashes, remember_tokens, invite_tokens never land in audit_logs.
        // Bcrypt is hashed already, but storing it twice doesn't help and
        // does broaden the data-handling surface for compliance.
        $hidden = method_exists($model, 'getHidden') ? $model->getHidden() : [];
        $scrub = static function (array $row) use ($hidden): array {
            foreach ($hidden as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = '[redacted]';
                }
            }

            return $row;
        };

        if ($action === 'updated') {
            $changes = [
                'before' => $scrub($model->getOriginal()),
                'after' => $scrub($model->getChanges()),
            ];
            // Filter out timestamps to reduce noise
            unset($changes['before']['updated_at'], $changes['after']['updated_at']);

            // If no meaningful changes, don't log
            if (empty($changes['after'])) {
                return;
            }
        } elseif ($action === 'created') {
            $changes = [
                'attributes' => $scrub($model->getAttributes()),
            ];
        }

        // Get user_id safely - verify user exists to prevent FK violations
        $userId = Auth::id();
        if ($userId) {
            // Verify user still exists (may have been deleted)
            $userExists = \App\Models\User::withoutGlobalScopes()->where('id', $userId)->exists();
            if (! $userExists) {
                $userId = null;
            }
        }

        try {
            AuditLog::create([
                'tenant_id' => tenant_id(),
                'user_id' => $userId,
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => $changes,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging failures must NOT break the main operation (the
            // business action has already succeeded by the time we write the
            // audit). But they also must NOT be silent — lost audit trails
            // have compliance implications. We log at ERROR level with
            // structured context so monitoring (Sentry) picks it up.
            //
            // D.8 — previously this used \Log::warning() with just the
            // message, which didn't include the model ID or action, making
            // it impossible to reconstruct what was missed.
            \Log::error('audit_log_write_failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'tenant_id' => tenant_id(),
                'user_id' => $userId,
            ]);

            // Report to Sentry if available, so audit-log outages surface
            // to the on-call rotation rather than disappearing into log files.
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        }
    }
}
