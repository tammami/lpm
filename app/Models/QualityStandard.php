<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Standar mutu SPMI (pernyataan standar, indikator, target).
 */
#[Fillable(['code', 'name', 'category', 'statement', 'indicator', 'target', 'reference', 'is_active', 'sort_order'])]
class QualityStandard extends Model
{
    use Auditable;

    protected string $auditModule = 'ami';

    public const CATEGORIES = [
        'pendidikan' => 'Standar Pendidikan',
        'penelitian' => 'Standar Penelitian',
        'pkm' => 'Standar Pengabdian kepada Masyarakat',
        'tata_kelola' => 'Standar Tata Kelola',
        'tambahan' => 'Standar Tambahan (Kekhasan)',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
