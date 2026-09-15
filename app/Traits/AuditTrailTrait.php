<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

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
    }
}