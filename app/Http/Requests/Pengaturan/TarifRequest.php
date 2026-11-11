<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Tarif;
use Illuminate\Foundation\Http\FormRequest;

class TarifRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tarif = $this->route('tarif');

        return $tarif ? $this->user()->can('update', $tarif) : $this->user()->can('create', Tarif::class);
    }

    public function rules(): array
    {
        return [
            'golongan' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-\/]+$/'],
            'daya_min_va' => ['required', 'integer', 'min:0'],
            'daya_max_va' => ['required', 'integer', 'gte:daya_min_va'],
            'harga_per_kwh' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'biaya_beban' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'berlaku_mulai' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'daya_min_va' => 'daya minimum',
            'daya_max_va' => 'daya maksimum',
            'harga_per_kwh' => 'harga per kWh',
            'biaya_beban' => 'biaya beban',
            'berlaku_mulai' => 'tanggal berlaku',
            'berlaku_sampai' => 'tanggal berakhir',
        ];
    }

    public function messages(): array
    {
        return [
            'golongan.regex' => 'Golongan hanya boleh huruf besar dan angka, contoh R1.',
            'daya_max_va.gte' => 'Daya maksimum harus lebih besar atau sama dengan daya minimum.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'golongan' => strtoupper(trim((string) $this->input('golongan'))),
            'berlaku_sampai' => $this->filled('berlaku_sampai') ? $this->input('berlaku_sampai') : null,
        ]);
    }
}
