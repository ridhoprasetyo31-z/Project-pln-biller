<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\Role;
use App\Models\Rute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class RuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rute = $this->route('rute');

        return $rute ? $this->user()->can('update', $rute) : $this->user()->can('create', Rute::class);
    }

    public function rules(): array
    {
        $id = $this->route('rute')?->id;
        $unitId = $this->integer('unit_id');

        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'kode' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('rute', 'kode')->where('unit_id', $unitId)->ignore($id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'biller_id' => ['nullable', 'integer', self::billerDiUnit($unitId)],
            'is_active' => ['boolean'],
        ];
    }

    /** Biller pemegang harus biller aktif di unit yang sama dengan rute. */
    public static function billerDiUnit(int $unitId): Exists
    {
        return Rule::exists('users', 'id')
            ->where('role', Role::Biller->value)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }

    public function attributes(): array
    {
        return ['unit_id' => 'unit', 'biller_id' => 'biller pemegang', 'kode' => 'kode rute'];
    }

    public function messages(): array
    {
        return [
            'kode.unique' => 'Kode rute sudah dipakai di unit ini.',
            'biller_id.exists' => 'Biller harus biller aktif dari unit yang sama dengan rute.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['kode' => strtoupper(trim((string) $this->input('kode')))]);
    }
}
