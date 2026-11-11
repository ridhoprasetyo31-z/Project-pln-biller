import { usePage } from '@inertiajs/react';

import CatatMeterForm from '@/Components/catat/CatatMeterForm';
import type { CatatMeterProps } from '@/types/catat';

export default function CatatPrabayar() {
    const props = usePage<CatatMeterProps>().props;
    return <CatatMeterForm key={props.pelanggan.id} props={props} jenis="prabayar" />;
}
