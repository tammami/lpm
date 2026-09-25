<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_name', 'address', 'email', 'phone', 'website', 'logo_path', 'rector_name', 'lpm_head_name', 'is_active'])]
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Institusi utama aplikasi (single-tenant, siap dikembangkan menjadi multi-tenant).
     */
    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
