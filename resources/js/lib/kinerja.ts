import type { StatusMeta } from '@/lib/status';

export interface BarisPeringkat {
    biller_id: number;
    nama: string;
    nip: string;
    foto_url: string | null;
    unit: string | null;
    rute: { id: number; kode: string; nama: string }[];
    target: number;
    tercatat: number;
    terverifikasi: number;
    disetujui_tanpa_revisi: number;
    jumlah_tepat_waktu: number;
    jumlah_bukti_lengkap: number;
    akurasi: number;
    penyelesaian: number;
    ketepatan_waktu: number;
    bukti_lengkap: number;
    skor: number;
    skor_lalu: number | null;
    tren: number | null;
    peringkat: number;
    status: 'pembinaan' | 'dipantau' | null;
    is_final: boolean;
    dihitung_at: string | null;
}

export interface Bobot {
    akurasi: number;
    penyelesaian: number;
    tepat_waktu: number;
    bukti_lengkap: number;
}

export const STATUS_KINERJA: Record<'pembinaan' | 'dipantau', StatusMeta> = {
    pembinaan: { label: 'Perlu pembinaan', tone: 'danger' },
    dipantau: { label: 'Dipantau', tone: 'waiting' },
};

export const KOMPONEN = [
    { key: 'akurasi', bobot: 'akurasi', label: 'Akurasi' },
    { key: 'penyelesaian', bobot: 'penyelesaian', label: 'Penyelesaian' },
    { key: 'ketepatan_waktu', bobot: 'tepat_waktu', label: 'Tepat waktu' },
    { key: 'bukti_lengkap', bobot: 'bukti_lengkap', label: 'Bukti lengkap' },
] as const;

/** "Skor = 0,40·Akurasi + 0,30·Penyelesaian + …" */
export function rumusSkor(b: Bobot): string {
    const f = (n: number) => (n / 100).toFixed(2).replace('.', ',');
    return `Skor = ${f(b.akurasi)}·Akurasi + ${f(b.penyelesaian)}·Penyelesaian + ${f(b.tepat_waktu)}·Tepat waktu + ${f(b.bukti_lengkap)}·Bukti lengkap`;
}
