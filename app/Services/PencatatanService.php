<?php

namespace App\Services;

use App\Enums\JenisLampiran;
use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Enums\StatusCatatan;
use App\Jobs\BuatThumbnailFoto;
use App\Models\Lampiran;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan catatan meter (draf atau kirim) beserta fotonya.
 * Saat dikirim, catatan diperiksa AnomalyDetector lalu masuk antrean verifikasi.
 */
class PencatatanService
{
    public function __construct(
        private readonly RiwayatPemakaian $riwayat,
        private readonly AnomalyDetector $detector,
        private readonly ImageStore $images,
    ) {}

    /**
     * @param  array{stand_kini?: float|null, sisa_kwh?: float|null, kondisi_meter: string, keterangan?: string|null, latitude?: float|null, longitude?: float|null, foto_diambil_at?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function simpan(
        User $biller,
        Pelanggan $pelanggan,
        Penugasan $penugasan,
        array $data,
        ?UploadedFile $foto,
        bool $kirim,
        ?CarbonImmutable $sekarang = null,
    ): PencatatanMeter {
        $sekarang ??= CarbonImmutable::now();
        $periode = $penugasan->loadMissing('periode')->periode;
        $lampiranBaru = null;

        $catatan = DB::transaction(function () use ($biller, $pelanggan, $penugasan, $periode, $data, $foto, $kirim, $sekarang, &$lampiranBaru) {
            $catatan = PencatatanMeter::query()
                ->lockForUpdate()
                ->firstOrNew(['periode_id' => $periode->id, 'pelanggan_id' => $pelanggan->id]);

            if ($catatan->exists && ! $catatan->bisaDiubahBiller()) {
                throw ValidationException::withMessages([
                    'catatan' => 'Catatan pelanggan ini sudah dikirim dan sedang diverifikasi, jadi tidak bisa diubah.',
                ]);
            }

            $pascabayar = $pelanggan->jenis === JenisPelanggan::Pascabayar;
            $standLalu = $pascabayar ? $this->riwayat->standLalu($pelanggan->id, $periode) : null;
            $standKini = $pascabayar && isset($data['stand_kini']) ? round((float) $data['stand_kini'], 1) : null;

            $lat = isset($data['latitude']) ? (float) $data['latitude'] : $catatan->latitude;
            $lng = isset($data['longitude']) ? (float) $data['longitude'] : $catatan->longitude;

            $catatan->fill([
                'biller_id' => $biller->id,
                'penugasan_id' => $penugasan->id,
                'jenis' => $pelanggan->jenis,
                'stand_lalu' => $standLalu,
                'stand_kini' => $standKini,
                'pemakaian_kwh' => ($standKini !== null && $standLalu !== null) ? round($standKini - $standLalu, 1) : null,
                'sisa_kwh' => $pascabayar ? null : (isset($data['sisa_kwh']) ? (float) $data['sisa_kwh'] : null),
                'kondisi_meter' => $data['kondisi_meter'],
                'keterangan' => $data['keterangan'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'jarak_m' => ($d = GeoDistance::meter($lat, $lng, $pelanggan->latitude, $pelanggan->longitude)) !== null ? round($d, 1) : null,
            ]);

            if (! $catatan->exists) {
                $catatan->status = StatusCatatan::Draft;
            }
            $catatan->save();

            if ($foto) {
                $lampiranBaru = $this->gantiFoto($catatan, $foto, $pascabayar ? JenisLampiran::Meter : JenisLampiran::LayarMeter, $biller, $lat, $lng, $data['foto_diambil_at'] ?? null);
            }

            if ($kirim) {
                $this->kirim($catatan, $pelanggan, $sekarang);
            }

            return $catatan;
        });

        if ($lampiranBaru) {
            BuatThumbnailFoto::dispatchAfterResponse($lampiranBaru->id);
        }

        return $catatan;
    }

    /** Jalankan validasi otomatis lalu ubah status ke antrean verifikasi. */
    public function kirim(PencatatanMeter $catatan, Pelanggan $pelanggan, CarbonImmutable $sekarang): void
    {
        $periode = $catatan->periode()->first();
        $adaFoto = $catatan->lampiran()->whereIn('jenis', [JenisLampiran::Meter, JenisLampiran::LayarMeter])->exists();

        $anomali = $this->detector->periksa(new DataPemeriksaan(
            jenis: $pelanggan->jenis,
            kondisi: $catatan->kondisi_meter instanceof KondisiMeter ? $catatan->kondisi_meter : KondisiMeter::from($catatan->kondisi_meter),
            standLalu: $catatan->stand_lalu,
            standKini: $catatan->stand_kini,
            rataTigaBulan: $pelanggan->jenis === JenisPelanggan::Pascabayar ? $this->riwayat->rataTigaBulan($pelanggan->id, $periode) : null,
            latitude: $catatan->latitude,
            longitude: $catatan->longitude,
            jarakM: $catatan->jarak_m,
            adaFoto: $adaFoto,
            dikirimAt: $sekarang,
            batasPencatatan: CarbonImmutable::parse($periode->tanggal_selesai),
        ));

        $catatan->anomali()->delete();
        foreach ($anomali as $a) {
            $catatan->anomali()->create($a);
        }

        $catatan->update([
            'dikirim_at' => $sekarang,
            'tepat_waktu' => $sekarang->lessThanOrEqualTo(CarbonImmutable::parse($periode->tanggal_selesai)->endOfDay()),
            'status' => $anomali ? StatusCatatan::Ditandai : StatusCatatan::MenungguVerifikasi,
        ]);
    }

    private function gantiFoto(
        PencatatanMeter $catatan,
        UploadedFile $foto,
        JenisLampiran $jenis,
        User $biller,
        ?float $lat,
        ?float $lng,
        ?string $diambilAt,
    ): Lampiran {
        foreach ($catatan->lampiran()->where('jenis', $jenis)->get() as $lama) {
            // Foto contoh dari seeder dipakai bersama, jangan dihapus berkasnya.
            if (! Str::startsWith($lama->path, 'demo/')) {
                $this->images->delete($lama->path);
                $this->images->delete($lama->thumbnail_path);
            }
            $lama->delete();
        }

        $path = $this->images->storeScaled($foto, 'lampiran/'.$catatan->periode()->value('kode'));

        return $catatan->lampiran()->create([
            'jenis' => $jenis,
            'path' => $path,
            'ukuran_kb' => (int) ceil(Storage::disk('public')->size($path) / 1024),
            'latitude' => $lat,
            'longitude' => $lng,
            'diambil_at' => $diambilAt ? CarbonImmutable::parse($diambilAt) : now(),
            'diunggah_oleh' => $biller->id,
        ]);
    }
}
