<?php

namespace App\Http\Requests;

use App\Enums\JenisPdil;
use App\Enums\JenisPelanggan;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Policies\PdilPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PdilRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pdil = $this->route('pdil');
        if ($pdil) {
            return $this->user()->can('update', $pdil);
        }

        $pelanggan = $this->input('jenis') === JenisPdil::PerubahanData->value
            ? Pelanggan::find($this->integer('pelanggan_id'))
            : null;

        return $this->user()->can('create', [Pdil::class, $pelanggan, $pelanggan ? null : $this->integer('rute_id')]);
    }

    public function rules(): array
    {
        /** @var Pdil|null $pdil */
        $pdil = $this->route('pdil');
        $jenis = $pdil?->jenis->value ?? $this->input('jenis');
        $pelangganId = $pdil?->pelanggan_id ?? ($jenis === 'perubahan_data' ? $this->integer('pelanggan_id') : null);
        $adaFoto = fn (string $j) => $pdil?->lampiran()->where('jenis', $j)->exists() ?? false;

        return [
            'jenis' => $pdil ? ['prohibited'] : ['required', Rule::enum(JenisPdil::class)],
            'pelanggan_id' => $pdil ? ['prohibited'] : ['nullable', 'required_if:jenis,perubahan_data', 'integer', 'exists:pelanggan,id'],
            'rute_id' => $pdil ? ['nullable'] : ['nullable', 'required_if:jenis,pelanggan_baru', 'integer', Rule::in(PdilPolicy::ruteBiller($this->user()))],
            'nama' => ['required', 'string', 'max:100'],
            'alamat' => ['required', 'string', 'max:255'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'jenis_pelanggan' => ['required', Rule::enum(JenisPelanggan::class)],
            'golongan_tarif' => ['required', 'string', 'max:10', Rule::exists('tarif', 'golongan')],
            'daya_va' => ['required', 'integer', 'min:450', 'max:5000000'],
            'nomor_meter' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('pelanggan', 'nomor_meter')->ignore($pelangganId)],
            'letak_meter' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-11,6', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:95,141', 'required_with:latitude'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'foto_rumah' => [$adaFoto('rumah') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_meter' => [$adaFoto('meter') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_rumah_diambil_at' => ['nullable', 'date'],
            'foto_meter_diambil_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rute_id' => 'rute',
            'jenis_pelanggan' => 'jenis pelanggan',
            'golongan_tarif' => 'golongan tarif',
            'daya_va' => 'daya',
            'nomor_meter' => 'nomor meter',
            'foto_rumah' => 'foto rumah',
            'foto_meter' => 'foto meter',
        ];
    }

    public function messages(): array
    {
        return [
            'rute_id.in' => 'Pilih salah satu rute Anda.',
            'nomor_meter.unique' => 'Nomor meter ini sudah dipakai pelanggan lain.',
            'golongan_tarif.exists' => 'Golongan tarif belum terdaftar.',
            'latitude.between' => 'Titik lokasi di luar wilayah Indonesia.',
            'longitude.between' => 'Titik lokasi di luar wilayah Indonesia.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'golongan_tarif' => strtoupper(trim((string) $this->input('golongan_tarif'))),
            'nomor_meter' => preg_replace('/\s+/', '', (string) $this->input('nomor_meter')),
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
        ]);
    }
}
