<?php

namespace App\Http\Requests;

use App\Enums\PenyebabKoreksi;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KoreksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $koreksi = $this->route('koreksi');
        if ($koreksi) {
            return $this->user()->can('update', $koreksi);
        }

        $invoice = $this->invoice();

        return $invoice && $this->user()->can('create', [KoreksiRekening::class, $invoice]);
    }

    public function invoice(): ?Invoice
    {
        $koreksi = $this->route('koreksi');

        return $koreksi ? $koreksi->invoice()->first() : Invoice::find($this->integer('invoice_id'));
    }

    public function rules(): array
    {
        $adaFoto = $this->route('koreksi')?->lampiran()->where('jenis', 'bukti')->exists() ?? false;

        return [
            'invoice_id' => $this->route('koreksi') ? ['prohibited'] : ['required', 'integer', 'exists:invoice,id'],
            'stand_baru' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,1'],
            'penyebab' => ['required', Rule::enum(PenyebabKoreksi::class)],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
            'foto' => [$adaFoto ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_diambil_at' => ['nullable', 'date'],
        ];
    }

    /** Stand baru tidak boleh di bawah stand lalu dan harus berbeda dari stand lama. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $catatan = $this->invoice()?->pencatatanMeter()->first();
                if (! $catatan || $validator->errors()->has('stand_baru')) {
                    return;
                }
                $baru = (float) $this->input('stand_baru');
                if ($catatan->stand_lalu !== null && $baru < $catatan->stand_lalu) {
                    $validator->errors()->add('stand_baru', 'Stand baru tidak boleh lebih kecil dari stand lalu ('.number_format($catatan->stand_lalu, 1, ',', '.').').');
                }
                if (abs($baru - (float) $catatan->stand_kini) < 0.05) {
                    $validator->errors()->add('stand_baru', 'Stand baru sama dengan stand yang tercatat; tidak ada yang dikoreksi.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['stand_baru' => 'stand yang benar', 'penyebab' => 'penyebab', 'alasan' => 'penjelasan', 'foto' => 'foto bukti'];
    }

    public function messages(): array
    {
        return [
            'stand_baru.decimal' => 'Stand maksimal satu angka di belakang koma.',
            'alasan.min' => 'Jelaskan koreksi minimal 10 karakter.',
            'foto.required' => 'Lampirkan foto meter terbaru sebagai bukti.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nilai = str_replace(' ', '', (string) $this->input('stand_baru'));
        if (str_contains($nilai, ',')) {
            $nilai = str_replace(['.', ','], ['', '.'], $nilai);
        }
        $this->merge(['stand_baru' => $nilai === '' ? null : $nilai]);
    }
}
