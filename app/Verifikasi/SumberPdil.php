<?php

namespace App\Verifikasi;

use App\Enums\JenisPdil;
use App\Models\Pdil;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberPdil extends SumberVerifikasi
{
    public function tipe(): string
    {
        return 'pdil';
    }

    public function label(): string
    {
        return 'PDIL';
    }

    public function query(?int $unitId): Builder
    {
        return Pdil::query()->diUnit($unitId);
    }

    protected function kolomWaktu(): string
    {
        return 'updated_at';
    }

    protected function withItem(): array
    {
        return ['biller:id,name'];
    }

    /** @param  Pdil  $m */
    protected function ringkas(Model $m): array
    {
        return [
            'idpel' => $m->idpel ?? 'Pelanggan baru',
            'nama' => $m->nama,
            'biller' => $m->biller->name,
            'waktu' => $m->updated_at?->toIso8601String(),
            'keterangan' => $m->jenis->label(),
        ];
    }

    /** @param  Pdil  $p */
    public function detail(Model $p): array
    {
        $p->loadMissing('biller:id,name,nip', 'rute:id,kode,nama', 'lampiran');
        $baru = $p->dataBaru();
        $lama = $p->jenis === JenisPdil::PerubahanData ? ($p->data_lama ?? []) : null;

        return $this->dasarDetail($p) + [
            'pelanggan' => [
                'idpel' => $p->idpel ?? 'Belum ada (dibuat saat disetujui)',
                'nama' => $p->nama,
                'alamat' => "{$p->alamat}, {$p->kelurahan}",
                'tarif' => $p->golongan_tarif.' / '.number_format($p->daya_va, 0, ',', '.').' VA',
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ],
            'biller' => $p->biller->only(['name', 'nip']),
            'jenis' => $p->jenis->value,
            'jenis_pdil_label' => $p->jenis->label(),
            'rute' => "{$p->rute->kode} · {$p->rute->nama}",
            'perbandingan' => PdilView::perbandingan($lama, $baru),
            'keterangan' => $p->keterangan,
            'diajukan_at' => $p->updated_at->toIso8601String(),
            'foto' => StatusCatatanView::foto($p->lampiran->firstWhere('jenis.value', 'rumah')),
            'foto_meter' => StatusCatatanView::foto($p->lampiran->firstWhere('jenis.value', 'meter')),
        ];
    }
}
