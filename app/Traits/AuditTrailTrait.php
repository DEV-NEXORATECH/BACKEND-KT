<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait AuditTrailTrait
{
    public static function bootAuditTrailTrait(): void
    {
        static::creating(function ($model) {
            if (\Schema::hasColumn($model->getTable(), 'created_by') && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (\Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            if (\Schema::hasColumn($model->getTable(), 'deleted_by') && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });

        static::created(function ($model) {
            static::writeAuditLog($model, 'CREATE', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $previous = collect(array_keys($changes))
                ->mapWithKeys(fn (string $key) => [$key => $model->getOriginal($key)])
                ->all();

            static::writeAuditLog($model, 'UPDATE', $previous, $changes);
        });

        static::deleted(function ($model) {
            static::writeAuditLog($model, 'DELETE', $model->getOriginal(), null);
        });
    }

    protected static function writeAuditLog($model, string $action, ?array $previous, ?array $new): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'module' => Str::of($model->getTable())->replace('_', '-')->toString(),
            'action' => $action,
            'entity_type' => $model::class,
            'entity_id' => $model->getKey(),
            'previous_values' => $previous,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
