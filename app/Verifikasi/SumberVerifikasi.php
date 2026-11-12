<?php

namespace App\Verifikasi;

use App\Enums\StatusCatatan;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu jenis catatan yang masuk antrean verifikasi koordinator.
 * Antrean menggabungkan semua sumber (PRD 3.8: satu antrean untuk semua modul).
 */
abstract class SumberVerifikasi
{
    /** Alias morph map, dipakai di URL /verifikasi/{tipe}/{id}. */
    abstract public function tipe(): string;

    /** Label jenis untuk filter & kartu, contoh "Catat meter". */
    abstract public function label(): string;

    /** Query dasar: catatan jenis ini di unit koordinator. */
    abstract public function query(?int $unitId): Builder;

    /** Relasi yang dimuat untuk kartu antrean. */
    abstract protected function withItem(): array;

    /** Data kartu antrean (selain kunci bersama). */
    abstract protected function ringkas(Model $m): array;

    /** Data panel detail. */
    abstract public function detail(Model $m): array;

    /** Kolom waktu kirim untuk urutan antrean. */
    protected function kolomWaktu(): string
    {
        return 'created_at';
    }

    public function tab(Builder $q, string $tab): Builder
    {
        return match ($tab) {
            'ditandai' => $q->where('status', StatusCatatan::Ditandai->value),
            'normal' => $q->where('status', StatusCatatan::MenungguVerifikasi->value),
            default => $q->whereIn('status', [StatusCatatan::Disetujui->value, StatusCatatan::Revisi->value])
                ->whereHas('verifikasi')
                ->where('updated_at', '>=', now()->subDays(60)),
        };
    }

    /** @return list<array<string, mixed>> */
    public function items(?int $unitId, string $tab, int $batas = 300): array
    {
        return $this->tab($this->query($unitId), $tab)
            ->with([...$this->withItem(), 'anomali:id,anomaliable_id,anomaliable_type,jenis', 'lampiran'])
            ->orderBy($tab === 'diperiksa' ? 'updated_at' : $this->kolomWaktu(), $tab === 'diperiksa' ? 'desc' : 'asc')
            ->limit($batas)
            ->get()
            ->map(fn (Model $m) => [
                'tipe' => $this->tipe(),
                'id' => $m->getKey(),
                'jenis_label' => $this->label(),
                'status' => $m->status->value,
                'anomali' => $m->anomali->map(fn ($a) => $a->jenis->value)->unique()->values()->all(),
                'jumlah_revisi' => $m->jumlah_revisi,
                'urut' => ($tab === 'diperiksa' ? $m->updated_at : $m->{$this->kolomWaktu()})?->getTimestamp() ?? 0,
                'thumb' => $m->lampiran->first()?->thumbnailUrl(),
                ...$this->ringkas($m),
            ])
            ->all();
    }

    public function jumlah(?int $unitId, string $tab): int
    {
        return $this->tab($this->query($unitId), $tab)->count();
    }

    public function temukan(?int $unitId, int $id): ?Model
    {
        return $this->query($unitId)->find($id);
    }

    /** Kunci bersama untuk panel detail semua jenis. */
    protected function dasarDetail(Model $m): array
    {
        $m->loadMissing('verifikasi.verifikator:id,name', 'anomali');

        return [
            'tipe' => $this->tipe(),
            'jenis_label' => $this->label(),
            'id' => $m->getKey(),
            'status' => $m->status->value,
            'jumlah_revisi' => $m->jumlah_revisi,
            'anomali' => StatusCatatanView::anomali($m->anomali),
            'riwayatVerifikasi' => $m->verifikasi->map(fn ($v) => StatusCatatanView::verifikasi($v))->values(),
            'bisaDiputuskan' => in_array($m->status->value, ['menunggu_verifikasi', 'ditandai'], true),
        ];
    }
}
