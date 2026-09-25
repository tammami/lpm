<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Arahkan pengguna ke beranda sesuai perannya.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->must_change_password) {
            return redirect()->route('profile.password');
        }

        if ($user->primaryRole() === UserRole::Mahasiswa) {
            return redirect()->route('portal.home');
        }

        return redirect()->route('dashboard');
    }
}
