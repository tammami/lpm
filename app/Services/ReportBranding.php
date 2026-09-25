<?php

namespace App\Services;

use App\Models\Institution;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas kampus untuk kop laporan PDF.
 */
class ReportBranding
{
    /**
     * @return array{name: string, short_name: string, address: string|null, email: string|null, phone: string|null, website: string|null, rector: string|null, lpm_head: string|null, city: string, logo: string}
     */
    public static function data(): array
    {
        $institution = Institution::current();
        $logo = null;

        if ($institution?->logo_path && Storage::disk('public')->exists($institution->logo_path)) {
            $mime = Storage::disk('public')->mimeType($institution->logo_path);
            $logo = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($institution->logo_path));
        }

        return [
            'name' => $institution?->name ?? 'IAIA NU Lombok Timur',
            'short_name' => $institution?->short_name ?? 'IAIA NU',
            'address' => $institution?->address,
            'email' => $institution?->email,
            'phone' => $institution?->phone,
            'website' => $institution?->website,
            'rector' => $institution?->rector_name,
            'lpm_head' => $institution?->lpm_head_name,
            'city' => (string) Settings::get('report.signature_city', 'Lombok Timur'),
            'logo' => $logo ?? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-iaia.png'))),
        ];
    }
}
