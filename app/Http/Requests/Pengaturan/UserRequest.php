<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user ? $this->user()->can('update', $user) : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'nip' => ['required', 'digits:18', Rule::unique('users', 'nip')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'role' => ['required', Rule::enum(Role::class)],
            'unit_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== Role::SuperAdmin->value),
                'nullable',
                'integer',
                'exists:units,id',
            ],
            'password' => [$id ? 'nullable' : 'required', 'string', Password::min(8)],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nip' => 'NIP',
            'role' => 'peran',
            'unit_id' => 'unit',
            'is_active' => 'status aktif',
        ];
    }

    public function messages(): array
    {
        return [
            'nip.digits' => 'NIP harus 18 digit angka.',
            'phone.regex' => 'Nomor HP harus diawali 08 atau +62 dan berisi 10 sampai 15 digit.',
            'unit_id.required' => 'Unit wajib dipilih untuk biller dan koordinator.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\-]/', '', (string) $this->input('phone'));

        $this->merge([
            'nip' => preg_replace('/\s+/', '', (string) $this->input('nip')),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $phone === '' ? null : $phone,
            'password' => $this->filled('password') ? $this->input('password') : null,
        ]);
    }

    /** Super admin tidak boleh menonaktifkan atau menurunkan perannya sendiri. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                if (! $target || $target->isNot($this->user())) {
                    return;
                }
                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Anda tidak bisa menonaktifkan akun sendiri.');
                }
                if ($this->input('role') !== Role::SuperAdmin->value) {
                    $validator->errors()->add('role', 'Anda tidak bisa mengubah peran akun sendiri.');
                }
            },
        ];
    }
}
