import { router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import type { PageProps, UnitOption } from '@/types';

interface Row {
    kode: string;
    nama: string;
    final: boolean;
    jumlah_biller: number;
    rata: number;
    dihitung_at: string | null;
}

type Props = PageProps<{
    periode: Row[];
    unitId: number | null;
    unitOptions: UnitOption[] | null;
    unitNama: string;
}>;

export default function LaporanIndex() {
    const { periode, unitId, unitOptions, unitNama } = usePage<Props>().props;
    const q = unitId ? `?unit_id=${unitId}` : '';

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Periode',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-medium">{p.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">{p.kode}</p>
                </div>
            ),
        },
        {
            header: 'Status skor',
            cell: ({ row }) => (row.original.final ? <StatusChip label="Final" tone="success" /> : <StatusChip label="Sementara" tone="waiting" />),
        },
        { header: 'Biller', meta: { className: 'text-right' }, cell: ({ row }) => formatNumber(row.original.jumlah_biller) },
        {
            header: 'Rata-rata skor',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.rata, 2)}</span>,
        },
        { header: 'Dihitung', cell: ({ row }) => <span className="text-[13px] text-muted-ink">{formatTanggalWaktu(row.original.dihitung_at)}</span> },
        {
            id: 'unduh',
            header: () => <span className="sr-only">Unduh</span>,
            meta: { className: 'text-right' },
            cell: ({ row: { original: p } }) => (
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" size="sm" asChild>
                        <a href={route('laporan.excel', p.kode) + q}>
                            <FileSpreadsheet />
                            Excel
                        </a>
                    </Button>
                    <Button variant="secondary" size="sm" asChild>
                        <a href={route('laporan.pdf', p.kode) + q}>
                            <FileText />
                            PDF
                        </a>
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Laporan">
            <PageHeader
                title="Laporan peringkat"
                description={`Unduh peringkat biller per periode untuk ${unitNama}. Excel berisi angka mentah tiap komponen; PDF siap cetak.`}
                actions={
                    unitOptions && (
                        <NativeSelect
                            className="w-48"
                            aria-label="Unit"
                            value={unitId ?? ''}
                            onChange={(e) => router.get(route('laporan.index'), e.target.value ? { unit_id: e.target.value } : {}, { preserveScroll: true })}
                        >
                            <option value="">Semua unit</option>
                            {unitOptions.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nama}
                                </option>
                            ))}
                        </NativeSelect>
                    )
                }
            />
            <DataTable columns={columns} data={periode} getRowId={(p) => p.kode} empty="Belum ada skor yang dihitung." />
        </AppLayout>
    );
}
