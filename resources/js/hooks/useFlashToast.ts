import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import type { SharedProps } from '@/types';

/** Tampilkan pesan flash dari server sebagai toast. */
export function useFlashToast() {
    const { flash } = usePage<SharedProps>().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);
}
