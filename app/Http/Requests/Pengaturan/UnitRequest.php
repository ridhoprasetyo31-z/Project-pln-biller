<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\JenisUnit;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit ? $this->user()->can('update', $unit) : $this->user()->can('create', Unit::class);
    }

    public function rules(): array
    {
        $id = $this->route('unit')?->id;

        return [
            'kode' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('units', 'kode')->ignore($id)],
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::enum(JenisUnit::class)],
            'parent_id' => [
                'required_if:jenis,ulp',
                'prohibited_if:jenis,up3',
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('jenis', JenisUnit::Up3->value),
            ],
            'alamat' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['parent_id' => 'UP3 induk', 'jenis' => 'jenis unit'];
    }

    public function messages(): array
    {
        return [
            'parent_id.required_if' => 'ULP wajib memilih UP3 induk.',
            'parent_id.prohibited_if' => 'UP3 tidak memiliki unit induk.',
            'parent_id.exists' => 'Unit induk harus berupa UP3.',
        ];
    }
}
