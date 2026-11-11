<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Periode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $periode = $this->route('periode');

        return $periode ? $this->user()->can('update', $periode) : $this->user()->can('create', Periode::class);
    }

    public function rules(): array
    {
        return [
            // Bulan hanya dipilih saat membuat; kode periode tidak bisa diganti.
            'bulan' => $this->route('periode') ? ['prohibited'] : [
                'required',
                'date_format:Y-m',
                function (string $attribute, mixed $value, Closure $fail) {
                    $kode = str_replace('-', '', (string) $value);
                    if (Periode::where('kode', $kode)->exists()) {
                        $fail("Periode {$kode} sudah ada.");
                    }
                },
            ],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bulan' => 'bulan periode',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'batas pencatatan',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'Batas pencatatan tidak boleh sebelum tanggal mulai.',
        ];
    }

    /** "2026-11" → "202611". */
    public function kode(): string
    {
        return str_replace('-', '', (string) $this->validated('bulan'));
    }
}
