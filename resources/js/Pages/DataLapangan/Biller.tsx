import { usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import MeterDigits from '@/Components/shared/MeterDigits';
import PageHeader from '@/Components/shared/PageHeader';
import { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import UserAvatar from '@/Components/shared/UserAvatar';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif } from '@/lib/format';
import { STATUS_KINERJA } from '@/lib/kinerja';
import type { PageProps } from '@/types';

interface Row {
    id: number;
    name: string;
    nip: string;
    phone: string | null;
    foto_url: string | null;
    is_active: boolean;
    rute: string[];
    target: number;
    terkirim: number;
    skor: number | null;
    peringkat: number | null;
    status: 'pembinaan' | 'dipantau' | null;
    last_login_at: string | null;
}

type Props = PageProps<{ biller: Row[]; periodeFinal: string | null; periodeBerjalan: string | null }>;

export default function DataBiller() {
    const { biller, periodeFinal, periodeBerjalan } = usePage<Props>().props;

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Biller',
            cell: ({ row: { original: b } }) => (
                <div className="flex items-center gap-3">
                    <UserAvatar name={b.name} src={b.foto_url} size={34} />
                    <div>
                        <p className="font-medium">{b.name}</p>
                        <p className="font-mono text-xs text-muted-ink">{b.nip}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Kontak',
            cell: ({ row: { original: b } }) => (
                <div className="text-[13px]">
                    <p className="font-mono">{b.phone ?? '–'}</p>
                    <p className="text-xs text-muted-ink">{b.last_login_at ? `Masuk ${formatRelatif(b.last_login_at)}` : 'Belum pernah masuk'}</p>
                </div>
            ),
        },
        { header: 'Rute', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.rute.join(', ') || '–'}</span> },
        {
            header: periodeBerjalan ? `Progres ${periodeBerjalan}` : 'Progres',
            meta: { className: 'w-48' },
            cell: ({ row: { original: b } }) =>
                b.target > 0 ? (
                    <div>
                        <p className="font-mono text-[13px]">
                            {formatNumber(b.terkirim)} / {formatNumber(b.target)}
                        </p>
                        <ProgressBar className="mt-1.5" value={(b.terkirim / b.target) * 100} />
                    </div>
                ) : (
                    <span className="text-[13px] text-muted-ink">Tidak ditugaskan</span>
                ),
        },
        {
            header: periodeFinal ? `Skor ${periodeFinal}` : 'Skor',
            cell: ({ row: { original: b } }) =>
                b.skor !== null ? (
                    <div className="grid justify-items-start gap-1">
                        <MeterDigits value={b.skor} decimals={1} size="sm" />
                        {b.status && <StatusChip className="py-0.5 text-[11px]" {...STATUS_KINERJA[b.status]} />}
                    </div>
                ) : (
                    <span className="text-muted-ink">–</span>
                ),
        },
        { header: 'Status akun', cell: ({ row }) => (row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />) },
    ];

    return (
        <AppLayout title="Biller">
            <PageHeader title="Biller" description="Biller di unit Anda: rute, progres periode berjalan, dan skor final terakhir. Akun dikelola super admin." />
            <DataTable columns={columns} data={biller} getRowId={(b) => String(b.id)} empty="Belum ada biller di unit ini." />
        </AppLayout>
    );
}
