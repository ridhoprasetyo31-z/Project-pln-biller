import { usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { StatusTone } from '@/lib/status';
import type { Option, PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    idpel: string;
    nama: string;
    alamat: string;
    rute: string;
    urutan: number;
    tarif: string;
    jenis_label: string;
    status: 'aktif' | 'putus_sementara' | 'bongkar';
    status_label: string;
    lembar_menunggak: number;
}

type Props = PageProps<{
    pelanggan: Paginated<Row>;
    filters: { status?: string; rute_id?: string; q?: string };
    statusOptions: Option[];
    ruteOptions: Option<number>[];
}>;

const TONE: Record<Row['status'], StatusTone> = { aktif: 'success', putus_sementara: 'danger', bongkar: 'neutral' };

export default function DataPelanggan() {
    const { pelanggan, filters, statusOptions, ruteOptions } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', rute_id: filters.rute_id ?? '', status: filters.status ?? '' });

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Urutan',
            meta: { className: 'w-24' },
            cell: ({ row: { original: p } }) => (
                <div className="font-mono text-[13px]">
                    <p>{String(p.urutan).padStart(3, '0')}</p>
                    <p className="text-xs text-muted-ink">{p.rute}</p>
                </div>
            ),
        },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-medium">{p.nama}</p>
                    <p className="text-xs text-muted-ink">
                        <span className="font-mono">{p.idpel}</span> · {p.alamat}
                    </p>
                </div>
            ),
        },
        {
            header: 'Tarif',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p>{p.tarif}</p>
                    <p className="text-xs text-muted-ink">{p.jenis_label}</p>
                </div>
            ),
        },
        {
            header: 'Tunggakan',
            meta: { className: 'text-right' },
            cell: ({ row: { original: p } }) =>
                p.lembar_menunggak > 0 ? <span className="font-medium text-error-ink">{p.lembar_menunggak} lembar</span> : <span className="text-muted-ink">–</span>,
        },
        { header: 'Status', cell: ({ row: { original: p } }) => <StatusChip label={p.status_label} tone={TONE[p.status]} /> },
    ];

    return (
        <AppLayout title="Pelanggan">
            <PageHeader
                title="Pelanggan"
                description={`${formatNumber(pelanggan.total)} pelanggan di unit Anda. Perubahan data diajukan biller lewat PDIL; data master dikelola super admin.`}
            />
            <Toolbar>
                <SearchInput className="md:w-72" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama, ID, atau alamat" />
                <NativeSelect className="md:w-52" aria-label="Rute" value={f.values.rute_id} onChange={(e) => f.set('rute_id', e.target.value)}>
                    <option value="">Semua rute</option>
                    {ruteOptions.map((r) => (
                        <option key={r.value} value={r.value}>
                            {r.label}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect className="md:w-48" aria-label="Status" value={f.values.status} onChange={(e) => f.set('status', e.target.value)}>
                    <option value="">Semua status</option>
                    {statusOptions.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>
            <DataTable columns={columns} data={pelanggan} getRowId={(p) => String(p.id)} empty="Tidak ada pelanggan yang cocok." />
        </AppLayout>
    );
}
