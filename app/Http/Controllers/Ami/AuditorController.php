<?php

namespace App\Http\Controllers\Ami;

use App\Enums\AuditStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Auditor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditorController extends Controller
{
    public function index(): Response
    {
        $auditors = Auditor::query()
            ->with('user:id,name,email,username')
            ->withCount(['audits', 'audits as active_audits_count' => fn ($q) => $q->whereNotIn('status', [AuditStatus::Completed, AuditStatus::Cancelled])])
            ->get()
            ->sortBy('user.name')
            ->values()
            ->map(fn (Auditor $auditor): array => [
                ...$auditor->only(['id', 'user_id', 'certificate_number', 'certification', 'competencies', 'is_active', 'audits_count', 'active_audits_count']),
                'certified_on' => $auditor->certified_on?->toDateString(),
                'name' => $auditor->user->name,
                'email' => $auditor->user->email,
            ]);

        return Inertia::render('ami/auditors', [
            'auditors' => $auditors->all(),
            'users' => User::query()->where('is_active', true)->whereDoesntHave('auditor')->whereDoesntHave('roles', fn ($q) => $q->where('name', UserRole::Mahasiswa->value))
                ->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => "{$user->name} ({$user->email})"])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $auditor = Auditor::query()->create($validated);
        $auditor->user->assignRole(UserRole::Auditor->value);

        $this->toast("{$auditor->user->name} ditetapkan sebagai auditor.");

        return back();
    }

    public function update(Request $request, Auditor $auditor): RedirectResponse
    {
        $auditor->update($this->validated($request, $auditor));
        $this->toast('Data auditor diperbarui.');

        return back();
    }

    public function destroy(Auditor $auditor): RedirectResponse
    {
        if ($auditor->audits()->exists()) {
            $auditor->update(['is_active' => false]);
            $this->toast('Auditor memiliki riwayat audit sehingga dinonaktifkan.', 'info');

            return back();
        }

        $auditor->user->removeRole(UserRole::Auditor->value);
        $auditor->delete();
        $this->toast('Auditor dihapus.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Auditor $auditor = null): array
    {
        return $request->validate([
            'user_id' => [$auditor ? 'sometimes' : 'required', 'exists:users,id', Rule::unique('auditors')->ignore($auditor)],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'certification' => ['nullable', 'string', 'max:255'],
            'certified_on' => ['nullable', 'date'],
            'competencies' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
    }
}
