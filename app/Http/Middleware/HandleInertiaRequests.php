<?php

namespace App\Http\Middleware;

use App\Models\AcademicPeriod;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => fn (): array => Cache::remember('app.branding', 3600, function (): array {
                $institution = Institution::current();

                return [
                    'name' => config('app.name'),
                    'institution' => $institution?->name ?? 'IAIA NU Lombok Timur',
                    'institution_short' => $institution?->short_name ?? 'IAIA NU',
                    'logo_url' => $institution?->logo_path ? route('branding.logo') : null,
                ];
            }),
            'auth' => [
                'user' => $user ? fn (): array => $this->userPayload($user) : null,
            ],
            'activePeriod' => fn (): ?array => $user
                ? AcademicPeriod::active()?->only(['id', 'name', 'code'])
                : null,
            'unreadNotifications' => fn (): int => $user ? $user->unreadNotifications()->count() : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $role = $user->primaryRole();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'initials' => Str::of($user->name)->explode(' ')->filter()->take(2)
                ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode(''),
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'roles' => $user->getRoleNames()->all(),
            'scope_label' => $user->scopeLabel(),
            'permissions' => $user->hasRole('superadmin')
                ? ['*']
                : $user->getAllPermissions()->pluck('name')->all(),
            'must_change_password' => $user->must_change_password,
            'is_lecturer' => $user->lecturer()->exists(),
            'is_student' => $user->student()->exists(),
        ];
    }
}
