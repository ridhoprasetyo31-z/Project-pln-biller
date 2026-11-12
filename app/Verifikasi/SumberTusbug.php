<?php

namespace App\Verifikasi;

use App\Enums\HasilTusbug;
use App\Models\Tusbug;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberTusbug extends SumberVerifikasi
{
    public function tipe(): string
    {
        return 'tusbug';
    }

    public function label(): string
    {
        return 'TUSBUG';
    }

    public function query(?int $unitId): Builder
    {
        return Tusbug::query()->diUnit($unitId);
    }

    protected function kolomWaktu(): string
    {
        return 'dilaksanakan_at';
    }

    protected function withItem(): array
    {
        return ['pelanggan:id,idpel,nama', 'biller:id,name'];
    }

    /** @param  Tusbug  $m */
    protected function ringkas(Model $m): array
    {
        return [
            'idpel' => $m->pelanggan->idpel,
            'nama' => $m->pelanggan->nama,
            'biller' => $m->biller->name,
            'waktu' => $m->dilaksanakan_at?->toIso8601String(),
            'keterangan' => $m->hasil?->label().' · '.$m->jumlah_lembar.' lembar',
        ];
    }

    /** @param  Tusbug  $t */
    public function detail(Model $t): array
    {
        $t->loadMissing('pelanggan', 'biller:id,name,nip', 'penugas:id,name', 'lampiran', 'invoice.periode:id,kode');
        $p = $t->pelanggan;

        return $this->dasarDetail($t) + [
            'pelanggan' => [
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => "{$p->alamat}, {$p->kelurahan}",
                'tarif' => $p->labelTarif(),
                'nomor_meter' => $p->nomor_meter,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ],
            'biller' => $t->biller->only(['name', 'nip']),
            'nomor_surat' => $t->nomor_surat,
            'penugas' => $t->penugas->name,
            'tanggal_rencana' => $t->tanggal_rencana->toDateString(),
            'jumlah_lembar' => $t->jumlah_lembar,
            'total_tunggakan' => $t->total_tunggakan,
            'invoice' => $t->invoice->map(fn ($i) => ['nomor' => $i->nomor, 'total' => $i->total, 'status_label' => $i->status->label()])->values(),
            'hasil' => $t->hasil?->value,
            'hasil_label' => $t->hasil?->label(),
            'efek' => match ($t->hasil) {
                HasilTusbug::Diputus => 'Bila disetujui, status pelanggan menjadi putus sementara.',
                HasilTusbug::BayarDiTempat => 'Bila disetujui, rekening tunggakan ditandai lunas.',
                default => 'Bila disetujui, status pelanggan dan rekening tidak berubah.',
            },
            'keterangan' => $t->keterangan,
            'latitude' => $t->latitude,
            'longitude' => $t->longitude,
            'jarak_m' => $t->jarak_m,
            'dilaksanakan_at' => $t->dilaksanakan_at?->toIso8601String(),
            'foto' => StatusCatatanView::foto($t->lampiran->firstWhere('jenis.value', 'sebelum')),
            'foto_sesudah' => StatusCatatanView::foto($t->lampiran->firstWhere('jenis.value', 'sesudah')),
        ];
    }
}
