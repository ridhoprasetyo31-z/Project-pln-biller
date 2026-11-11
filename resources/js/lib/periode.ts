import { formatTanggal } from '@/lib/format';
import type { StatusTone } from '@/lib/status';

export type StatusPeriode = 'draft' | 'berjalan' | 'ditutup';

export interface PeriodeRingkas {
    id: number;
    kode: string;
    nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    status: StatusPeriode;
    status_label: string;
    ditutup_at: string | null;
    ditutup_oleh: string | null;
    bisa_diubah: boolean;
}

export const STATUS_PERIODE: Record<StatusPeriode, { label: string; tone: StatusTone }> = {
    draft: { label: 'Draf, belum dimulai', tone: 'neutral' },
    berjalan: { label: 'Berjalan', tone: 'info' },
    ditutup: { label: 'Ditutup', tone: 'success' },
};

/** "1 Okt – 31 Okt 2026". */
export function jadwal(p: Pick<PeriodeRingkas, 'tanggal_mulai' | 'tanggal_selesai'>): string {
    return `${formatTanggal(p.tanggal_mulai, 'd MMM')} – ${formatTanggal(p.tanggal_selesai, 'd MMM yyyy')}`;
}
