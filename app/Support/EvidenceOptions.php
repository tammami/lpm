<?php

namespace App\Support;

use App\Models\EvidenceCategory;
use App\Services\Evidence\EvidenceService;
use App\Services\Settings;
use Illuminate\Http\Request;

/**
 * Opsi formulir unggah dokumen yang dipakai di berbagai halaman (temuan, rencana aksi, akreditasi).
 */
final class EvidenceOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Request $request): array
    {
        return [
            'categories' => EvidenceCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (EvidenceCategory $category): array => ['value' => $category->id, 'label' => $category->name])->all(),
            'units' => Auditee::options($request->user()),
            'periods' => Options::periods(),
            'maxUploadMb' => UploadLimit::megabytes((int) Settings::get('evidence.max_upload_mb', 20)),
            'allowedExtensions' => EvidenceService::ALLOWED_EXTENSIONS,
        ];
    }
}
