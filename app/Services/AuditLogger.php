<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Pencatat jejak audit (siapa, melakukan apa, di modul mana, sebelum/sesudah).
 */
class AuditLogger
{
    /**
     * Atribut yang tidak boleh ikut tercatat.
     *
     * @var list<string>
     */
    private const REDACTED = ['password', 'remember_token', 'response_ref'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(
        string $event,
        ?string $module = null,
        ?Model $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'module' => $module,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old ? Arr::except($old, self::REDACTED) : null,
            'new_values' => $new ? Arr::except($new, self::REDACTED) : null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    public static function created(Model $model, string $module, ?string $description = null): void
    {
        static::log('created', $module, $model, $description, null, $model->attributesToArray());
    }

    public static function updated(Model $model, string $module, ?string $description = null): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return;
        }

        $original = Arr::only($model->getPrevious(), array_keys($changes));

        static::log('updated', $module, $model, $description, $original, $changes);
    }

    public static function deleted(Model $model, string $module, ?string $description = null): void
    {
        static::log('deleted', $module, $model, $description, $model->attributesToArray());
    }
}
