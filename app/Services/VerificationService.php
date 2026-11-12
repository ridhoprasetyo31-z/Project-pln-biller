<?php

namespace App\Services;

use App\Enums\AlasanTolak;
use App\Enums\KeputusanVerifikasi;
use App\Enums\StatusPeriode;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\Pdil;
use App\Models\PencatatanMeter;
use App\Models\Tusbug;
use App\Models\User;
use App\Notifications\CatatanDikembalikan;
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
            // Hanya catatan meter yang punya kolom disetujui_at (isFillable tidak bisa dipakai:
            // selalu true saat model di-unguard, misalnya di seeder).
            if ($catatan instanceof PencatatanMeter) {
                $perubahan['disetujui_at'] = now();
            }
            $catatan->update($perubahan);

            $this->catat($catatan, $verifikator, KeputusanVerifikasi::Disetujui, null, $catatanTeks);
            $this->setelahDisetujui($catatan);
        });
    }

    /**
     * Efek lanjutan per modul setelah catatan disetujui:
     * catat meter pascabayar → invoice terbit; kunjungan "lunas" → invoice lunas;
     * koreksi rekening → invoice & catatan meter diperbarui, skor dihitung ulang.
     */
    private function setelahDisetujui(Model $catatan): void
    {
        match (true) {
            $catatan instanceof PencatatanMeter => app(InvoiceGenerator::class)->buatUntuk($catatan),
            $catatan instanceof KunjunganPenagihan => app(KunjunganService::class)->terapkan($catatan),
            $catatan instanceof KoreksiRekening => app(KoreksiService::class)->terapkan($catatan),
            $catatan instanceof Pdil => app(PdilService::class)->terapkan($catatan),
            $catatan instanceof Tusbug => app(TusbugService::class)->terapkan($catatan),
            default => null,
        };
    }

    /** Beri tahu biller pemilik catatan lewat lonceng notifikasi. */
    private function kabariDikembalikan(Model $catatan, AlasanTolak $alasan, ?string $teks): void
    {
        [$label, $pemilikId, $pelanggan, $url] = match (true) {
            $catatan instanceof PencatatanMeter => ['Catat meter', $catatan->biller_id, $catatan->pelanggan?->nama,
                route("catat.{$catatan->jenis->value}", $catatan->pelanggan_id)],
            $catatan instanceof KunjunganPenagihan => ['Kunjungan penagihan', $catatan->biller_id, $catatan->invoice?->pelanggan?->nama,
                route('kunjungan.edit', $catatan)],
            $catatan instanceof KoreksiRekening => ['Koreksi rekening', $catatan->diajukan_oleh, $catatan->pelanggan?->nama,
                route('koreksi-rekening.edit', $catatan)],
            $catatan instanceof Pdil => ['PDIL', $catatan->biller_id, $catatan->nama, route('pdil.edit', $catatan)],
            $catatan instanceof Tusbug => ['TUSBUG', $catatan->biller_id, $catatan->pelanggan?->nama, route('tusbug.show', $catatan)],
            default => [null, null, null, null],
        };

        if ($label && $pemilikId) {
            User::find($pemilikId)?->notify(new CatatanDikembalikan($label, $pelanggan ?? 'Pelanggan', $alasan->label(), $teks, $url));
        }
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

        $this->kabariDikembalikan($catatan, $alasan, $catatanTeks);
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

        // Hanya catat meter yang terkunci saat periode ditutup; koreksi rekening justru
        // diajukan untuk periode yang sudah lewat.
        if ($catatan instanceof PencatatanMeter && $catatan->periode_id) {
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
