<?php

namespace App\Http\Requests\Auth;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => 'email / NIM / NIDN / NUPTK', 'password' => 'kata sandi'];
    }

    /**
     * Autentikasi menggunakan email atau username (NIM/NIDN/NUPTK).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $login = trim((string) $this->string('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Dosen boleh masuk dengan NIDN maupun NUPTK, apa pun yang menjadi username akunnya.
        if ($field === 'username' && ctype_digit($login) && ! User::query()->where('username', $login)->exists()) {
            $login = Lecturer::query()->identifiedBy($login)->first()?->user?->username ?? $login;
        }

        $credentials = [$field => $login, 'password' => (string) $this->string('password')];

        if (! Auth::attempt([...$credentials, 'is_active' => true], $this->boolean('remember'))) {
            $inactive = User::query()->where($field, $login)->where('is_active', false)->exists();

            throw ValidationException::withMessages([
                'login' => $inactive
                    ? 'Akun Anda dinonaktifkan. Hubungi admin LPM.'
                    : 'Email/NIM/NIDN/NUPTK atau kata sandi tidak sesuai.',
            ]);
        }
    }
}
