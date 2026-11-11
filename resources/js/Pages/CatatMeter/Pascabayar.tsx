import { usePage } from '@inertiajs/react';

import CatatMeterForm from '@/Components/catat/CatatMeterForm';
import type { CatatMeterProps } from '@/types/catat';

export default function CatatPascabayar() {
    const props = usePage<CatatMeterProps>().props;
    // key per pelanggan: form selalu bersih saat pindah ke pelanggan berikutnya.
    return <CatatMeterForm key={props.pelanggan.id} props={props} jenis="pascabayar" />;
}
