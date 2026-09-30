<?php

use App\Enums\UserRole;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a reset link and lets the user choose a new password', function () {
    Notification::fake();
    $user = userWithRole(UserRole::AdminLpm, ['email' => 'lpm@simutu.test']);

    $this->post(route('password.email'), ['email' => 'lpm@simutu.test'])->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'KataSandiBaru#2026',
        'password_confirmation' => 'KataSandiBaru#2026',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Hash::check('KataSandiBaru#2026', $user->fresh()->password))->toBeTrue();
});

it('does not reveal whether an email is registered', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'tidak.ada@simutu.test'])->assertSessionHas('status');

    Notification::assertNothingSent();
});
