<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Pengaturan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class KpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', new Pengaturan);
    }

    public function rules(): array
    {
        return [
            'bobot_kpi' => ['required', 'array:akurasi,penyelesaian,tepat_waktu,bukti_lengkap'],
            'bobot_kpi.akurasi' => ['required', 'integer', 'between:0,100'],
            'bobot_kpi.penyelesaian' => ['required', 'integer', 'between:0,100'],
            'bobot_kpi.tepat_waktu' => ['required', 'integer', 'between:0,100'],
            'bobot_kpi.bukti_lengkap' => ['required', 'integer', 'between:0,100'],
            'ambang_lonjakan_persen' => ['required', 'integer', 'between:101,1000'],
            'ambang_jarak_m' => ['required', 'integer', 'between:10,5000'],
            'ambang_pembinaan' => ['required', 'integer', 'between:0,100'],
            'ambang_dipantau' => ['required', 'integer', 'between:0,100', 'gte:ambang_pembinaan'],
            'min_lembar_tusbug' => ['required', 'integer', 'between:1,24'],
            'ppj_persen' => ['required', 'numeric', 'between:0,20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bobot_kpi.akurasi' => 'bobot akurasi',
            'bobot_kpi.penyelesaian' => 'bobot penyelesaian',
            'bobot_kpi.tepat_waktu' => 'bobot tepat waktu',
            'bobot_kpi.bukti_lengkap' => 'bobot bukti lengkap',
            'ambang_lonjakan_persen' => 'ambang lonjakan',
            'ambang_jarak_m' => 'ambang jarak',
            'ambang_pembinaan' => 'ambang pembinaan',
            'ambang_dipantau' => 'ambang dipantau',
            'min_lembar_tusbug' => 'minimal lembar TUSBUG',
            'ppj_persen' => 'PPJ',
        ];
    }

    public function messages(): array
    {
        return [
            'ambang_dipantau.gte' => 'Ambang dipantau harus lebih besar atau sama dengan ambang pembinaan.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $total = array_sum(array_map('intval', (array) $this->input('bobot_kpi', [])));
                if ($total !== 100) {
                    $validator->errors()->add('bobot_kpi', "Total bobot harus 100%, sekarang {$total}%.");
                }
            },
        ];
    }
}
