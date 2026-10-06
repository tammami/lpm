<?php

namespace App\Models;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Concerns\Auditable;
use Database\Factories\ScientificWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Karya ilmiah dosen (publikasi, buku, HKI). Hanya data isian — tanpa berkas.
 */
#[Fillable([
    'lecturer_id', 'title', 'type', 'year', 'venue', 'rank', 'author_role', 'co_authors', 'source', 'funding',
    'identifier', 'volume', 'url', 'status', 'verification_note', 'verified_by', 'verified_at', 'created_by',
])]
class ScientificWork extends Model
{
    /** @use HasFactory<ScientificWorkFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'research';

    public const AUTHOR_ROLES = [
        'first' => 'Penulis pertama',
        'corresponding' => 'Penulis korespondensi',
        'member' => 'Penulis anggota',
    ];

    public const SOURCES = [
        'research' => 'Hasil penelitian',
        'service' => 'Hasil pengabdian (PkM)',
        'other' => 'Lainnya',
    ];

    public const FUNDINGS = [
        'self' => 'Mandiri',
        'internal' => 'Internal perguruan tinggi',
        'government' => 'Pemerintah (Kemenag, Kemdiktisaintek, dll.)',
        'external' => 'Mitra/lembaga lain dalam negeri',
        'foreign' => 'Luar negeri',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScientificWorkType::class,
            'status' => ScientificWorkStatus::class,
            'year' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Batasi ke karya dosen yang homebase-nya berada dalam cakupan pengguna.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->accessibleStudyProgramIds() !== null) {
            $query->whereHas('lecturer', fn (Builder $lecturer) => $lecturer->visibleTo($user));
        }
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $labels): array
    {
        return collect($labels)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all();
    }
}
