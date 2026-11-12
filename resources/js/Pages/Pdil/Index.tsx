import { Link, router, usePage } from '@inertiajs/react';
import { Plus, UserPen, UserPlus } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import { STATUS_CATATAN, statusCatatan, type StatusCatatan } from '@/lib/status';
import type { Option, PageProps, Paginated } from '@/types';

interface Row {
    id: number;
    jenis: 'pelanggan_baru' | 'perubahan_data';
    jenis_label: string;
    nama: string;
    idpel: string | null;
    alamat: string;
    rute: string;
    biller: string;
    status: StatusCatatan;
    anomali: string | null;
    waktu: string;
}

type Props = PageProps<{
    pdil: Paginated<Row>;
    filters: { status?: string; jenis?: string; q?: string };
    jenisOptions: Option[];
    bisaAjukan: boolean;
}>;

export default function PdilIndex() {
    const { pdil, filters, jenisOptions, bisaAjukan, auth } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', jenis: filters.jenis ?? '', status: filters.status ?? '' });
    const [cariPelanggan, setCariPelanggan] = useState(false);

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Pelanggan',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-medium">{r.nama}</p>
                    <p className="text-xs text-muted-ink">
                        <span className="font-mono">{r.idpel ?? 'Belum ada ID'}</span> · {r.alamat}
                    </p>
                </div>
            ),
        },
        { header: 'Jenis', cell: ({ row }) => row.original.jenis_label },
        { header: 'Rute', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.rute}</span> },
        ...(auth.user.role === 'koordinator_admin' ? [{ header: 'Biller', cell: ({ row }: { row: { original: Row } }) => row.original.biller } as ColumnDef<Row>] : []),
        { header: 'Diajukan', cell: ({ row }) => <span className="text-[13px] text-muted-ink">{formatTanggalWaktu(row.original.waktu)}</span> },
        { header: 'Status', cell: ({ row }) => <StatusChip {...statusCatatan(row.original.status, row.original.anomali)} /> },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-24 text-right' },
            cell: ({ row }) => (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={route('pdil.show', row.original.id)}>Lihat</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="PDIL">
            <PageHeader
                title="PDIL"
                description="Pendataan pelanggan: usulan pelanggan baru dan perubahan data. Setelah disetujui koordinator, data langsung masuk ke data pelanggan."
                actions={
                    bisaAjukan && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button>
                                    <Plus />
                                    Ajukan PDIL
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onSelect={() => router.visit(route('pdil.create'))}>
                                    <UserPlus />
                                    Pelanggan baru
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => setCariPelanggan(true)}>
                                    <UserPen />
                                    Perubahan data pelanggan
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )
                }
            />

            <Toolbar>
                <SegmentedTabs
                    label="Jenis PDIL"
                    value={f.values.jenis}
                    onChange={(v) => f.set('jenis', v)}
                    items={[{ value: '', label: 'Semua' }, ...jenisOptions.map((j) => ({ value: j.value, label: j.label }))]}
                />
                <SearchInput className="md:w-64" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau ID" />
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

            <DataTable columns={columns} data={pdil} getRowId={(r) => String(r.id)} empty="Belum ada PDIL." />

            <CariPelangganDialog open={cariPelanggan} onOpenChange={setCariPelanggan} />
        </AppLayout>
    );
}

function CariPelangganDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
    const [idpel, setIdpel] = useState('');
    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Perubahan data pelanggan"
            description="Masukkan ID pelanggan di rute Anda. Data saat ini akan terisi di formulir, ubah bagian yang berbeda di lapangan."
            submitLabel="Lanjut"
            onSubmit={() => router.get(route('pdil.create'), { idpel })}
        >
            <FormField id="idpel" label="ID pelanggan" required>
                <Input id="idpel" inputMode="numeric" maxLength={12} className="font-mono" value={idpel} onChange={(e) => setIdpel(e.target.value.replace(/\D/g, ''))} />
            </FormField>
        </FormDialog>
    );
}
