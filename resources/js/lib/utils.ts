import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Inisial dua huruf dari nama, contoh "Andi Saputra" → "AS". */
export function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    const first = parts[0][0] ?? '';
    const last = parts.length > 1 ? (parts[parts.length - 1][0] ?? '') : (parts[0][1] ?? '');
    return (first + last).toUpperCase();
}

/** Cek nama route Ziggy tanpa melempar galat. */
export function hasRoute(name: string): boolean {
    try {
        return route().has(name);
    } catch {
        return false;
    }
}
