<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'certificate_number', 'certification', 'certified_on', 'competencies', 'is_active'])]
class Auditor extends Model
{
    use Auditable;

    protected string $auditModule = 'ami';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'certified_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audits(): BelongsToMany
    {
        return $this->belongsToMany(Audit::class)->withPivot('role')->withTimestamps();
    }
}
