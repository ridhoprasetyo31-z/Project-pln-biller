<?php

namespace App\Services;

use App\Enums\JenisLampiran;
use App\Enums\PenyebabKoreksi;
use App\Enums\StatusCatatan;
use App\Enums\StatusPeriode;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Models\PencatatanMeter;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi rekening: biller mengajukan stand yang benar, koordinator menyetujui,
 * lalu invoice dan catatan meter diperbarui. Penyebab "salah catat biller"
 * mengurangi akurasi biller pencatat (lihat KpiCalculator).
 */
class KoreksiService
{
    public function __construct(
        private readonly LampiranService $lampiran,
        private readonly KpiCalculator $kpi,
    ) {}

    /**
     * Hitung ulang kWh dan tagihan dengan tarif yang tersalin di invoice.
     *
     * @return array{stand_lalu: float|null, stand_lama: float, stand_baru: float, kwh_lama: float, kwh_baru: float, tagihan_lama: float, tagihan_baru: float, rincian: array}
     */
    public function hitung(Invoice $invoice, float $standBaru): array
    {
        $catatan = $invoice->pencatatanMeter()->firstOrFail();
        $kwhBaru = round($standBaru - (float) $catatan->stand_lalu, 1);
        $rincian = InvoiceGenerator::rincian($kwhBaru, $invoice->harga_per_kwh, $invoice->biaya_beban, InvoiceGenerator::ppjDariInvoice($invoice));

        return [
            'stand_lalu' => $catatan->stand_lalu,
            'stand_lama' => (float) $catatan->stand_kini,
            'stand_baru' => round($standBaru, 1),
            'kwh_lama' => $invoice->pemakaian_kwh,
            'kwh_baru' => $kwhBaru,
            'tagihan_lama' => $invoice->total,
            'tagihan_baru' => $rincian['total'],
            'rincian' => $rincian,
        ];
    }

    /**
     * Ajukan (atau perbaiki setelah revisi) koreksi. Langsung masuk antrean verifikasi.
     *
     * @param  array{stand_baru: float, penyebab: string, alasan: string, foto_diambil_at?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function ajukan(User $pengaju, Invoice $invoice, array $data, ?UploadedFile $foto, ?KoreksiRekening $koreksi = null): KoreksiRekening
    {
        if ($koreksi && ! $koreksi->bisaDiubahBiller()) {
            throw ValidationException::withMessages(['koreksi' => 'Koreksi ini sedang diverifikasi atau sudah disetujui.']);
        }

        $terbuka = KoreksiRekening::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', [StatusCatatan::MenungguVerifikasi, StatusCatatan::Ditandai, StatusCatatan::Revisi])
            ->when($koreksi, fn ($q) => $q->whereKeyNot($koreksi->id))
            ->exists();
        if ($terbuka) {
            throw ValidationException::withMessages(['koreksi' => 'Invoice ini masih punya koreksi lain yang belum selesai.']);
        }

        $h = $this->hitung($invoice, (float) $data['stand_baru']);

        return DB::transaction(function () use ($pengaju, $invoice, $data, $foto, $koreksi, $h) {
            $koreksi ??= new KoreksiRekening(['diajukan_oleh' => $pengaju->id]);
            $koreksi->fill([
                'pelanggan_id' => $invoice->pelanggan_id,
                'periode_id' => $invoice->periode_id,
                'invoice_id' => $invoice->id,
                'pencatatan_meter_id' => $invoice->pencatatan_meter_id,
                'stand_lama' => $h['stand_lama'],
                'stand_baru' => $h['stand_baru'],
                'kwh_lama' => $h['kwh_lama'],
                'kwh_baru' => $h['kwh_baru'],
                'tagihan_lama' => $h['tagihan_lama'],
                'tagihan_baru' => $h['tagihan_baru'],
                'penyebab' => PenyebabKoreksi::from($data['penyebab']),
                'alasan' => $data['alasan'],
                'status' => StatusCatatan::MenungguVerifikasi,
            ])->save();

            if ($foto) {
                $this->lampiran->ganti($koreksi, $foto, JenisLampiran::Bukti, $pengaju, 'koreksi/'.now()->format('Ym'), null, null, $data['foto_diambil_at'] ?? null);
            }

            return $koreksi;
        });
    }

    /**
     * Dipanggil saat koreksi disetujui: perbarui invoice dan catatan meter,
     * sesuaikan stand lalu catatan bulan berikutnya yang belum disetujui,
     * lalu hitung ulang skor periode tersebut.
     */
    public function terapkan(KoreksiRekening $koreksi): void
    {
        $invoice = $koreksi->invoice()->first();
        $catatan = $koreksi->pencatatanMeter()->first();
        $periode = $koreksi->periode()->first();

        if ($invoice) {
            $r = InvoiceGenerator::rincian($koreksi->kwh_baru, $invoice->harga_per_kwh, $invoice->biaya_beban, InvoiceGenerator::ppjDariInvoice($invoice));
            $invoice->update(['pemakaian_kwh' => $r['pemakaian_kwh'], 'pajak' => $r['pajak'], 'total' => $r['total']]);
        }

        if ($catatan) {
            $catatan->update(['stand_kini' => $koreksi->stand_baru, 'pemakaian_kwh' => $koreksi->kwh_baru]);

            // Catatan bulan berikutnya yang belum disetujui memakai stand lalu yang sudah dikoreksi.
            PencatatanMeter::query()
                ->where('pelanggan_id', $catatan->pelanggan_id)
                ->whereIn('periode_id', Periode::where('kode', '>', $periode->kode)->select('id'))
                ->where('status', '!=', StatusCatatan::Disetujui)
                ->where('stand_lalu', $koreksi->stand_lama)
                ->get()
                ->each(fn (PencatatanMeter $n) => $n->update([
                    'stand_lalu' => $koreksi->stand_baru,
                    'pemakaian_kwh' => $n->stand_kini !== null ? round($n->stand_kini - $koreksi->stand_baru, 1) : null,
                ]));
        }

        if ($periode && $periode->status !== StatusPeriode::Draft) {
            $this->kpi->hitung($periode, $periode->status === StatusPeriode::Ditutup);
        }
    }
}
