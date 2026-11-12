<?php

namespace App\Services;

use App\Enums\JenisAnomali;
use App\Enums\JenisLampiran;
use App\Enums\JenisPdil;
use App\Enums\StatusCatatan;
use App\Enums\StatusPelanggan;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Models\Pengaturan;
use App\Models\Rute;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PDIL: biller mengusulkan pelanggan baru atau perubahan data, koordinator
 * memverifikasi, lalu data disalin ke tabel pelanggan.
 */
class PdilService
{
    public function __construct(private readonly LampiranService $lampiran) {}

    /**
     * @param  array<string, mixed>  $data  jenis, pelanggan_id|rute_id, kolom data pelanggan, keterangan, latitude, longitude
     * @param  array{rumah?: UploadedFile|null, meter?: UploadedFile|null}  $foto
     *
     * @throws ValidationException
     */
    public function ajukan(User $biller, array $data, array $foto, ?Pdil $pdil = null): Pdil
    {
        if ($pdil && ! $pdil->bisaDiubahBiller()) {
            throw ValidationException::withMessages(['pdil' => 'PDIL ini sedang diverifikasi atau sudah disetujui.']);
        }

        $jenis = $pdil?->jenis ?? JenisPdil::from($data['jenis']);
        $pelanggan = $jenis === JenisPdil::PerubahanData
            ? Pelanggan::findOrFail($pdil?->pelanggan_id ?? $data['pelanggan_id'])
            : null;

        $baru = collect(Pdil::KOLOM_DATA)->mapWithKeys(fn ($k) => [$k => $data[$k] ?? null])->all();

        if ($pelanggan) {
            $lama = self::dataPelanggan($pelanggan);
            if (self::samaSemua($lama, $baru)) {
                throw ValidationException::withMessages(['nama' => 'Belum ada data yang diubah dibanding data pelanggan sekarang.']);
            }
        }

        return DB::transaction(function () use ($biller, $data, $foto, $pdil, $jenis, $pelanggan, $baru) {
            $pdil ??= new Pdil(['biller_id' => $biller->id, 'jenis' => $jenis]);
            $pdil->fill($baru + [
                'pelanggan_id' => $pelanggan?->id,
                'rute_id' => $pelanggan?->rute_id ?? $pdil->rute_id ?? $data['rute_id'],
                'idpel' => $pelanggan?->idpel,
                'data_lama' => $pelanggan ? self::dataPelanggan($pelanggan) : null,
                'keterangan' => $data['keterangan'] ?? null,
                'status' => StatusCatatan::MenungguVerifikasi,
            ])->save();

            $folder = 'pdil/'.now()->format('Ym');
            foreach (['rumah' => JenisLampiran::Rumah, 'meter' => JenisLampiran::Meter] as $kunci => $jenisFoto) {
                if (! empty($foto[$kunci])) {
                    $this->lampiran->ganti($pdil, $foto[$kunci], $jenisFoto, $biller, $folder, $pdil->latitude, $pdil->longitude, $data["foto_{$kunci}_diambil_at"] ?? null);
                }
            }

            $this->periksa($pdil, $pelanggan);

            return $pdil;
        });
    }

    /** Validasi otomatis: bukti lengkap dan perpindahan titik lokasi. */
    private function periksa(Pdil $pdil, ?Pelanggan $pelanggan): void
    {
        $anomali = [];
        $jenisFoto = $pdil->lampiran()->pluck('jenis')->map(fn ($j) => $j->value)->all();
        $kurang = array_filter([
            ! in_array('rumah', $jenisFoto, true) ? 'foto rumah' : null,
            ! in_array('meter', $jenisFoto, true) ? 'foto meter' : null,
            $pdil->latitude === null ? 'titik lokasi' : null,
        ]);
        if ($kurang) {
            $anomali[] = ['jenis' => JenisAnomali::BuktiTidakLengkap, 'nilai' => null, 'keterangan' => 'Tanpa '.implode(', ', $kurang).'.'];
        }

        if ($pelanggan && $pelanggan->latitude !== null && $pdil->latitude !== null) {
            $geser = GeoDistance::meter($pdil->latitude, $pdil->longitude, $pelanggan->latitude, $pelanggan->longitude);
            $ambang = (float) Pengaturan::ambil('ambang_jarak_m');
            if ($geser !== null && $geser > $ambang) {
                $anomali[] = [
                    'jenis' => JenisAnomali::LokasiJauh,
                    'nilai' => round($geser, 1),
                    'keterangan' => sprintf('Titik lokasi baru %s m dari titik lama. Pastikan pelanggan memang pindah lokasi meter.', number_format($geser, 0, ',', '.')),
                ];
            }
        }

        $pdil->anomali()->delete();
        foreach ($anomali as $a) {
            $pdil->anomali()->create($a);
        }
        $pdil->update(['status' => $anomali ? StatusCatatan::Ditandai : StatusCatatan::MenungguVerifikasi]);
    }

    /** Dipanggil saat PDIL disetujui: salin ke tabel pelanggan. */
    public function terapkan(Pdil $pdil): Pelanggan
    {
        $data = $pdil->dataBaru();

        if ($pdil->jenis === JenisPdil::PerubahanData) {
            $pelanggan = Pelanggan::findOrFail($pdil->pelanggan_id);
            $pelanggan->update(['jenis' => $data['jenis_pelanggan']] + collect($data)->except('jenis_pelanggan')->all());

            return $pelanggan;
        }

        $rute = Rute::with('unit:id,kode')->findOrFail($pdil->rute_id);
        $pelanggan = Pelanggan::create(collect($data)->except('jenis_pelanggan')->all() + [
            'jenis' => $data['jenis_pelanggan'],
            'idpel' => $this->idpelBaru($rute, $pdil),
            'rute_id' => $rute->id,
            'urutan_rute' => (int) Pelanggan::withTrashed()->where('rute_id', $rute->id)->max('urutan_rute') + 1,
            'status' => StatusPelanggan::Aktif,
        ]);

        $pdil->update(['pelanggan_id' => $pelanggan->id, 'idpel' => $pelanggan->idpel]);

        return $pelanggan;
    }

    /** ID pelanggan 12 digit: 5 digit kode unit + "9" (penanda PDIL) + 6 digit urut. */
    private function idpelBaru(Rute $rute, Pdil $pdil): string
    {
        $prefix = substr(preg_replace('/\D/', '', (string) $rute->unit->kode).'00000', 0, 5).'9';
        $n = $pdil->id;
        do {
            $idpel = $prefix.str_pad((string) $n++, 6, '0', STR_PAD_LEFT);
        } while (Pelanggan::withTrashed()->where('idpel', $idpel)->exists());

        return $idpel;
    }

    /** @return array<string, mixed> */
    public static function dataPelanggan(Pelanggan $p): array
    {
        return [
            'nama' => $p->nama,
            'alamat' => $p->alamat,
            'kelurahan' => $p->kelurahan,
            'jenis_pelanggan' => $p->jenis->value,
            'golongan_tarif' => $p->golongan_tarif,
            'daya_va' => $p->daya_va,
            'nomor_meter' => $p->nomor_meter,
            'letak_meter' => $p->letak_meter,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
        ];
    }

    private static function samaSemua(array $lama, array $baru): bool
    {
        foreach (Pdil::KOLOM_DATA as $k) {
            $a = $lama[$k] ?? null;
            $b = $baru[$k] ?? null;
            $sama = in_array($k, ['latitude', 'longitude', 'daya_va'], true)
                ? (($a === null && $b === null) || ($a !== null && $b !== null && abs((float) $a - (float) $b) < 0.0000005))
                : (string) $a === (string) $b;
            if (! $sama) {
                return false;
            }
        }

        return true;
    }
}
