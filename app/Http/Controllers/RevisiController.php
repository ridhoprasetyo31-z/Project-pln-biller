<?php

namespace App\Http\Controllers;

use App\Enums\StatusCatatan;
use App\Models\PencatatanMeter;
use App\Support\StatusCatatanView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catatan biller yang dikembalikan koordinator dan harus diperbaiki.
 */
class RevisiController extends Controller
{
    public function index(Request $request): Response
    {
        $catatan = PencatatanMeter::query()
            ->with([
                'pelanggan:id,idpel,nama,alamat,jenis,urutan_rute,rute_id',
                'pelanggan.rute:id,kode',
                'periode:id,kode',
                'verifikasiTerakhir.verifikator:id,name',
            ])
            ->where('biller_id', $request->user()->id)
            ->where('status', StatusCatatan::Revisi)
            ->periodeTerbuka()
            ->orderBy('updated_at')
            ->get()
            ->map(fn (PencatatanMeter $c) => [
                'id' => $c->id,
                'periode' => $c->periode->kode,
                'pelanggan' => [
                    'idpel' => $c->pelanggan->idpel,
                    'nama' => $c->pelanggan->nama,
                    'alamat' => $c->pelanggan->alamat,
                    'urutan' => $c->pelanggan->urutan_rute,
                    'rute' => $c->pelanggan->rute->kode,
                ],
                'stand_kini' => $c->stand_kini,
                'sisa_kwh' => $c->sisa_kwh,
                'jenis' => $c->jenis->value,
                'jumlah_revisi' => $c->jumlah_revisi,
                'verifikasi' => StatusCatatanView::verifikasi($c->verifikasiTerakhir),
                'url' => route("catat.{$c->pelanggan->jenis->value}", $c->pelanggan_id),
            ]);

        return Inertia::render('Revisi/Index', ['catatan' => $catatan]);
    }
}
