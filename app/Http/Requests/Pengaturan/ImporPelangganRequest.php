<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;

class ImporPelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Pelanggan::class);
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return ['unit_id' => 'unit', 'file' => 'berkas Excel'];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
        ];
    }
}
