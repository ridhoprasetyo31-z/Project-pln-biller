<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Penugasan;
use App\Models\Rute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah atau ubah penugasan (rute → biller) dalam satu periode.
 */
class PenugasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $penugasan = $this->route('penugasan');
        if ($penugasan) {
            return $this->user()->can('update', $penugasan);
        }

        return $this->user()->can('create', Penugasan::class) && $this->route('periode')->bisaDiubah();
    }

    public function rules(): array
    {
        $penugasan = $this->route('penugasan');
        $ruteId = $penugasan?->rute_id ?? $this->integer('rute_id');
        $unitId = (int) Rute::whereKey($ruteId)->value('unit_id');

        return [
            'rute_id' => $penugasan ? ['prohibited'] : [
                'required', 'integer', 'exists:rute,id',
                Rule::unique('penugasan', 'rute_id')->where('periode_id', $this->route('periode')->id),
            ],
            'biller_id' => ['required', 'integer', RuteRequest::billerDiUnit($unitId)],
            'target_pelanggan' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function attributes(): array
    {
        return ['rute_id' => 'rute', 'biller_id' => 'biller', 'target_pelanggan' => 'target pelanggan'];
    }

    public function messages(): array
    {
        return [
            'rute_id.unique' => 'Rute ini sudah punya penugasan di periode ini.',
            'biller_id.exists' => 'Biller harus biller aktif dari unit yang sama dengan rute.',
        ];
    }
}
