import { Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { STATUS_CATATAN, statusCatatan, type StatusCatatan } from '@/lib/status';
import { rupiah } from '@/lib/tagihan';
import { cn } from '@/lib/utils';
import type { PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    pelanggan: { idpel: string; nama: string };
    periode: string;
    pengaju: string;
    stand_lama: number;
    stand_baru: number;
    tagihan_lama: number;
    tagihan_baru: number;
    penyebab_label: string;
    status: StatusCatatan;
    waktu: string;
}

type Props = PageProps<{
    koreksi: Paginated<Row>;
    filters: { status?: string; q?: string };
    bisaAjukan: boolean;
}>;

export default function KoreksiIndex() {
    const { koreksi, filters, bisaAjukan } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', status: filters.status ?? '' });
    const [ajukan, setAjukan] = useState(false);

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Pelanggan',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-medium">{r.pelanggan.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">
                        {r.pelanggan.idpel} · {r.periode}
                    </p>
                </div>
            ),
        },
        {
            header: 'Stand',
            cell: ({ row: { original: r } }) => (
                <span className="font-mono text-[13px]">
                    {formatNumber(r.stand_lama, 1)} → {formatNumber(r.stand_baru, 1)}
                </span>
            ),
        },
        {
            header: 'Tagihan',
            meta: { className: 'text-right' },
            cell: ({ row: { original: r } }) => {
                const selisih = r.tagihan_baru - r.tagihan_lama;
                return (
                    <div className="font-mono text-[13px]">
                        <p>{rupiah(r.tagihan_baru)}</p>
                        <p className={cn('text-xs', selisih < 0 ? 'text-success-ink' : 'text-error-ink')}>
                            {selisih < 0 ? '−' : '+'}
                            {rupiah(Math.abs(selisih))}
                        </p>
                    </div>
                );
            },
        },
        {
            header: 'Penyebab',
            cell: ({ row: { original: r } }) => (
                <div className="text-[13px]">
                    <p>{r.penyebab_label}</p>
                    <p className="text-xs text-muted-ink">
                        {r.pengaju} · {formatTanggalWaktu(r.waktu)}
                    </p>
                </div>
            ),
        },
        { header: 'Status', cell: ({ row }) => <StatusChip {...statusCatatan(row.original.status)} /> },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-24 text-right' },
            cell: ({ row }) => (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={route('koreksi-rekening.show', row.original.id)}>Lihat</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Koreksi rekening">
            <PageHeader
                title="Koreksi rekening"
                description="Perbaikan stand dan tagihan yang sudah terbit. Disetujui koordinator → invoice diperbarui; penyebab salah catat biller mengurangi akurasi."
                actions={
                    bisaAjukan && (
                        <Button onClick={() => setAjukan(true)}>
                            <Plus />
                            Ajukan koreksi
                        </Button>
                    )
                }
            />

            <Toolbar>
                <SearchInput className="md:w-72" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau ID pelanggan" />
                <NativeSelect className="md:w-64" aria-label="Status" value={f.values.status} onChange={(e) => f.set('status', e.target.value)}>
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

            <DataTable columns={columns} data={koreksi} getRowId={(r) => String(r.id)} empty="Belum ada koreksi rekening." />

            <AjukanDialog open={ajukan} onOpenChange={setAjukan} />
        </AppLayout>
    );
}

function AjukanDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
    const [idpel, setIdpel] = useState('');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Ajukan koreksi rekening"
            description="Masukkan ID pelanggan. Rekening terbaru yang belum lunas akan dibuka. Bisa juga lewat tombol Ajukan koreksi di halaman detail penagihan."
            submitLabel="Lanjut"
            onSubmit={() => router.get(route('koreksi-rekening.create'), { idpel })}
        >
            <FormField id="idpel" label="ID pelanggan" required>
                <Input id="idpel" inputMode="numeric" maxLength={12} className="font-mono" value={idpel} onChange={(e) => setIdpel(e.target.value.replace(/\D/g, ''))} />
            </FormField>
        </FormDialog>
    );
}
