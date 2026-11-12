<?php

namespace App\Services;

use App\Enums\HasilKunjungan;
use App\Enums\JenisAnomali;
use App\Enums\JenisLampiran;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\KunjunganPenagihan;
use App\Models\Pengaturan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kunjungan penagihan oleh biller: hasil, foto bukti, dan lokasi GPS.
 * Setelah dikirim masuk antrean verifikasi; tanpa GPS/foto atau lokasi jauh → ditandai.
 */
class KunjunganService
{
    public function __construct(private readonly LampiranService $lampiran) {}

    /**
     * @param  array{hasil: string, tanggal_janji?: string|null, nominal_dibayar?: float|null, keterangan?: string|null, latitude?: float|null, longitude?: float|null, foto_diambil_at?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function simpan(User $biller, Invoice $invoice, array $data, ?UploadedFile $foto, ?KunjunganPenagihan $kunjungan = null): KunjunganPenagihan
    {
        if ($kunjungan && ! $kunjungan->bisaDiubahBiller()) {
            throw ValidationException::withMessages(['kunjungan' => 'Kunjungan ini sedang diverifikasi atau sudah disetujui.']);
        }
        if (! $kunjungan && ! $invoice->bisaDitagih()) {
            throw ValidationException::withMessages(['kunjungan' => 'Invoice ini sudah lunas atau dibatalkan.']);
        }

        return DB::transaction(function () use ($biller, $invoice, $data, $foto, $kunjungan) {
            $invoice->loadMissing('pelanggan');
            $pelanggan = $invoice->pelanggan;
            $hasil = HasilKunjungan::from($data['hasil']);
            $lat = isset($data['latitude']) ? (float) $data['latitude'] : $kunjungan?->latitude;
            $lng = isset($data['longitude']) ? (float) $data['longitude'] : $kunjungan?->longitude;
            $jarak = GeoDistance::meter($lat, $lng, $pelanggan->latitude, $pelanggan->longitude);

            $kunjungan ??= new KunjunganPenagihan(['invoice_id' => $invoice->id, 'biller_id' => $biller->id]);
            $kunjungan->fill([
                'dikunjungi_at' => now(),
                'hasil' => $hasil,
                'tanggal_janji' => $hasil === HasilKunjungan::JanjiBayar ? $data['tanggal_janji'] : null,
                'nominal_dibayar' => $hasil === HasilKunjungan::Lunas ? (float) ($data['nominal_dibayar'] ?? $invoice->total) : null,
                'keterangan' => $data['keterangan'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'jarak_m' => $jarak !== null ? round($jarak, 1) : null,
                'status' => StatusCatatan::MenungguVerifikasi,
            ])->save();

            if ($foto) {
                $this->lampiran->ganti($kunjungan, $foto, JenisLampiran::Bukti, $biller, 'penagihan/'.now()->format('Ym'), $lat, $lng, $data['foto_diambil_at'] ?? null);
            }

            $this->periksa($kunjungan, $invoice);

            return $kunjungan;
        });
    }

    /** Validasi otomatis kunjungan: bukti lengkap, jarak, dan nominal bayar. */
    private function periksa(KunjunganPenagihan $k, Invoice $invoice): void
    {
        $ambangJarak = (float) Pengaturan::ambil('ambang_jarak_m');
        $anomali = [];

        if ($k->jarak_m !== null && $k->jarak_m > $ambangJarak) {
            $anomali[] = [
                'jenis' => JenisAnomali::LokasiJauh,
                'nilai' => $k->jarak_m,
                'keterangan' => sprintf('Kunjungan dicatat %s m dari titik pelanggan. Batas %s m.', number_format($k->jarak_m, 0, ',', '.'), number_format($ambangJarak, 0, ',', '.')),
            ];
        }

        $adaFoto = $k->lampiran()->where('jenis', JenisLampiran::Bukti)->exists();
        if (! $adaFoto || $k->latitude === null) {
            $kurang = array_filter([! $adaFoto ? 'foto' : null, $k->latitude === null ? 'lokasi GPS' : null]);
            $anomali[] = ['jenis' => JenisAnomali::BuktiTidakLengkap, 'nilai' => null, 'keterangan' => 'Tanpa '.implode(' dan ', $kurang).'.'];
        }

        if ($k->hasil === HasilKunjungan::Lunas && $k->nominal_dibayar !== null && $k->nominal_dibayar + 0.5 < $invoice->total) {
            $anomali[] = [
                'jenis' => JenisAnomali::BuktiTidakLengkap,
                'nilai' => $k->nominal_dibayar,
                'keterangan' => sprintf('Nominal dibayar Rp%s kurang dari tagihan Rp%s.', number_format($k->nominal_dibayar, 0, ',', '.'), number_format($invoice->total, 0, ',', '.')),
            ];
        }

        $k->anomali()->delete();
        foreach ($anomali as $a) {
            $k->anomali()->create($a);
        }

        $k->update(['status' => $anomali ? StatusCatatan::Ditandai : StatusCatatan::MenungguVerifikasi]);
    }

    /** Dipanggil saat kunjungan disetujui: hasil "lunas" melunasi invoice. */
    public function terapkan(KunjunganPenagihan $k): void
    {
        if ($k->hasil !== HasilKunjungan::Lunas) {
            return;
        }

        $k->invoice()->first()?->update([
            'status' => StatusInvoice::Lunas,
            'dibayar_at' => CarbonImmutable::parse($k->dikunjungi_at),
        ]);
    }
}
