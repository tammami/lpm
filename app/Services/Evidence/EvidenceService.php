<?php

namespace App\Services\Evidence;

use App\Enums\ActionPlanStatus;
use App\Enums\CorrectiveActionStatus;
use App\Enums\EvidenceStatus;
use App\Enums\FindingStatus;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationPeriod;
use App\Models\ActionPlan;
use App\Models\Audit;
use App\Models\CorrectiveAction;
use App\Models\Evidence;
use App\Models\EvidenceMapping;
use App\Models\EvidenceVersion;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\User;
use App\Models\Verification;
use App\Services\AuditLogger;
use App\Services\Settings;
use App\Support\Auditee;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Repositori dokumen bukti: unggah (disk privat), versi, pemetaan multi-guna, verifikasi.
 */
class EvidenceService
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];

    /**
     * Tipe objek yang boleh dipetakan ke dokumen (alias morph → kelas).
     *
     * @return array<string, class-string<Model>>
     */
    public static function mappableTypes(): array
    {
        return array_filter([
            'finding' => Finding::class,
            'corrective_action' => CorrectiveAction::class,
            'recommendation' => Recommendation::class,
            'action_plan' => ActionPlan::class,
            'audit' => Audit::class,
            'accreditation_indicator' => AccreditationIndicator::class,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function uploadRules(bool $required = true): array
    {
        $maxKb = (int) Settings::get('evidence.max_upload_mb', 20) * 1024;

        return [$required ? 'required' : 'nullable', 'file', 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS), "max:{$maxKb}"];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, ?UploadedFile $file = null, ?string $url = null): Evidence
    {
        return DB::transaction(function () use ($data, $user, $file, $url): Evidence {
            $unit = isset($data['unit']) && $data['unit'] ? Auditee::resolve(...Auditee::parse($data['unit'])) : null;

            $evidence = Evidence::query()->create([
                'code' => ($data['code'] ?? null) ?: CodeGenerator::next('DOK', 'evidence'),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'evidence_category_id' => $data['evidence_category_id'] ?? null,
                'document_type' => $file ? 'file' : 'link',
                'owner_user_id' => $user->id,
                'unit_type' => $unit['type'] ?? null,
                'unit_id' => $unit['id'] ?? null,
                'unit_name' => $unit['name'] ?? null,
                'study_program_id' => $unit['study_program_id'] ?? null,
                'faculty_id' => $unit['faculty_id'] ?? null,
                'year' => $data['year'] ?? now()->year,
                'academic_period_id' => $data['academic_period_id'] ?? null,
                'status' => EvidenceStatus::Pending,
                'valid_until' => $data['valid_until'] ?? null,
                'confidentiality' => $data['confidentiality'] ?? 'internal',
                'created_by' => $user->id,
            ]);

            $this->addVersion($evidence, $user, $file, $url, 'Versi awal.');

            return $evidence;
        });
    }

    public function addVersion(Evidence $evidence, User $user, ?UploadedFile $file, ?string $url, ?string $notes = null): EvidenceVersion
    {
        if (! $file && ! $url) {
            throw ValidationException::withMessages(['file' => 'Unggah berkas atau isi tautan dokumen.']);
        }

        $number = (int) $evidence->versions()->max('version') + 1;
        $attributes = ['version' => $number, 'notes' => $notes, 'uploaded_by' => $user->id];

        if ($file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $attributes += [
                'file_path' => $file->storeAs("evidence/{$evidence->id}", "v{$number}-".Str::random(12).".{$extension}", 'local'),
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
            ];
        } else {
            $attributes['url'] = $url;
        }

        $version = $evidence->versions()->create($attributes);
        $evidence->update([
            'current_version_id' => $version->id,
            'document_type' => $file ? 'file' : 'link',
            // Versi baru perlu diverifikasi ulang.
            'status' => EvidenceStatus::Pending,
        ]);

        AuditLogger::log('uploaded', 'evidence', $evidence, "Mengunggah {$evidence->code} versi {$number}");

        return $version;
    }

    public function map(Evidence $evidence, string $type, int $id, User $user, ?int $contextId = null, ?string $note = null): EvidenceMapping
    {
        $class = self::mappableTypes()[$type] ?? throw ValidationException::withMessages(['mappable_type' => 'Jenis pemetaan tidak dikenal.']);
        $mappable = $class::query()->findOrFail($id);
        self::ensureUnlocked($mappable, $contextId);

        return EvidenceMapping::query()->firstOrCreate(
            ['evidence_id' => $evidence->id, 'mappable_type' => (new $class)->getMorphClass(), 'mappable_id' => $id, 'context_id' => $contextId],
            ['note' => $note, 'created_by' => $user->id],
        );
    }

    public function verify(Evidence $evidence, User $user, bool $accepted, ?string $notes): void
    {
        DB::transaction(function () use ($evidence, $user, $accepted, $notes): void {
            Verification::query()->create([
                'verifiable_type' => $evidence->getMorphClass(),
                'verifiable_id' => $evidence->id,
                'verifier_id' => $user->id,
                'decision' => $accepted ? 'accepted' : 'rejected',
                'notes' => $notes,
                'verified_at' => now(),
            ]);

            $evidence->update(['status' => $accepted ? EvidenceStatus::Verified : EvidenceStatus::Rejected]);
        });

        AuditLogger::log('verified', 'evidence', $evidence, ($accepted ? 'Memverifikasi' : 'Menolak').' dokumen '.$evidence->code, null, ['notes' => $notes]);
    }

    /**
     * Payload dokumen tertaut untuk ditampilkan pada halaman objek (temuan, rencana aksi, dll.).
     *
     * @param  iterable<EvidenceMapping>  $mappings
     * @return list<array<string, mixed>>
     */
    /**
     * Objek yang sudah diverifikasi/ditutup tidak boleh berubah buktinya, agar hasil verifikasi tetap dapat dipertanggungjawabkan.
     */
    public static function isLocked(?Model $mappable): bool
    {
        return match (true) {
            $mappable instanceof ActionPlan => $mappable->status === ActionPlanStatus::Verified,
            $mappable instanceof CorrectiveAction => $mappable->status === CorrectiveActionStatus::Verified,
            $mappable instanceof Finding => $mappable->status === FindingStatus::Closed,
            default => false,
        };
    }

    public static function ensureUnlocked(?Model $mappable, ?int $contextId = null): void
    {
        if ($mappable instanceof AccreditationIndicator) {
            $period = self::accreditationPeriod($mappable, $contextId);

            if (! $period->status->isOpen()) {
                throw ValidationException::withMessages(['mappable_id' => 'Periode akreditasi sudah ditutup sehingga buktinya terkunci.']);
            }

            return;
        }

        if (self::isLocked($mappable)) {
            throw ValidationException::withMessages(['mappable_id' => 'Objek ini sudah diverifikasi/ditutup sehingga buktinya terkunci.']);
        }
    }

    /**
     * Bukti indikator akreditasi wajib terikat pada periode yang memakai versi instrumen yang sama.
     */
    public static function accreditationPeriod(AccreditationIndicator $indicator, ?int $contextId): AccreditationPeriod
    {
        $period = $contextId ? AccreditationPeriod::query()->find($contextId) : null;

        if (! $period || $period->instrument_version_id !== $indicator->instrument_version_id) {
            throw ValidationException::withMessages(['context_id' => 'Pilih periode akreditasi yang sesuai dengan instrumen indikator.']);
        }

        return $period;
    }

    public static function linkedPayload(iterable $mappings, User $user, bool $locked = false): array
    {
        $items = [];

        foreach ($mappings as $mapping) {
            $evidence = $mapping->evidence;

            if (! $evidence) {
                continue;
            }

            $version = $evidence->currentVersion;
            $status = $evidence->isExpired() ? EvidenceStatus::Expired : $evidence->status;

            $items[] = [
                'mapping_id' => $mapping->id,
                'id' => $evidence->id,
                'code' => $evidence->code,
                'title' => $evidence->title,
                'document_type' => $evidence->document_type,
                'status' => $status->value,
                'status_label' => $status->label(),
                'version' => $version?->version,
                'file_name' => $version?->original_name,
                'mime_type' => $version?->mime_type,
                'size' => $version?->size,
                'url' => $version?->url,
                'download_url' => route('evidence.download', $evidence),
                'show_url' => route('evidence.show', $evidence),
                'updated_at' => $evidence->updated_at?->toIso8601String(),
                'can_unlink' => ! $locked && ($mapping->created_by === $user->id || $user->can('evidence.verify')),
            ];
        }

        return $items;
    }

    public static function morphAlias(string $class): string
    {
        return array_search($class, Relation::morphMap(), true) ?: $class;
    }
}
