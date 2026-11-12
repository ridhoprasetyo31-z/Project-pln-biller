<?php

namespace App\Verifikasi;

use App\Models\PencatatanMeter;
use App\Services\RiwayatPemakaian;
use App\Support\StatusCatatanView;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberPencatatan extends SumberVerifikasi
{
    public function __construct(private readonly RiwayatPemakaian $riwayat) {}

    public function tipe(): string
    {
        return 'pencatatan_meter';
    }

    public function label(): string
    {
        return 'Catat meter';
    }

    public function query(?int $unitId): Builder
    {
        return PencatatanMeter::query()->diUnit($unitId)->periodeTerbuka();
    }

    protected function kolomWaktu(): string
    {
        return 'dikirim_at';
    }

    protected function withItem(): array
    {
        return ['pelanggan:id,idpel,nama', 'biller:id,name'];
    }

    /** @param  PencatatanMeter  $m */
    protected function ringkas(Model $m): array
    {
        return [
            'idpel' => $m->pelanggan->idpel,
            'nama' => $m->pelanggan->nama,
            'biller' => $m->biller->name,
            'waktu' => $m->dikirim_at?->toIso8601String(),
            'keterangan' => $m->stand_kini !== null
                ? 'Stand '.number_format($m->stand_kini, 1, ',', '.')
                : ($m->sisa_kwh !== null ? 'Sisa '.number_format($m->sisa_kwh, 2, ',', '.').' kWh' : null),
        ];
    }

    /** @param  PencatatanMeter  $c */
    public function detail(Model $c): array
    {
        $c->loadMissing('pelanggan', 'periode', 'biller:id,name,nip', 'lampiran');
        $pelanggan = $c->pelanggan;
        $pasca = $c->jenis->value === 'pascabayar';
        $histori = $this->riwayat->untuk($pelanggan->id, $c->periode, 3)->reverse()->values();
        $bulan = fn (string $kode) => CarbonImmutable::createFromFormat('Ym', $kode)->startOfMonth()->locale('id')->translatedFormat('M');

        $bars = $histori->map(fn ($h) => [
            'label' => $bulan($h->kode),
            'nilai' => $pasca ? $h->pemakaian_kwh : $h->sisa_kwh,
            'kini' => false,
        ])->push([
            'label' => $bulan($c->periode->kode),
            'nilai' => $pasca ? $c->pemakaian_kwh : $c->sisa_kwh,
            'kini' => true,
        ]);

        return $this->dasarDetail($c) + [
            'jenis' => $c->jenis->value,
            'periode' => $c->periode->kode,
            'pelanggan' => [
                'idpel' => $pelanggan->idpel,
                'nama' => $pelanggan->nama,
                'alamat' => "{$pelanggan->alamat}, {$pelanggan->kelurahan}",
                'tarif' => $pelanggan->labelTarif(),
                'nomor_meter' => $pelanggan->nomor_meter,
                'latitude' => $pelanggan->latitude,
                'longitude' => $pelanggan->longitude,
            ],
            'biller' => $c->biller->only(['name', 'nip']),
            'stand_lalu' => $c->stand_lalu,
            'stand_kini' => $c->stand_kini,
            'pemakaian_kwh' => $c->pemakaian_kwh,
            'sisa_kwh' => $c->sisa_kwh,
            'rata_tiga_bulan' => $pasca ? $this->riwayat->rataTigaBulan($pelanggan->id, $c->periode) : null,
            'kondisi' => $c->kondisi_meter->label(),
            'keterangan' => $c->keterangan,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'jarak_m' => $c->jarak_m,
            'dikirim_at' => $c->dikirim_at?->toIso8601String(),
            'tepat_waktu' => $c->tepat_waktu,
            'foto' => StatusCatatanView::foto(StatusCatatanView::fotoUtama($c)),
            'bars' => $bars,
        ];
    }
}
