import { router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { useState } from 'react';

/**
 * State untuk dialog konfirmasi (hapus, tutup periode, dst.):
 * simpan baris target, lalu jalankan request saat dikonfirmasi.
 */
export function useConfirmAction<T>() {
    const [target, setTarget] = useState<T | null>(null);
    const [processing, setProcessing] = useState(false);

    const run = (method: 'delete' | 'post' | 'patch', url: string, data: RequestPayload = {}) =>
        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setTarget(null);
            },
        });

    return {
        target,
        open: target !== null,
        ask: (row: T) => setTarget(row),
        close: () => setTarget(null),
        processing,
        run,
    };
}
