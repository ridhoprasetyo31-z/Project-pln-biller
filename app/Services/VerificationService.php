<?php

namespace App\Services;

use App\Enums\AlasanTolak;
use App\Enums\KeputusanVerifikasi;
use App\Enums\StatusPeriode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan koordinator atas catatan dari semua modul (pencatatan meter,
 * kunjungan penagihan, koreksi, PDIL, TUSBUG). Setiap keputusan ditulis
 * ke tabel verifikasi (append-only) dan log audit.
 *
 * Disetujui → status "disetujui". Ditolak → status "revisi" dan jumlah_revisi bertambah.
 */
class VerificationService
{
    private const BISA_DIVERIFIKASI = ['menunggu_verifikasi', 'ditandai'];

    /** @throws ValidationException */
    public function setujui(Model $catatan, User $verifikator, ?string $catatanTeks = null): void
    {
        DB::transaction(function () use ($catatan, $verifikator, $catatanTeks) {
            $this->pastikanBisaDiverifikasi($catatan);

            $perubahan = ['status' => 'disetujui'];
            if ($catatan->isFillable('disetujui_at')) {
                $perubahan['disetujui_at'] = now();
            }
            $catatan->update($perubahan);

            $this->catat($catatan, $verifikator, KeputusanVerifikasi::Disetujui, null, $catatanTeks);
        });
    }

    /** @throws ValidationException */
    public function tolak(Model $catatan, User $verifikator, AlasanTolak $alasan, ?string $catatanTeks = null): void
    {
        if ($alasan === AlasanTolak::Lainnya && blank($catatanTeks)) {
            throw ValidationException::withMessages(['catatan' => 'Tulis catatan bila alasan penolakan "Lainnya".']);
        }

        DB::transaction(function () use ($catatan, $verifikator, $alasan, $catatanTeks) {
            $this->pastikanBisaDiverifikasi($catatan);

            $catatan->update([
                'status' => 'revisi',
                'jumlah_revisi' => $catatan->jumlah_revisi + 1,
            ]);

            $this->catat($catatan, $verifikator, KeputusanVerifikasi::Ditolak, $alasan, $catatanTeks);
        });
    }

    private function pastikanBisaDiverifikasi(Model $catatan): void
    {
        $catatan->refresh();
        $status = $catatan->status instanceof \BackedEnum ? $catatan->status->value : $catatan->status;

        if (! in_array($status, self::BISA_DIVERIFIKASI, true)) {
            throw ValidationException::withMessages([
                'catatan' => 'Catatan ini sudah diperiksa atau belum dikirim biller.',
            ]);
        }

        if ($catatan->isRelation('periode') && $catatan->periode_id) {
            $periode = $catatan->periode()->first();
            if ($periode?->status === StatusPeriode::Ditutup) {
                throw ValidationException::withMessages([
                    'catatan' => "Periode {$periode->kode} sudah ditutup, catatan tidak bisa diverifikasi lagi.",
                ]);
            }
        }
    }

    private function catat(Model $catatan, User $verifikator, KeputusanVerifikasi $keputusan, ?AlasanTolak $alasan, ?string $teks): void
    {
        $catatan->verifikasi()->create([
            'verifikator_id' => $verifikator->id,
            'keputusan' => $keputusan,
            'alasan' => $alasan,
            'catatan' => $teks,
        ]);

        activity('verifikasi')
            ->performedOn($catatan)
            ->causedBy($verifikator)
            ->event($keputusan->value)
            ->withProperties(['alasan' => $alasan?->value, 'catatan' => $teks])
            ->log($keputusan === KeputusanVerifikasi::Disetujui ? 'Catatan disetujui' : 'Catatan dikembalikan untuk revisi');
    }
}
