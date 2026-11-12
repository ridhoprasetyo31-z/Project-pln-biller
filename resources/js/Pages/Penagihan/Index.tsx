import { Link, router, usePage } from '@inertiajs/react';
import { FilePlus2, LoaderCircle } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatCard from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif, formatTanggal } from '@/lib/format';
import { statusCatatan, type StatusCatatan } from '@/lib/status';
import { rupiah, STATUS_INVOICE, type StatusInvoice } from '@/lib/tagihan';
import { cn } from '@/lib/utils';
import type { Option, PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    nomor: string;
    periode: string;
    pelanggan: { idpel: string; nama: string; alamat: string; rute: string | null };
    pemakaian_kwh: number;
    total: number;
    jatuh_tempo: string;
    status: StatusInvoice;
    status_label: string;
    dibayar_at: string | null;
    lembar_menunggak: number;
    kunjungan: { hasil_label: string; status: StatusCatatan; waktu: string | null } | null;
}

type Props = PageProps<{
    invoice: Paginated<Row>;
    filters: { q?: string; status: string; periode_id?: string; rute_id?: string };
    ringkasan: Record<'belum_bayar' | 'menunggak' | 'lunas', { jumlah: number; nominal: number }>;
    periodeOptions: Option<number>[];
    ruteOptions: Option<number>[];
    can: { susulan: boolean };
}>;

export default function PenagihanIndex() {
    const { invoice, filters, ringkasan, periodeOptions, ruteOptions, can, auth } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', status: filters.status, periode_id: filters.periode_id ?? '', rute_id: filters.rute_id ?? '' });
    const [menyusul, setMenyusul] = useState(false);
    const biller = auth.user.role === 'biller';
    const hariIni = new Date().toISOString().slice(0, 10);

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Pelanggan',
            cell: ({ row: { original: r } }) => (
                <div className="min-w-0">
                    <p className="font-medium">{r.pelanggan.nama}</p>
                    <p className="text-xs text-muted-ink">
                        <span className="font-mono">{r.pelanggan.idpel}</span>
                        {r.pelanggan.rute ? ` · ${r.pelanggan.rute}` : ''} · {r.pelanggan.alamat}
                    </p>
                </div>
            ),
        },
        {
            header: 'Rekening',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-mono text-[13px]">{r.nomor}</p>
                    <p className="text-xs text-muted-ink">{formatNumber(r.pemakaian_kwh, 1)} kWh</p>
                </div>
            ),
        },
        {
            header: 'Tagihan',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono font-medium">{rupiah(row.original.total)}</span>,
        },
        {
            header: 'Jatuh tempo',
            cell: ({ row: { original: r } }) => {
                const lewat = r.status !== 'lunas' && r.jatuh_tempo < hariIni;
                return (
                    <span className={cn('text-[13px]', lewat && 'text-error-ink')}>
                        {formatTanggal(r.jatuh_tempo, 'd MMM yyyy')}
                        {r.status === 'lunas' && r.dibayar_at && <span className="block text-xs text-muted-ink">Dibayar {formatTanggal(r.dibayar_at, 'd MMM')}</span>}
                    </span>
                );
            },
        },
        {
            header: 'Status',
            cell: ({ row: { original: r } }) => (
                <div className="grid justify-items-start gap-1">
                    <StatusChip {...STATUS_INVOICE[r.status]} />
                    {r.lembar_menunggak >= 2 && <span className="text-xs font-medium text-error-ink">{r.lembar_menunggak} lembar menunggak</span>}
                </div>
            ),
        },
        {
            header: 'Kunjungan terakhir',
            cell: ({ row: { original: r } }) =>
                r.kunjungan ? (
                    <div className="text-[13px]">
                        <p>{r.kunjungan.hasil_label}</p>
                        <p className="text-xs text-muted-ink">
                            {formatRelatif(r.kunjungan.waktu)} · {statusCatatan(r.kunjungan.status).label.toLowerCase()}
                        </p>
                    </div>
                ) : (
                    <span className="text-[13px] text-muted-ink">Belum dikunjungi</span>
                ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-28 text-right' },
            cell: ({ row: { original: r } }) => (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={route('penagihan.show', r.id)}>{biller && r.status !== 'lunas' ? 'Tagih' : 'Lihat'}</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Penagihan">
            <PageHeader
                title="Penagihan"
                description={biller ? 'Rekening pelanggan di rute Anda. Catat setiap kunjungan penagihan dengan foto dan lokasi.' : 'Rekening pelanggan di unit Anda, diterbitkan dari catatan meter pascabayar yang disetujui.'}
                actions={
                    can.susulan && (
                        <Button
                            variant="secondary"
                            disabled={menyusul}
                            onClick={() =>
                                router.post(route('penagihan.susulan'), {}, { preserveScroll: true, onStart: () => setMenyusul(true), onFinish: () => setMenyusul(false) })
                            }
                        >
                            {menyusul ? <LoaderCircle className="animate-spin" /> : <FilePlus2 />}
                            Terbitkan invoice susulan
                        </Button>
                    )
                }
            />

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <StatCard label="Belum bayar" value={formatNumber(ringkasan.belum_bayar.jumlah)} caption={rupiah(ringkasan.belum_bayar.nominal)} />
                <StatCard
                    label="Menunggak"
                    value={formatNumber(ringkasan.menunggak.jumlah)}
                    valueClassName={ringkasan.menunggak.jumlah > 0 ? 'text-pln-red' : undefined}
                    caption={`${rupiah(ringkasan.menunggak.nominal)} lewat jatuh tempo`}
                />
                <StatCard label="Lunas" value={formatNumber(ringkasan.lunas.jumlah)} caption={rupiah(ringkasan.lunas.nominal)} />
            </div>

            <Toolbar>
                <SegmentedTabs
                    label="Status invoice"
                    value={f.values.status}
                    onChange={(v) => f.set('status', v)}
                    items={[
                        { value: 'tagih', label: 'Perlu ditagih' },
                        { value: 'menunggak', label: 'Menunggak' },
                        { value: 'lunas', label: 'Lunas' },
                        { value: 'semua', label: 'Semua' },
                    ]}
                />
                <SearchInput className="md:w-64" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama, ID, atau nomor" />
                <NativeSelect className="md:w-44" aria-label="Periode" value={f.values.periode_id} onChange={(e) => f.set('periode_id', e.target.value)}>
                    <option value="">Semua periode</option>
                    {periodeOptions.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </NativeSelect>
                {ruteOptions.length > 1 && (
                    <NativeSelect className="md:w-52" aria-label="Rute" value={f.values.rute_id} onChange={(e) => f.set('rute_id', e.target.value)}>
                        <option value="">Semua rute</option>
                        {ruteOptions.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </Toolbar>

            <DataTable
                columns={columns}
                data={invoice}
                getRowId={(r) => String(r.id)}
                rowClassName={(r) => (r.status === 'menunggak' ? 'bg-error-row' : undefined)}
                empty="Tidak ada rekening yang cocok dengan filter."
            />
        </AppLayout>
    );
}
