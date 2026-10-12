<?php

namespace App\Models;

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Concerns\Auditable;
use Database\Factories\ScientificWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Karya dosen (publikasi, buku, HKI, karya lain) sesuai template Data Karya Dosen LPM.
 * Bukti berupa tautan — tanpa unggah berkas. Dosen rekan & mahasiswa yang ikut menulis dicatat sebagai anggota.
 */
#[Fillable([
    'lecturer_id', 'title', 'type', 'year', 'venue', 'indexation', 'author_role', 'author_count', 'co_authors', 'source',
    'funding', 'relevant_to_program', 'relevant_to_roadmap', 'identifier', 'doi', 'volume', 'url', 'evidence_url',
    'ip_number', 'ip_status', 'status', 'verification_note', 'verified_by', 'verified_at', 'created_by',
])]
class ScientificWork extends Model
{
    /** @use HasFactory<ScientificWorkFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'research';

    /** Peran Dosen (template). */
    public const AUTHOR_ROLES = [
        'single' => 'Penulis Tunggal',
        'first' => 'Penulis Pertama',
        'second' => 'Penulis Kedua',
        'third' => 'Penulis Ketiga',
        'corresponding' => 'Penulis Korespondensi',
        'editor' => 'Editor',
        'contributor' => 'Kontributor',
        'other' => 'Lainnya',
    ];

    /** Asal kegiatan (tambahan aplikasi, untuk LKPS). */
    public const SOURCES = [
        'research' => 'Hasil penelitian',
        'service' => 'Hasil pengabdian (PkM)',
        'other' => 'Lainnya',
    ];

    /** Sumber Dana (template). */
    public const FUNDINGS = [
        'self' => 'Mandiri',
        'institution' => 'Institusi',
        'internal_grant' => 'Hibah Internal',
        'external_grant' => 'Hibah Eksternal',
        'partner' => 'Kerja Sama/Mitra',
        'other' => 'Lainnya',
    ];

    /** Status HKI (template); kosong = Tidak Berlaku. */
    public const IP_STATUSES = [
        'registered' => 'Terdaftar',
        'granted' => 'Granted/Diberikan',
        'certified' => 'Bersertifikat',
        'in_process' => 'Dalam Proses',
        'unknown' => 'Tidak Diketahui',
    ];

    public const MAX_MEMBERS = 20;

    protected function casts(): array
    {
        return [
            'type' => ScientificWorkType::class,
            'indexation' => ScientificWorkIndexation::class,
            'status' => ScientificWorkStatus::class,
            'year' => 'integer',
            'author_count' => 'integer',
            'relevant_to_program' => 'boolean',
            'relevant_to_roadmap' => 'boolean',
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

    public function members(): HasMany
    {
        return $this->hasMany(ScientificWorkMember::class)->orderBy('position')->orderBy('id');
    }

    public function lecturerMembers(): HasMany
    {
        return $this->members()->where('kind', ScientificWorkMember::LECTURER);
    }

    public function studentMembers(): HasMany
    {
        return $this->members()->where('kind', ScientificWorkMember::STUDENT);
    }

    /**
     * Karya milik dosen ini atau yang menandainya sebagai dosen rekan.
     *
     * @param  Builder<self>  $query
     * @param  list<int>|int  $lecturerIds
     */
    public function scopeCreditedTo(Builder $query, array|int $lecturerIds): void
    {
        $ids = (array) $lecturerIds;

        $query->where(fn (Builder $inner) => $inner
            ->whereIn('lecturer_id', $ids)
            ->orWhereHas('lecturerMembers', fn (Builder $member) => $member->whereIn('lecturer_id', $ids)));
    }

    /**
     * Batasi ke karya yang pemilik atau dosen rekannya berada dalam cakupan pengguna.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->accessibleStudyProgramIds() !== null) {
            $query->where(fn (Builder $inner) => $inner
                ->whereHas('lecturer', fn (Builder $lecturer) => $lecturer->visibleTo($user))
                ->orWhereHas('lecturerMembers.lecturer', fn (Builder $lecturer) => $lecturer->visibleTo($user)));
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
