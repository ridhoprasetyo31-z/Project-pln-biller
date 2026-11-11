<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Batas percobaan login per menit (PRD: 5/menit). */
    private const MAX_PERCOBAAN = 5;

    public function authorize(): bool
    {
        return true;
    }

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
        return [
            'login' => 'NIP atau email',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Login memakai NIP (angka) atau email. Akun nonaktif ditolak.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $kolom = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'nip';

        $kredensial = [
            $kolom => $kolom === 'email' ? Str::lower($login) : $login,
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::validate($kredensial)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'login' => 'NIP/email atau kata sandi tidak sesuai.',
            ]);
        }

        $user = Auth::getProvider()->retrieveByCredentials($kredensial);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda dinonaktifkan. Hubungi admin unit.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login')).'|'.$this->ip());
    }
}
