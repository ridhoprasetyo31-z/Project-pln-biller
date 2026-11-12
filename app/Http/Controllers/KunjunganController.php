<?php

namespace App\Http\Controllers;

use App\Enums\HasilKunjungan;
use App\Http\Requests\KunjunganRequest;
use App\Models\Invoice;
use App\Models\KunjunganPenagihan;
use App\Models\Pengaturan;
use App\Services\KunjunganService;
use App\Support\StatusCatatanView;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form kunjungan penagihan oleh biller (baru, atau perbaikan setelah dikembalikan).
 */
class KunjunganController extends Controller
{
    public function create(Invoice $invoice): Response
    {
        $this->authorize('create', [KunjunganPenagihan::class, $invoice]);

        return $this->form($invoice, null);
    }

    public function store(KunjunganRequest $request, Invoice $invoice, KunjunganService $service): RedirectResponse
    {
        $service->simpan($request->user(), $invoice, $request->safe()->except('foto'), $request->file('foto'));

        return to_route('penagihan.show', $invoice)->with('success', 'Kunjungan penagihan terkirim ke koordinator untuk diverifikasi.');
    }

    public function edit(KunjunganPenagihan $kunjungan): Response
    {
        $this->authorize('update', $kunjungan);

        return $this->form($kunjungan->invoice()->firstOrFail(), $kunjungan);
    }

    public function update(KunjunganRequest $request, KunjunganPenagihan $kunjungan, KunjunganService $service): RedirectResponse
    {
        $invoice = $kunjungan->invoice()->firstOrFail();
        $service->simpan($request->user(), $invoice, $request->safe()->except('foto'), $request->file('foto'), $kunjungan);

        return to_route('penagihan.show', $invoice)->with('success', 'Perbaikan kunjungan terkirim ke koordinator.');
    }

    private function form(Invoice $invoice, ?KunjunganPenagihan $kunjungan): Response
    {
        $invoice->load('pelanggan', 'periode');
        $kunjungan?->load('lampiran', 'verifikasiTerakhir.verifikator:id,name');
        $p = $invoice->pelanggan;

        return Inertia::render('Penagihan/Kunjungan', [
            'invoice' => [
                'id' => $invoice->id,
                'nomor' => $invoice->nomor,
                'periode' => $invoice->periode->nama(),
                'total' => $invoice->total,
                'jatuh_tempo' => $invoice->jatuh_tempo->toDateString(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ],
            'pelanggan' => [
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => "{$p->alamat}, {$p->kelurahan}",
                'tarif' => $p->labelTarif(),
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ],
            'kunjungan' => $kunjungan ? [
                'id' => $kunjungan->id,
                'hasil' => $kunjungan->hasil->value,
                'tanggal_janji' => $kunjungan->tanggal_janji?->toDateString(),
                'nominal_dibayar' => $kunjungan->nominal_dibayar,
                'keterangan' => $kunjungan->keterangan,
                'foto' => StatusCatatanView::foto($kunjungan->lampiran->first()),
                'verifikasi' => StatusCatatanView::verifikasi($kunjungan->verifikasiTerakhir),
                'jumlah_revisi' => $kunjungan->jumlah_revisi,
            ] : null,
            'hasilOptions' => HasilKunjungan::options(),
            'ambangJarak' => (float) Pengaturan::ambil('ambang_jarak_m'),
        ]);
    }
}
