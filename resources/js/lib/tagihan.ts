import { formatNumber } from '@/lib/format';
import type { StatusMeta } from '@/lib/status';

export type StatusInvoice = 'belum_bayar' | 'lunas' | 'menunggak' | 'dibatalkan';

export const STATUS_INVOICE: Record<StatusInvoice, StatusMeta> = {
    belum_bayar: { label: 'Belum bayar', tone: 'waiting' },
    menunggak: { label: 'Menunggak', tone: 'danger' },
    lunas: { label: 'Lunas', tone: 'success' },
    dibatalkan: { label: 'Dibatalkan', tone: 'neutral' },
};

export function rupiah(n: number | null | undefined): string {
    if (n === null || n === undefined || Number.isNaN(n)) return '–';
    return `Rp${formatNumber(Math.round(n))}`;
}

/** Sama dengan InvoiceGenerator::rincian di server. */
export function rincianTagihan(kwh: number, harga: number, beban: number, ppjPersen: number) {
    const k = Math.max(0, Math.round(kwh * 10) / 10);
    const biayaPemakaian = Math.round(k * harga);
    const subtotal = biayaPemakaian + Math.round(beban);
    const pajak = Math.round((subtotal * ppjPersen) / 100);
    return { kwh: k, biayaPemakaian, subtotal, pajak, total: subtotal + pajak };
}

/** "18.912,4" / "18912,4" → 18912.4 */
export function keAngka(v: string): number | null {
    if (v.trim() === '') return null;
    const n = Number(v.replace(/\./g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}
