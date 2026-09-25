<?php

namespace App\Models;

use App\Enums\AuditProgramStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'year', 'academic_period_id', 'scope', 'objective', 'criteria', 'starts_on', 'ends_on', 'status', 'created_by'])]
class AuditProgram extends Model
{
    use Auditable;

    protected string $auditModule = 'ami';

    protected function casts(): array
    {
        return [
            'status' => AuditProgramStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'year' => 'integer',
        ];
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
