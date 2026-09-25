<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationPeriod;
use App\Models\ActionPlan;
use App\Models\Audit;
use App\Models\CorrectiveAction;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\Survey;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Date::use(CarbonImmutable::class);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8));

        Relation::morphMap([
            'evidence' => Evidence::class,
            'finding' => Finding::class,
            'corrective_action' => CorrectiveAction::class,
            'recommendation' => Recommendation::class,
            'action_plan' => ActionPlan::class,
            'audit' => Audit::class,
            'survey' => Survey::class,
            'accreditation_indicator' => AccreditationIndicator::class,
            'accreditation_period' => AccreditationPeriod::class,
        ]);

        Gate::before(fn (User $user): ?bool => $user->hasRole(UserRole::Superadmin->value) ? true : null);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('login')).'|'.$request->ip()),
        ));

        $this->registerAuthAuditTrail();

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $status = $response->statusCode();
            $renderable = $this->app->isProduction() ? [403, 404, 500, 503] : [403, 404];

            if (in_array($status, $renderable, true)) {
                return $response->render('error', ['status' => $status])->withSharedData();
            }

            return null;
        });
    }

    /**
     * Catat login, logout, dan percobaan login gagal ke log audit.
     */
    private function registerAuthAuditTrail(): void
    {
        Event::listen(function (Login $event): void {
            AuditLogger::log('login', 'auth', $event->user, 'Masuk ke sistem', userId: $event->user->getAuthIdentifier());
        });

        Event::listen(function (Logout $event): void {
            if ($event->user) {
                AuditLogger::log('logout', 'auth', $event->user, 'Keluar dari sistem', userId: $event->user->getAuthIdentifier());
            }
        });

        Event::listen(function (Failed $event): void {
            AuditLogger::log('login_failed', 'auth', null, 'Percobaan masuk gagal', new: [
                'login' => $event->credentials['email'] ?? $event->credentials['username'] ?? null,
            ]);
        });
    }
}
