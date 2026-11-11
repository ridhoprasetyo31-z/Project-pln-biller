<?php

namespace App\Http\Requests;

use App\Enums\JenisLampiran;
use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Periode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatatMeterRequest extends FormRequest
{
    /** Kondisi pascabayar yang membuat stand tidak bisa dibaca. */
    private const TANPA_STAND = ['meter_rusak', 'akses_tertutup'];

    public function authorize(): bool
    {
        return $this->user()->can('catat', [PencatatanMeter::class, $this->pelanggan()]);
    }

    public function pelanggan(): Pelanggan
    {
        return $this->route('pelanggan');
    }

    public function kirim(): bool
    {
        return $this->input('aksi') === 'kirim';
    }

    public function rules(): array
    {
        $pasca = $this->pelanggan()->jenis === JenisPelanggan::Pascabayar;
        $kondisi = $pasca ? KondisiMeter::untukPascabayar() : KondisiMeter::untukPrabayar();
        $tanpaStand = in_array($this->input('kondisi_meter'), self::TANPA_STAND, true);

        $rules = [
            'aksi' => ['required', 'in:draf,kirim'],
            'kondisi_meter' => ['required', Rule::in(array_map(fn ($k) => $k->value, $kondisi))],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_diambil_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];

        if ($pasca) {
            $rules['stand_kini'] = [
                Rule::requiredIf($this->kirim() && ! $tanpaStand),
                'nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,1',
            ];
            $rules['keterangan'] = [
                Rule::requiredIf($this->kirim() && $tanpaStand && ! $this->filled('stand_kini')),
                'nullable', 'string', 'max:500',
            ];
        } else {
            $rules['sisa_kwh'] = [Rule::requiredIf($this->kirim()), 'nullable', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'];
            $rules['keterangan'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'stand_kini' => 'stand kini',
            'sisa_kwh' => 'sisa kWh',
            'kondisi_meter' => 'kondisi meter',
            'foto' => 'foto meter',
        ];
    }

    public function messages(): array
    {
        return [
            'stand_kini.required' => 'Stand kini wajib diisi sebelum dikirim.',
            'stand_kini.decimal' => 'Stand kini maksimal satu angka di belakang koma.',
            'sisa_kwh.required' => 'Sisa kWh wajib diisi sebelum dikirim.',
            'keterangan.required' => 'Jelaskan kenapa stand tidak bisa dibaca.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }

    /** Foto wajib saat dikirim: unggahan baru atau foto yang sudah tersimpan di draf. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->kirim() || $this->hasFile('foto')) {
                    return;
                }

                $periode = Periode::berjalan();
                $adaFoto = $periode && PencatatanMeter::query()
                    ->where('periode_id', $periode->id)
                    ->where('pelanggan_id', $this->pelanggan()->id)
                    ->whereHas('lampiran', fn ($q) => $q->whereIn('jenis', [JenisLampiran::Meter, JenisLampiran::LayarMeter]))
                    ->exists();

                if (! $adaFoto) {
                    $validator->errors()->add('foto', 'Ambil foto meter sebelum mengirim catatan.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['stand_kini', 'sisa_kwh'] as $field) {
            if ($this->has($field)) {
                $nilai = str_replace(' ', '', (string) $this->input($field));
                // Format Indonesia "18.912,4" → "18912.4"; format "18912.4" dibiarkan.
                if (str_contains($nilai, ',')) {
                    $nilai = str_replace(['.', ','], ['', '.'], $nilai);
                }
                $this->merge([$field => $nilai === '' ? null : $nilai]);
            }
        }
    }
}
