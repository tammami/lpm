<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Catat perubahan data (create/update/delete) ke log audit ketika dilakukan pengguna.
 * Proses tanpa pengguna (seeder, job terjadwal) tidak dicatat per baris.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            if (Auth::check()) {
                AuditLogger::created($model, $model->auditModule());
            }
        });

        static::updated(function (self $model): void {
            if (Auth::check()) {
                AuditLogger::updated($model, $model->auditModule());
            }
        });

        static::deleted(function (self $model): void {
            if (Auth::check()) {
                AuditLogger::deleted($model, $model->auditModule());
            }
        });
    }

    public function auditModule(): string
    {
        return property_exists($this, 'auditModule') ? $this->auditModule : 'master';
    }
}
