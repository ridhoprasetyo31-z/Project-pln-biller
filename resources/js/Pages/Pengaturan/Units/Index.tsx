import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import RowActions from '@/Components/shared/RowActions';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { Option, PageProps, Paginated } from '@/types';

interface UnitRow {
    id: number;
    kode: string;
    nama: string;
    jenis: 'up3' | 'ulp';
    jenis_label: string;
    parent_id: number | null;
    parent: string | null;
    alamat: string | null;
    users_count: number;
    rute_count: number;
    children_count: number;
}

type Props = PageProps<{
    units: Paginated<UnitRow>;
    filters: { q?: string };
    options: { jenis: Option[]; up3: { id: number; kode: string; nama: string }[] };
}>;

export default function UnitsIndex() {
    const { units, filters, options } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '' });
    const [form, setForm] = useState<{ open: boolean; unit: UnitRow | null }>({ open: false, unit: null });
    const hapus = useConfirmAction<UnitRow>();

    const columns: ColumnDef<UnitRow>[] = [
        {
            header: 'Kode',
            meta: { className: 'w-28' },
            cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.kode}</span>,
        },
        {
            header: 'Unit',
            cell: ({ row: { original: u } }) => (
                <div>
                    <p className="font-medium">{u.nama}</p>
                    <p className="text-xs text-muted-ink">{u.alamat ?? 'Alamat belum diisi'}</p>
                </div>
            ),
        },
        { header: 'Jenis', cell: ({ row }) => row.original.jenis_label },
        { header: 'Induk', cell: ({ row }) => row.original.parent ?? '–' },
        {
            header: 'Pengguna',
            meta: { className: 'text-right' },
            cell: ({ row }) => formatNumber(row.original.users_count),
        },
        {
            header: 'Rute',
            meta: { className: 'text-right' },
            cell: ({ row }) => formatNumber(row.original.rute_count),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: u } }) => (
                <RowActions
                    label={u.nama}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, unit: u }) },
                        { label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(u) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Unit">
            <PageHeader
                title="Unit"
                description="UP3 dan ULP. Setiap ULP berada di bawah satu UP3; rute dan pengguna terikat ke unit."
                actions={
                    <Button onClick={() => setForm({ open: true, unit: null })}>
                        <Plus />
                        Tambah unit
                    </Button>
                }
            />

            <Toolbar>
                <SearchInput
                    className="md:w-72"
                    value={f.values.q}
                    onChange={(v) => f.set('q', v, { debounce: true })}
                    placeholder="Cari kode atau nama unit"
                />
            </Toolbar>

            <DataTable columns={columns} data={units} getRowId={(u) => String(u.id)} empty="Belum ada unit." />

            <UnitFormDialog
                key={form.unit?.id ?? 'baru'}
                open={form.open}
                unit={form.unit}
                options={options}
                onClose={() => setForm((s) => ({ ...s, open: false }))}
            />

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus unit?"
                description={
                    <>
                        <strong className="text-ink">{hapus.target?.nama}</strong> hanya bisa dihapus bila tidak punya
                        pengguna, rute, atau ULP di bawahnya.
                    </>
                }
                confirmLabel="Hapus unit"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.units.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function UnitFormDialog({
    open,
    unit,
    options,
    onClose,
}: {
    open: boolean;
    unit: UnitRow | null;
    options: Props['options'];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        kode: unit?.kode ?? '',
        nama: unit?.nama ?? '',
        jenis: (unit?.jenis ?? 'ulp') as 'up3' | 'ulp',
        parent_id: unit?.parent_id ? String(unit.parent_id) : '',
        alamat: unit?.alamat ?? '',
    });

    const close = () => {
        onClose();
        clearErrors();
        if (!unit) reset();
    };

    const submit = () => {
        transform((d) => ({ ...d, parent_id: d.jenis === 'up3' ? null : d.parent_id || null }));
        const opts = { preserveScroll: true, onSuccess: close };
        if (unit) put(route('pengaturan.units.update', unit.id), opts);
        else post(route('pengaturan.units.store'), opts);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title={unit ? `Ubah ${unit.nama}` : 'Tambah unit'}
            submitLabel={unit ? 'Simpan perubahan' : 'Tambah unit'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="jenis" label="Jenis unit" required error={errors.jenis}>
                <SegmentedTabs
                    label="Jenis unit"
                    value={data.jenis}
                    onChange={(v) => setData('jenis', v)}
                    items={options.jenis.map((j) => ({ value: j.value as 'up3' | 'ulp', label: j.label }))}
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-[160px_1fr]">
                <FormField id="kode" label="Kode" required error={errors.kode}>
                    <Input
                        id="kode"
                        className="font-mono"
                        value={data.kode}
                        onChange={(e) => setData('kode', e.target.value)}
                        aria-invalid={!!errors.kode}
                    />
                </FormField>
                <FormField id="nama" label="Nama unit" required error={errors.nama}>
                    <Input
                        id="nama"
                        placeholder={data.jenis === 'up3' ? 'Contoh: UP3 Pekanbaru' : 'Contoh: ULP Panam'}
                        value={data.nama}
                        onChange={(e) => setData('nama', e.target.value)}
                        aria-invalid={!!errors.nama}
                    />
                </FormField>
            </div>
            {data.jenis === 'ulp' && (
                <FormField id="parent_id" label="UP3 induk" required error={errors.parent_id}>
                    <NativeSelect
                        id="parent_id"
                        value={data.parent_id}
                        onChange={(e) => setData('parent_id', e.target.value)}
                        aria-invalid={!!errors.parent_id}
                    >
                        <option value="">Pilih UP3</option>
                        {options.up3.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama} ({u.kode})
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            )}
            <FormField id="alamat" label="Alamat kantor" error={errors.alamat}>
                <Input id="alamat" value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
