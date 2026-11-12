import { Link, usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { STATUS_CATATAN, statusCatatan, type StatusCatatan } from '@/lib/status';
import type { Option, PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    pelanggan: { idpel: string; nama: string; rute: string | null };
    biller: string;
    jenis: 'pascabayar' | 'prabayar';
    stand_kini: number | null;
    pemakaian_kwh: number | null;
    sisa_kwh: number | null;
    status: StatusCatatan;
    anomali: string | null;
    jumlah_revisi: number;
    dikirim_at: string | null;
}

type Props = PageProps<{
    catatan: Paginated<Row>;
    filters: { periode?: string; status?: string; rute_id?: string; q?: string };
    periodeOptions: Option[];
    ruteOptions: Option<number>[];
}>;

export default function DataPencatatan() {
    const { catatan, filters, periodeOptions, ruteOptions } = usePage<Props>().props;
    const f = useFilters({ periode: filters.periode ?? '', status: filters.status ?? '', rute_id: filters.rute_id ?? '', q: filters.q ?? '' });

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Pelanggan',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-medium">{r.pelanggan.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">
                        {r.pelanggan.idpel} · {r.pelanggan.rute}
                    </p>
                </div>
            ),
        },
        { header: 'Biller', cell: ({ row }) => row.original.biller },
        {
            header: 'Angka',
            meta: { className: 'text-right' },
            cell: ({ row: { original: r } }) => (
                <div className="font-mono text-[13px]">
                    {r.jenis === 'pascabayar' ? (
                        <>
                            <p>{r.stand_kini !== null ? formatNumber(r.stand_kini, 1) : '–'}</p>
                            <p className="text-xs text-muted-ink">{r.pemakaian_kwh !== null ? `${formatNumber(r.pemakaian_kwh, 1)} kWh` : ''}</p>
                        </>
                    ) : (
                        <p>Sisa {r.sisa_kwh !== null ? formatNumber(r.sisa_kwh, 2) : '–'}</p>
                    )}
                </div>
            ),
        },
        { header: 'Dikirim', cell: ({ row }) => <span className="text-[13px]">{formatTanggalWaktu(row.original.dikirim_at)}</span> },
        {
            header: 'Status',
            cell: ({ row: { original: r } }) => (
                <div className="grid justify-items-start gap-1">
                    <StatusChip {...statusCatatan(r.status, r.anomali)} />
                    {r.jumlah_revisi > 0 && <span className="text-xs text-muted-ink">Direvisi {r.jumlah_revisi}×</span>}
                </div>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-28 text-right' },
            cell: ({ row: { original: r } }) =>
                ['menunggu_verifikasi', 'ditandai'].includes(r.status) ? (
                    <Button variant="secondary" size="sm" asChild>
                        <Link href={route('verifikasi.index', { tab: r.status === 'ditandai' ? 'ditandai' : 'normal', tipe: 'pencatatan_meter', id: r.id })}>Periksa</Link>
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout title="Pencatatan">
            <PageHeader title="Pencatatan" description={`${formatNumber(catatan.total)} catatan meter di unit Anda pada periode terpilih.`} />
            <Toolbar>
                <NativeSelect className="md:w-48" aria-label="Periode" value={f.values.periode} onChange={(e) => f.set('periode', e.target.value)}>
                    {periodeOptions.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </NativeSelect>
                <SearchInput className="md:w-60" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau ID" />
                <NativeSelect className="md:w-52" aria-label="Rute" value={f.values.rute_id} onChange={(e) => f.set('rute_id', e.target.value)}>
                    <option value="">Semua rute</option>
                    {ruteOptions.map((r) => (
                        <option key={r.value} value={r.value}>
                            {r.label}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect className="md:w-60" aria-label="Status" value={f.values.status} onChange={(e) => f.set('status', e.target.value)}>
                    <option value="">Semua status</option>
                    {(Object.keys(STATUS_CATATAN) as StatusCatatan[]).map((s) => (
                        <option key={s} value={s}>
                            {STATUS_CATATAN[s].label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>
            <DataTable columns={columns} data={catatan} getRowId={(r) => String(r.id)} empty="Belum ada catatan." />
        </AppLayout>
    );
}
