<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'kata sandi lama',
            'password' => 'kata sandi baru',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Kata sandi lama tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
        ];
    }
}
