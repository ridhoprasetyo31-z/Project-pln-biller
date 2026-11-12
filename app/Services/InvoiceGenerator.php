<?php

namespace App\Services;

use App\Enums\JenisPelanggan;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\PencatatanMeter;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\Tarif;
use Carbon\CarbonImmutable;

/**
 * Membuat invoice (rekening) dari catatan meter pascabayar yang disetujui.
 * Harga per kWh, biaya beban, dan PPJ disalin ke invoice saat dibuat, sehingga
 * perubahan tarif berikutnya tidak mengubah rekening yang sudah terbit.
 *
 *   biaya pemakaian = pemakaian kWh × harga per kWh (dibulatkan ke rupiah)
 *   subtotal        = biaya pemakaian + biaya beban
 *   PPJ             = subtotal × ppj_persen (dibulatkan ke rupiah)
 *   total           = subtotal + PPJ
 */
class InvoiceGenerator
{
    /** @var array<string, Tarif|null> */
    private array $cacheTarif = [];

    /**
     * @return array{pemakaian_kwh: float, biaya_pemakaian: float, biaya_beban: float, subtotal: float, pajak: float, total: float}
     */
    public static function rincian(float $kwh, float $harga, float $beban, float $ppjPersen): array
    {
        $kwh = max(0.0, round($kwh, 1));
        $biayaPemakaian = round($kwh * $harga);
        $subtotal = $biayaPemakaian + round($beban);
        $pajak = round($subtotal * $ppjPersen / 100);

        return [
            'pemakaian_kwh' => $kwh,
            'biaya_pemakaian' => (float) $biayaPemakaian,
            'biaya_beban' => (float) round($beban),
            'subtotal' => (float) $subtotal,
            'pajak' => (float) $pajak,
            'total' => (float) ($subtotal + $pajak),
        ];
    }

    /** Persen PPJ yang tersirat di invoice (dipakai saat koreksi agar konsisten dengan rekening lama). */
    public static function ppjDariInvoice(Invoice $invoice): float
    {
        $subtotal = $invoice->total - $invoice->pajak;

        return $subtotal > 0 ? round($invoice->pajak / $subtotal * 100, 2) : (float) Pengaturan::ambil('ppj_persen');
    }

    public static function nomor(string $kodePeriode, string $idpel): string
    {
        return "INV/{$kodePeriode}/{$idpel}";
    }

    public static function jatuhTempo(Periode $periode): CarbonImmutable
    {
        return CarbonImmutable::parse($periode->tanggal_selesai)->addDays((int) config('kinerja.jatuh_tempo_hari', 25));
    }

    /**
     * Buat invoice untuk satu catatan. Null bila catatan tidak memenuhi syarat
     * (bukan pascabayar, belum disetujui, stand tidak terbaca, atau tarif tidak ditemukan).
     */
    public function buatUntuk(PencatatanMeter $catatan): ?Invoice
    {
        $catatan->loadMissing('pelanggan', 'periode', 'invoice');

        if ($catatan->invoice) {
            return $catatan->invoice;
        }
        if ($catatan->jenis !== JenisPelanggan::Pascabayar
            || $catatan->status !== StatusCatatan::Disetujui
            || $catatan->pemakaian_kwh === null) {
            return null;
        }

        $pelanggan = $catatan->pelanggan;
        $periode = $catatan->periode;
        $tarif = $this->tarif($pelanggan->golongan_tarif, $pelanggan->daya_va, CarbonImmutable::parse($periode->tanggal_mulai));

        if (! $tarif) {
            return null;
        }

        $r = self::rincian($catatan->pemakaian_kwh, (float) $tarif->harga_per_kwh, (float) $tarif->biaya_beban, (float) Pengaturan::ambil('ppj_persen'));

        return Invoice::create([
            'nomor' => self::nomor($periode->kode, $pelanggan->idpel),
            'pelanggan_id' => $pelanggan->id,
            'periode_id' => $periode->id,
            'pencatatan_meter_id' => $catatan->id,
            'tarif_id' => $tarif->id,
            'pemakaian_kwh' => $r['pemakaian_kwh'],
            'harga_per_kwh' => (float) $tarif->harga_per_kwh,
            'biaya_beban' => $r['biaya_beban'],
            'pajak' => $r['pajak'],
            'total' => $r['total'],
            'jatuh_tempo' => self::jatuhTempo($periode)->toDateString(),
            'status' => StatusInvoice::BelumBayar,
        ]);
    }

    /**
     * Buat invoice untuk semua catatan pascabayar disetujui di periode ini yang belum punya invoice.
     *
     * @return array{dibuat: int, dilewati: int}
     */
    public function buatPeriode(Periode $periode): array
    {
        $hasil = ['dibuat' => 0, 'dilewati' => 0];

        PencatatanMeter::query()
            ->where('periode_id', $periode->id)
            ->where('jenis', JenisPelanggan::Pascabayar)
            ->where('status', StatusCatatan::Disetujui)
            ->whereDoesntHave('invoice')
            ->with('pelanggan')
            ->chunkById(500, function ($catatan) use ($periode, &$hasil) {
                foreach ($catatan as $c) {
                    $c->setRelation('periode', $periode)->setRelation('invoice', null);
                    $this->buatUntuk($c) ? $hasil['dibuat']++ : $hasil['dilewati']++;
                }
            });

        return $hasil;
    }

    private function tarif(string $golongan, int $daya, CarbonImmutable $tanggal): ?Tarif
    {
        $kunci = "{$golongan}|{$daya}|{$tanggal->toDateString()}";

        return $this->cacheTarif[$kunci] ??= Tarif::untuk($golongan, $daya, $tanggal);
    }
}
