<?php

namespace App\Jobs;

use App\Http\Requests\Pengaturan\PelangganRequest;
use App\Imports\PelangganImport;
use App\Models\Pelanggan;
use App\Models\Rute;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Impor pelanggan dari Excel untuk satu unit.
 * Semua baris divalidasi lebih dulu; bila ada satu saja yang salah,
 * tidak ada data yang disimpan (all-or-nothing). Pelanggan dicocokkan lewat idpel.
 */
class ImporPelanggan implements ShouldQueue
{
    use Queueable;

    private const MAKS_GALAT = 50;

    public function __construct(
        public string $path,
        public int $unitId,
        public string $disk = 'local',
    ) {}

    /**
     * @return array{total: int, dibuat: int, diperbarui: int, errors: list<string>}
     */
    public function handle(): array
    {
        $import = new PelangganImport;
        Excel::import($import, $this->path, $this->disk);
        Storage::disk($this->disk)->delete($this->path);

        $rows = $import->rows ?? collect();
        $rute = Rute::where('unit_id', $this->unitId)->pluck('id', 'kode');
        $hasil = ['total' => $rows->count(), 'dibuat' => 0, 'diperbarui' => 0, 'errors' => []];

        if ($rows->isEmpty()) {
            $hasil['errors'][] = 'Berkas tidak berisi data. Gunakan template impor dan isi mulai baris ke-2.';

            return $hasil;
        }

        $siap = [];
        $idpelDiBerkas = [];
        $meterDiBerkas = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2; // baris 1 = judul kolom
            $data = $this->normalisasi($row->toArray());

            $ruteId = $rute[strtoupper($data['kode_rute'])] ?? null;
            if (! $ruteId) {
                $this->galat($hasil, $baris, "kode rute \"{$data['kode_rute']}\" tidak ada di unit ini.");

                continue;
            }
            $data['rute_id'] = $ruteId;
            unset($data['kode_rute']);

            if (isset($idpelDiBerkas[$data['idpel']])) {
                $this->galat($hasil, $baris, "ID pelanggan {$data['idpel']} ganda dengan baris {$idpelDiBerkas[$data['idpel']]}.");

                continue;
            }
            $idpelDiBerkas[$data['idpel']] = $baris;

            if (isset($meterDiBerkas[$data['nomor_meter']])) {
                $this->galat($hasil, $baris, "nomor meter {$data['nomor_meter']} ganda dengan baris {$meterDiBerkas[$data['nomor_meter']]}.");

                continue;
            }
            $meterDiBerkas[$data['nomor_meter']] = $baris;

            $existingId = Pelanggan::withTrashed()->where('idpel', $data['idpel'])->value('id');
            $validator = Validator::make($data, PelangganRequest::aturan($existingId), PelangganRequest::pesan(), PelangganRequest::atribut());

            if ($validator->fails()) {
                $this->galat($hasil, $baris, $validator->errors()->first());

                continue;
            }

            $siap[] = $validator->validated();
        }

        if ($hasil['errors']) {
            return $hasil;
        }

        DB::transaction(function () use ($siap, &$hasil) {
            foreach ($siap as $data) {
                $pelanggan = Pelanggan::withTrashed()->firstOrNew(['idpel' => $data['idpel']]);
                $baru = ! $pelanggan->exists;

                $pelanggan->fill($data);
                $pelanggan->deleted_at = null;
                $pelanggan->save();

                $baru ? $hasil['dibuat']++ : $hasil['diperbarui']++;
            }
        });

        return $hasil;
    }

    /** @param  array<string, mixed>  $row */
    private function normalisasi(array $row): array
    {
        $teks = fn (string $k) => trim((string) ($row[$k] ?? ''));
        // Angka panjang dari Excel bisa terbaca sebagai float ("181101000121.0").
        $angkaTeks = fn (string $k) => preg_replace('/\.0+$/', '', $teks($k));
        $kosongJadiNull = fn (string $k) => $teks($k) === '' ? null : $teks($k);

        return [
            'idpel' => $angkaTeks('idpel'),
            'nama' => $teks('nama'),
            'alamat' => $teks('alamat'),
            'kelurahan' => $teks('kelurahan'),
            'kode_rute' => strtoupper($teks('kode_rute')),
            'urutan_rute' => $angkaTeks('urutan_rute'),
            'jenis' => strtolower($teks('jenis')),
            'golongan_tarif' => strtoupper($teks('golongan_tarif')),
            'daya_va' => $angkaTeks('daya_va'),
            'nomor_meter' => $angkaTeks('nomor_meter'),
            'letak_meter' => $kosongJadiNull('letak_meter'),
            'latitude' => $kosongJadiNull('latitude'),
            'longitude' => $kosongJadiNull('longitude'),
            'status' => strtolower($teks('status')) ?: 'aktif',
        ];
    }

    private function galat(array &$hasil, int $baris, string $pesan): void
    {
        if (count($hasil['errors']) < self::MAKS_GALAT) {
            $hasil['errors'][] = "Baris {$baris}: {$pesan}";
        }
    }
}
