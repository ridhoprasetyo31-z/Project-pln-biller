import { usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { PageProps } from '@/types';

interface Row {
    id: number;
    kode: string;
    nama: string;
    kelurahan: string;
    is_active: boolean;
    pemegang: string | null;
    pelanggan_aktif: number;
    penugasan: { biller: string; target: number; terkirim: number } | null;
}

type Props = PageProps<{ rute: Row[]; periode: { kode: string; nama: string } | null }>;

export default function DataRute() {
    const { rute, periode } = usePage<Props>().props;

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Rute',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-mono text-[13px] font-medium">{r.kode}</p>
                    <p className="text-xs text-muted-ink">
                        {r.nama} · {r.kelurahan}
                    </p>
                </div>
            ),
        },
        { header: 'Biller pemegang', cell: ({ row }) => row.original.pemegang ?? <span className="text-error-ink">Belum ada</span> },
        { header: 'Pelanggan aktif', meta: { className: 'text-right' }, cell: ({ row }) => formatNumber(row.original.pelanggan_aktif) },
        {
            header: periode ? `Progres ${periode.nama}` : 'Progres',
            meta: { className: 'w-64' },
            cell: ({ row: { original: r } }) =>
                r.penugasan ? (
                    <div>
                        <div className="flex justify-between text-[13px]">
                            <span>{r.penugasan.biller}</span>
                            <span className="font-mono">
                                {formatNumber(r.penugasan.terkirim)} / {formatNumber(r.penugasan.target)}
                            </span>
                        </div>
                        <ProgressBar className="mt-1.5" value={r.penugasan.target ? (r.penugasan.terkirim / r.penugasan.target) * 100 : 0} />
                    </div>
                ) : (
                    <span className="text-[13px] text-muted-ink">Tidak ditugaskan</span>
                ),
        },
        { header: 'Status', cell: ({ row }) => (row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />) },
    ];

    return (
        <AppLayout title="Rute">
            <PageHeader title="Rute" description="Rute baca meter di unit Anda beserta progres periode berjalan. Penugasan diatur super admin di menu Periode." />
            <DataTable columns={columns} data={rute} getRowId={(r) => String(r.id)} empty="Belum ada rute di unit ini." />
        </AppLayout>
    );
}
