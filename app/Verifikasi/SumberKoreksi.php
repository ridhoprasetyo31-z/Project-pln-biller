<?php

namespace App\Verifikasi;

use App\Enums\PenyebabKoreksi;
use App\Models\KoreksiRekening;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberKoreksi extends SumberVerifikasi
{
    public function tipe(): string
    {
        return 'koreksi_rekening';
    }

    public function label(): string
    {
        return 'Koreksi rekening';
    }

    public function query(?int $unitId): Builder
    {
        return KoreksiRekening::query()->diUnit($unitId);
    }

    protected function withItem(): array
    {
        return ['pelanggan:id,idpel,nama', 'pengaju:id,name'];
    }

    /** @param  KoreksiRekening  $m */
    protected function ringkas(Model $m): array
    {
        return [
            'idpel' => $m->pelanggan->idpel,
            'nama' => $m->pelanggan->nama,
            'biller' => $m->pengaju->name,
            'waktu' => $m->created_at?->toIso8601String(),
            'keterangan' => $m->penyebab->label(),
        ];
    }

    /** @param  KoreksiRekening  $k */
    public function detail(Model $k): array
    {
        $k->loadMissing('pelanggan', 'periode', 'invoice', 'pengaju:id,name,nip', 'pencatatanMeter.biller:id,name', 'pencatatanMeter.lampiran', 'lampiran');
        $pelanggan = $k->pelanggan;
        $catatan = $k->pencatatanMeter;

        return $this->dasarDetail($k) + [
            'pelanggan' => [
                'idpel' => $pelanggan->idpel,
                'nama' => $pelanggan->nama,
                'alamat' => "{$pelanggan->alamat}, {$pelanggan->kelurahan}",
                'tarif' => $pelanggan->labelTarif(),
                'latitude' => $pelanggan->latitude,
                'longitude' => $pelanggan->longitude,
            ],
            'periode' => $k->periode->nama(),
            'invoice' => $k->invoice ? [
                'id' => $k->invoice->id,
                'nomor' => $k->invoice->nomor,
                'status' => $k->invoice->status->value,
                'status_label' => $k->invoice->status->label(),
            ] : null,
            'biller' => $k->pengaju->only(['name', 'nip']),
            'pencatat' => $catatan?->biller?->name,
            'penyebab' => $k->penyebab->value,
            'penyebab_label' => $k->penyebab->label(),
            'kurangi_akurasi' => $k->penyebab === PenyebabKoreksi::SalahCatatBiller,
            'alasan' => $k->alasan,
            'stand_lalu' => $catatan?->stand_lalu,
            'stand_lama' => $k->stand_lama,
            'stand_baru' => $k->stand_baru,
            'kwh_lama' => $k->kwh_lama,
            'kwh_baru' => $k->kwh_baru,
            'tagihan_lama' => $k->tagihan_lama,
            'tagihan_baru' => $k->tagihan_baru,
            'diajukan_at' => $k->created_at->toIso8601String(),
            'foto' => StatusCatatanView::foto($k->lampiran->firstWhere('jenis.value', 'bukti')),
            'foto_asli' => $catatan ? StatusCatatanView::foto(StatusCatatanView::fotoUtama($catatan)) : null,
        ];
    }
}
