<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function edit(): Response
    {
        $institution = Institution::current() ?? new Institution;

        return Inertia::render('master/institution', [
            'institution' => [
                ...$institution->only(['name', 'short_name', 'address', 'email', 'phone', 'website', 'rector_name', 'lpm_head_name']),
                'logo_url' => $institution->logo_path ? route('branding.logo').'?v='.$institution->updated_at?->timestamp : null,
            ],
            'stats' => [
                'faculties' => $institution->exists ? $institution->faculties()->count() : 0,
                'units' => $institution->exists ? $institution->units()->count() : 0,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'rector_name' => ['nullable', 'string', 'max:255'],
            'lpm_head_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $institution = Institution::current() ?? new Institution;
        unset($validated['logo']);
        $institution->fill($validated);

        if ($request->hasFile('logo')) {
            if ($institution->logo_path) {
                Storage::disk('public')->delete($institution->logo_path);
            }
            $institution->logo_path = $request->file('logo')->store('branding', 'public');
        }

        $institution->save();
        Cache::forget('app.branding');

        $this->toast('Profil institusi berhasil disimpan.');

        return back();
    }
}
