<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_foto' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'phone' => 'nomor HP',
            'foto' => 'foto profil',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor HP harus diawali 08 atau +62 dan berisi 10 sampai 15 digit.',
            'foto.max' => 'Ukuran foto profil maksimal 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\-]/', '', (string) $this->input('phone'));

        $this->merge(['phone' => $phone === '' ? null : $phone]);
    }
}
