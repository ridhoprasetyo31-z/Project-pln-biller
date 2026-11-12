<?php

namespace App\Http\Requests;

use App\Enums\HasilKunjungan;
use App\Models\KunjunganPenagihan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KunjunganRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kunjungan = $this->route('kunjungan');

        return $kunjungan
            ? $this->user()->can('update', $kunjungan)
            : $this->user()->can('create', [KunjunganPenagihan::class, $this->route('invoice')]);
    }

    public function rules(): array
    {
        $adaFoto = $this->route('kunjungan')?->lampiran()->where('jenis', 'bukti')->exists() ?? false;

        return [
            'hasil' => ['required', Rule::enum(HasilKunjungan::class)],
            'tanggal_janji' => ['nullable', 'required_if:hasil,janji_bayar', 'date', 'after_or_equal:today'],
            'nominal_dibayar' => ['nullable', 'required_if:hasil,lunas', 'numeric', 'min:1', 'max:999999999'],
            'keterangan' => ['nullable', 'required_if:hasil,menolak', 'string', 'max:500'],
            'foto' => [$adaFoto ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_diambil_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function attributes(): array
    {
        return [
            'hasil' => 'hasil kunjungan',
            'tanggal_janji' => 'tanggal janji bayar',
            'nominal_dibayar' => 'nominal dibayar',
            'foto' => 'foto bukti',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_janji.required_if' => 'Isi tanggal pelanggan berjanji membayar.',
            'tanggal_janji.after_or_equal' => 'Tanggal janji tidak boleh sebelum hari ini.',
            'nominal_dibayar.required_if' => 'Isi nominal yang dibayar pelanggan.',
            'keterangan.required_if' => 'Tulis alasan pelanggan menolak membayar.',
            'foto.required' => 'Ambil foto bukti kunjungan (kuitansi, rumah pelanggan, atau meter).',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('nominal_dibayar')) {
            $this->merge(['nominal_dibayar' => preg_replace('/[^\d]/', '', (string) $this->input('nominal_dibayar'))]);
        }
    }
}
