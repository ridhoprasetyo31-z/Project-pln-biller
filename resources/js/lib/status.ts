/**
 * Peta status → teks kalimat utuh + nada warna chip.
 * Satu-satunya tempat untuk teks status (DESIGN: StatusChip).
 */
export type StatusTone = 'waiting' | 'danger' | 'success' | 'info' | 'neutral';

export interface StatusMeta {
    label: string;
    tone: StatusTone;
}

export type StatusCatatan = 'draft' | 'menunggu_verifikasi' | 'ditandai' | 'revisi' | 'disetujui';

export const STATUS_CATATAN: Record<StatusCatatan, StatusMeta> = {
    draft: { label: 'Draf, belum dikirim', tone: 'neutral' },
    menunggu_verifikasi: { label: 'Menunggu koordinator memverifikasi', tone: 'waiting' },
    ditandai: { label: 'Ditandai sistem', tone: 'danger' },
    revisi: { label: 'Dikembalikan ke biller untuk revisi', tone: 'danger' },
    disetujui: { label: 'Disetujui', tone: 'success' },
};

export const JENIS_ANOMALI: Record<string, string> = {
    stand_turun: 'stand turun',
    lonjakan: 'lonjakan pemakaian',
    pemakaian_nol: 'pemakaian nol',
    lokasi_jauh: 'lokasi jauh dari pelanggan',
    bukti_tidak_lengkap: 'bukti tidak lengkap',
    indikasi_pelanggaran: 'indikasi pelanggaran',
    lewat_jadwal: 'lewat jadwal',
};

/** Status catatan, dengan detail anomali bila ditandai sistem. Null = belum dicatat. */
export function statusCatatan(status: StatusCatatan | null | undefined, anomali?: string | null): StatusMeta {
    if (!status) return { label: 'Belum dicatat', tone: 'neutral' };
    const meta = STATUS_CATATAN[status];
    if (status === 'ditandai' && anomali) {
        return { ...meta, label: `Ditandai sistem: ${JENIS_ANOMALI[anomali] ?? anomali}` };
    }
    return meta;
}

/** Label anomali dengan huruf awal kapital, untuk chip di antrean. */
export function labelAnomali(jenis: string): string {
    const l = JENIS_ANOMALI[jenis] ?? jenis;
    return l.charAt(0).toUpperCase() + l.slice(1);
}

export const TONE_CLASS: Record<StatusTone, string> = {
    waiting: 'bg-pln-yellow-light text-pln-yellow-ink',
    danger: 'bg-error-tint text-error-ink',
    success: 'bg-success-tint text-success-ink',
    info: 'bg-pln-blue-light text-pln-blue-dark',
    neutral: 'bg-table-head text-muted-ink',
};
