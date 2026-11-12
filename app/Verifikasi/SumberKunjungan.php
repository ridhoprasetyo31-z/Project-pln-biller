<?php

namespace App\Verifikasi;

use App\Enums\HasilKunjungan;
use App\Models\KunjunganPenagihan;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberKunjungan extends SumberVerifikasi
{
    public function tipe(): string
    {
        return 'kunjungan_penagihan';
    }

    public function label(): string
    {
        return 'Penagihan';
    }

    public function query(?int $unitId): Builder
    {
        return KunjunganPenagihan::query()->diUnit($unitId);
    }

    protected function kolomWaktu(): string
    {
        return 'dikunjungi_at';
    }

    protected function withItem(): array
    {
        return ['invoice:id,nomor,pelanggan_id,total', 'invoice.pelanggan:id,idpel,nama', 'biller:id,name'];
    }

    /** @param  KunjunganPenagihan  $m */
    protected function ringkas(Model $m): array
    {
        return [
            'idpel' => $m->invoice->pelanggan->idpel,
            'nama' => $m->invoice->pelanggan->nama,
            'biller' => $m->biller->name,
            'waktu' => $m->dikunjungi_at?->toIso8601String(),
            'keterangan' => $m->hasil->label().($m->hasil === HasilKunjungan::Lunas && $m->nominal_dibayar !== null
                ? ' · Rp'.number_format($m->nominal_dibayar, 0, ',', '.')
                : ''),
        ];
    }

    /** @param  KunjunganPenagihan  $k */
    public function detail(Model $k): array
    {
        $k->loadMissing('invoice.pelanggan', 'invoice.periode', 'biller:id,name,nip', 'lampiran');
        $invoice = $k->invoice;
        $pelanggan = $invoice->pelanggan;

        return $this->dasarDetail($k) + [
            'pelanggan' => [
                'idpel' => $pelanggan->idpel,
                'nama' => $pelanggan->nama,
                'alamat' => "{$pelanggan->alamat}, {$pelanggan->kelurahan}",
                'tarif' => $pelanggan->labelTarif(),
                'latitude' => $pelanggan->latitude,
                'longitude' => $pelanggan->longitude,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'nomor' => $invoice->nomor,
                'periode' => $invoice->periode->nama(),
                'total' => $invoice->total,
                'jatuh_tempo' => $invoice->jatuh_tempo->toDateString(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ],
            'biller' => $k->biller->only(['name', 'nip']),
            'hasil' => $k->hasil->value,
            'hasil_label' => $k->hasil->label(),
            'tanggal_janji' => $k->tanggal_janji?->toDateString(),
            'nominal_dibayar' => $k->nominal_dibayar,
            'keterangan' => $k->keterangan,
            'latitude' => $k->latitude,
            'longitude' => $k->longitude,
            'jarak_m' => $k->jarak_m,
            'dikunjungi_at' => $k->dikunjungi_at?->toIso8601String(),
            'foto' => StatusCatatanView::foto($k->lampiran->firstWhere('jenis.value', 'bukti')),
            'kunjungan_sebelumnya' => KunjunganPenagihan::where('invoice_id', $invoice->id)->whereKeyNot($k->id)->count(),
        ];
    }
}
