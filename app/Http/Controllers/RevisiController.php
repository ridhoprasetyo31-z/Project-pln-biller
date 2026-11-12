<?php

namespace App\Http\Controllers;

use App\Enums\StatusCatatan;
use App\Enums\StatusTusbug;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\Pdil;
use App\Models\PencatatanMeter;
use App\Models\Tusbug;
use App\Support\StatusCatatanView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Semua catatan biller yang dikembalikan koordinator: catat meter,
 * kunjungan penagihan, dan koreksi rekening.
 */
class RevisiController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $meter = PencatatanMeter::query()
            ->with(['pelanggan:id,idpel,nama,alamat,jenis,urutan_rute,rute_id', 'pelanggan.rute:id,kode', 'periode:id,kode', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('biller_id', $user->id)
            ->where('status', StatusCatatan::Revisi)
            ->periodeTerbuka()
            ->get()
            ->map(fn (PencatatanMeter $c) => [
                'kunci' => "pencatatan_meter-{$c->id}",
                'jenis_label' => 'Catat meter',
                'pelanggan' => ['idpel' => $c->pelanggan->idpel, 'nama' => $c->pelanggan->nama, 'alamat' => $c->pelanggan->alamat],
                'konteks' => "{$c->pelanggan->rute->kode} No. ".str_pad((string) $c->pelanggan->urutan_rute, 3, '0', STR_PAD_LEFT)." · periode {$c->periode->kode}",
                'ringkasan' => $c->jenis->value === 'pascabayar'
                    ? 'Stand dikirim '.($c->stand_kini !== null ? number_format($c->stand_kini, 1, ',', '.') : '–')
                    : 'Sisa kWh dikirim '.($c->sisa_kwh !== null ? number_format($c->sisa_kwh, 2, ',', '.') : '–'),
                'jumlah_revisi' => $c->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($c->verifikasiTerakhir),
                'url' => route("catat.{$c->pelanggan->jenis->value}", $c->pelanggan_id),
                'waktu' => $c->updated_at->getTimestamp(),
            ]);

        $kunjungan = KunjunganPenagihan::query()
            ->with(['invoice:id,nomor,pelanggan_id', 'invoice.pelanggan:id,idpel,nama,alamat', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('biller_id', $user->id)
            ->where('status', StatusCatatan::Revisi)
            ->get()
            ->map(fn (KunjunganPenagihan $k) => [
                'kunci' => "kunjungan_penagihan-{$k->id}",
                'jenis_label' => 'Penagihan',
                'pelanggan' => $k->invoice->pelanggan->only(['idpel', 'nama', 'alamat']),
                'konteks' => $k->invoice->nomor,
                'ringkasan' => 'Hasil kunjungan: '.$k->hasil->label(),
                'jumlah_revisi' => $k->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($k->verifikasiTerakhir),
                'url' => route('kunjungan.edit', $k),
                'waktu' => $k->updated_at->getTimestamp(),
            ]);

        $koreksi = KoreksiRekening::query()
            ->with(['pelanggan:id,idpel,nama,alamat', 'periode:id,kode', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('diajukan_oleh', $user->id)
            ->where('status', StatusCatatan::Revisi)
            ->get()
            ->map(fn (KoreksiRekening $k) => [
                'kunci' => "koreksi_rekening-{$k->id}",
                'jenis_label' => 'Koreksi rekening',
                'pelanggan' => $k->pelanggan->only(['idpel', 'nama', 'alamat']),
                'konteks' => "periode {$k->periode->kode}",
                'ringkasan' => 'Stand '.number_format($k->stand_lama, 1, ',', '.').' → '.number_format($k->stand_baru, 1, ',', '.'),
                'jumlah_revisi' => $k->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($k->verifikasiTerakhir),
                'url' => route('koreksi-rekening.edit', $k),
                'waktu' => $k->updated_at->getTimestamp(),
            ]);

        $pdil = Pdil::query()
            ->with(['rute:id,kode', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('biller_id', $user->id)
            ->where('status', StatusCatatan::Revisi)
            ->get()
            ->map(fn (Pdil $p) => [
                'kunci' => "pdil-{$p->id}",
                'jenis_label' => 'PDIL',
                'pelanggan' => ['idpel' => $p->idpel ?? 'Pelanggan baru', 'nama' => $p->nama, 'alamat' => $p->alamat],
                'konteks' => "{$p->rute->kode} · {$p->jenis->label()}",
                'ringkasan' => $p->jenis->label(),
                'jumlah_revisi' => $p->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($p->verifikasiTerakhir),
                'url' => route('pdil.edit', $p),
                'waktu' => $p->updated_at->getTimestamp(),
            ]);

        $tusbug = Tusbug::query()
            ->with(['pelanggan:id,idpel,nama,alamat', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('biller_id', $user->id)
            ->where('status', StatusTusbug::Revisi)
            ->get()
            ->map(fn (Tusbug $t) => [
                'kunci' => "tusbug-{$t->id}",
                'jenis_label' => 'TUSBUG',
                'pelanggan' => $t->pelanggan->only(['idpel', 'nama', 'alamat']),
                'konteks' => $t->nomor_surat,
                'ringkasan' => 'Hasil: '.($t->hasil?->label() ?? '–'),
                'jumlah_revisi' => $t->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($t->verifikasiTerakhir),
                'url' => route('tusbug.show', $t),
                'waktu' => $t->updated_at->getTimestamp(),
            ]);

        return Inertia::render('Revisi/Index', [
            'catatan' => $meter->concat($kunjungan)->concat($koreksi)->concat($pdil)->concat($tusbug)->sortBy('waktu')->values(),
        ]);
    }
}
