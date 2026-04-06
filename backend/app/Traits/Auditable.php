<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }

            $oldValues = array_intersect_key($model->getOriginal(), $changes);
            AuditLog::record('updated', $model, $oldValues, $changes);
        });

        static::deleted(function ($model) {
            AuditLog::record('deleted', $model, $model->getAttributes(), null);
        });
    }
}
