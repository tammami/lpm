<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $values = Settings::all();

        return Inertia::render('system/settings', [
            'settings' => collect(Settings::definitions())->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
                'help' => $definition['help'] ?? null,
                'type' => $definition['type'],
                'group' => $definition['group'],
                'value' => $values[$key] ?? $definition['value'],
                'default' => $definition['value'],
            ])->values()->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = Settings::definitions();
        $rules = collect($definitions)->mapWithKeys(fn (array $definition, string $key): array => [
            'values.'.str_replace('.', '__', $key) => match ($definition['type']) {
                'number' => ['required', 'integer', 'min:0', 'max:100000'],
                'decimal' => ['required', 'numeric', 'min:0', 'max:100'],
                'boolean' => ['required', 'boolean'],
                default => ['required', 'string', 'max:255'],
            },
        ])->all();

        $validated = $request->validate($rules);
        $before = Settings::all();

        foreach ($validated['values'] as $key => $value) {
            $settingKey = str_replace('__', '.', $key);
            $type = $definitions[$settingKey]['type'];
            Settings::set($settingKey, match ($type) {
                'number' => (int) $value,
                'decimal' => (float) $value,
                'boolean' => (bool) $value,
                default => (string) $value,
            });
        }

        AuditLogger::log('updated', 'settings', null, 'Memperbarui konfigurasi sistem', $before, Settings::all());
        $this->toast('Konfigurasi disimpan.');

        return back();
    }
}
