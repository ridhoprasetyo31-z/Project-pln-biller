import { usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { STATUS_CATATAN, statusCatatan, type StatusCatatan } from '@/lib/status';
import type { Option, PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    periode: string;
    pelanggan: { idpel: string; nama: string; alamat: string };
    jenis: 'pascabayar' | 'prabayar';
    stand_lalu: number | null;
    stand_kini: number | null;
    pemakaian_kwh: number | null;
    sisa_kwh: number | null;
    status: StatusCatatan;
    anomali: string | null;
    jumlah_revisi: number;
    dikirim_at: string | null;
    tepat_waktu: boolean | null;
}

type Props = PageProps<{
    catatan: Paginated<Row>;
    filters: { q?: string; periode_id?: string; status?: string };
    periodeOptions: Option<number>[];
}>;

export default function RiwayatIndex() {
    const { catatan, filters, periodeOptions } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', periode_id: filters.periode_id ?? '', status: filters.status ?? '' });

    const columns: ColumnDef<Row>[] = [
        { header: 'Periode', meta: { className: 'w-24' }, cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.periode}</span> },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-medium">{r.pelanggan.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">{r.pelanggan.idpel}</p>
                </div>
            ),
        },
        {
            header: 'Angka dicatat',
            meta: { className: 'text-right' },
            cell: ({ row: { original: r } }) =>
                r.jenis === 'pascabayar' ? (
                    <div className="font-mono text-[13px]">
                        <p>{r.stand_kini !== null ? formatNumber(r.stand_kini, 1) : '–'}</p>
                        <p className="text-xs text-muted-ink">{r.pemakaian_kwh !== null ? `${formatNumber(r.pemakaian_kwh, 1)} kWh` : ''}</p>
                    </div>
                ) : (
                    <div className="font-mono text-[13px]">
                        <p>{r.sisa_kwh !== null ? formatNumber(r.sisa_kwh, 2) : '–'}</p>
                        <p className="text-xs text-muted-ink">sisa kWh</p>
                    </div>
                ),
        },
        {
            header: 'Dikirim',
            cell: ({ row: { original: r } }) => (
                <div className="text-[13px]">
                    <p>{formatTanggalWaktu(r.dikirim_at)}</p>
                    {r.tepat_waktu === false && <p className="text-xs text-error-ink">Lewat batas</p>}
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row: { original: r } }) => (
                <div className="grid justify-items-start gap-1">
                    <StatusChip {...statusCatatan(r.status, r.anomali)} />
                    {r.jumlah_revisi > 0 && <span className="text-xs text-muted-ink">Direvisi {r.jumlah_revisi}×</span>}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Riwayat catatan">
            <PageHeader title="Riwayat catatan" description={`${formatNumber(catatan.total)} catatan yang sudah Anda kirim.`} />

            <Toolbar>
                <SearchInput className="md:w-72" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau ID pelanggan" />
                <NativeSelect className="md:w-48" aria-label="Filter periode" value={f.values.periode_id} onChange={(e) => f.set('periode_id', e.target.value)}>
                    <option value="">Semua periode</option>
                    {periodeOptions.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect className="md:w-60" aria-label="Filter status" value={f.values.status} onChange={(e) => f.set('status', e.target.value)}>
                    <option value="">Semua status</option>
                    {(Object.keys(STATUS_CATATAN) as StatusCatatan[])
                        .filter((s) => s !== 'draft')
                        .map((s) => (
                            <option key={s} value={s}>
                                {STATUS_CATATAN[s].label}
                            </option>
                        ))}
                </NativeSelect>
            </Toolbar>

            <DataTable columns={columns} data={catatan} getRowId={(r) => String(r.id)} empty="Belum ada catatan yang cocok." />
        </AppLayout>
    );
}
