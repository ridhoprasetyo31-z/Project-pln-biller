<?php

namespace App\Http\Requests\Pengaturan;

use App\Enums\JenisPelanggan;
use App\Enums\StatusPelanggan;
use App\Models\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pelanggan = $this->route('pelanggan');

        return $pelanggan ? $this->user()->can('update', $pelanggan) : $this->user()->can('create', Pelanggan::class);
    }

    public function rules(): array
    {
        return self::aturan($this->route('pelanggan')?->id);
    }

    /**
     * Aturan bersama untuk form dan impor Excel.
     *
     * @return array<string, mixed>
     */
    public static function aturan(?int $ignoreId = null): array
    {
        return [
            'idpel' => ['required', 'digits:12', Rule::unique('pelanggan', 'idpel')->ignore($ignoreId)],
            'rute_id' => ['required', 'integer', 'exists:rute,id'],
            'urutan_rute' => ['required', 'integer', 'min:1', 'max:99999'],
            'nama' => ['required', 'string', 'max:100'],
            'alamat' => ['required', 'string', 'max:255'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::enum(JenisPelanggan::class)],
            'golongan_tarif' => ['required', 'string', 'max:10', Rule::exists('tarif', 'golongan')],
            'daya_va' => ['required', 'integer', 'min:450', 'max:5000000'],
            'nomor_meter' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('pelanggan', 'nomor_meter')->ignore($ignoreId)],
            'letak_meter' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-11,6', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:95,141', 'required_with:latitude'],
            'status' => ['required', Rule::enum(StatusPelanggan::class)],
        ];
    }

    public static function atribut(): array
    {
        return [
            'idpel' => 'ID pelanggan',
            'rute_id' => 'rute',
            'urutan_rute' => 'urutan rute',
            'jenis' => 'jenis pelanggan',
            'golongan_tarif' => 'golongan tarif',
            'daya_va' => 'daya',
            'nomor_meter' => 'nomor meter',
            'letak_meter' => 'letak meter',
        ];
    }

    public static function pesan(): array
    {
        return [
            'idpel.digits' => 'ID pelanggan harus 12 digit angka.',
            'golongan_tarif.exists' => 'Golongan tarif belum terdaftar di menu Tarif.',
            'latitude.between' => 'Latitude di luar wilayah Indonesia.',
            'longitude.between' => 'Longitude di luar wilayah Indonesia.',
        ];
    }

    public function attributes(): array
    {
        return self::atribut();
    }

    public function messages(): array
    {
        return self::pesan();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'golongan_tarif' => strtoupper(trim((string) $this->input('golongan_tarif'))),
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
        ]);
    }
}
