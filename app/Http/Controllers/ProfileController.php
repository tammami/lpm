<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['lecturer.studyProgram', 'student.studyProgram']);

        return Inertia::render('profile/edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($validated);
        AuditLogger::updated($user, 'profile', 'Memperbarui profil');

        $this->toast('Profil berhasil diperbarui.');

        return back();
    }

    public function password(Request $request): Response
    {
        return Inertia::render('profile/password', [
            'mustChange' => $request->user()->must_change_password,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        AuditLogger::log('password_changed', 'auth', $request->user(), 'Mengganti kata sandi');

        $this->toast('Kata sandi berhasil diganti.');

        return redirect()->route('home');
    }
}
