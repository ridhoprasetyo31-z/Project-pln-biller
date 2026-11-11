import { format as formatDate, formatDistanceToNow, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';

const TZ = 'Asia/Jakarta';

/** 1204 → "1.204"; 97.8 dengan 1 desimal → "97,8". */
export function formatNumber(value: number | null | undefined, decimals = 0): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '–';
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

/** 97.8 → "97,8%". Nilai dalam satuan persen (0–100). */
export function formatPercent(value: number | null | undefined, decimals = 1): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '–';
    return `${formatNumber(value, decimals)}%`;
}

/** Rupiah tanpa desimal: 152000 → "Rp152.000". */
export function formatRupiah(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '–';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function toDate(value: string | Date): Date {
    return typeof value === 'string' ? parseISO(value) : value;
}

/**
 * Tanggal sesuai zona Asia/Jakarta.
 * pola default "d MMMM yyyy" → "11 November 2026".
 */
export function formatTanggal(value: string | Date | null | undefined, pola = 'd MMMM yyyy'): string {
    if (!value) return '–';
    const date = toDate(value);
    // Geser ke waktu dinding Jakarta agar tampilan konsisten di perangkat mana pun.
    const jakarta = new Date(date.toLocaleString('en-US', { timeZone: TZ }));
    return formatDate(jakarta, pola, { locale: id });
}

export function formatTanggalWaktu(value: string | Date | null | undefined): string {
    return formatTanggal(value, "d MMM yyyy, HH.mm 'WIB'");
}

export function formatRelatif(value: string | Date | null | undefined): string {
    if (!value) return '–';
    return formatDistanceToNow(toDate(value), { addSuffix: true, locale: id });
}

export function hariIni(): string {
    return formatTanggal(new Date(), 'EEEE, d MMMM yyyy');
}
