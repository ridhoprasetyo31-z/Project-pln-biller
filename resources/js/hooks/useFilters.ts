import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type FilterValue = string | number | null | undefined;

/**
 * Sinkronkan filter tabel dengan query string. Setiap perubahan memuat
 * ulang data (kembali ke halaman 1). Teks pencarian ditunda 350 ms.
 */
export function useFilters<T extends Record<string, FilterValue>>(initial: T) {
    const [values, setValues] = useState<T>(initial);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    const kirim = (next: T) => {
        const query: Record<string, string> = {};
        for (const [key, value] of Object.entries(next)) {
            if (value !== null && value !== undefined && value !== '') query[key] = String(value);
        }
        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const set = <K extends keyof T>(key: K, value: T[K], opts: { debounce?: boolean } = {}) => {
        const next = { ...values, [key]: value };
        setValues(next);
        clearTimeout(timer.current);
        if (opts.debounce) {
            timer.current = setTimeout(() => kirim(next), 350);
        } else {
            kirim(next);
        }
    };

    const setMany = (patch: Partial<T>) => {
        const next = { ...values, ...patch };
        setValues(next);
        clearTimeout(timer.current);
        kirim(next);
    };

    const aktif = Object.values(values).some((v) => v !== null && v !== undefined && v !== '');

    return { values, set, setMany, aktif };
}
