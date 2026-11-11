import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import RowActions from '@/Components/shared/RowActions';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { PageProps, Paginated } from '@/types';

interface TarifRow {
    id: number;
    golongan: string;
    daya_min_va: number;
    daya_max_va: number;
    harga_per_kwh: number;
    biaya_beban: number;
    berlaku_mulai: string;
    berlaku_sampai: string | null;
    berlaku: boolean;
}

type Props = PageProps<{
    tarif: Paginated<TarifRow>;
    filters: { golongan?: string; status?: string };
    options: { golongan: string[] };
}>;

const rupiah = (n: number, desimal = 0) => `Rp${formatNumber(n, desimal)}`;

export default function TarifIndex() {
    const { tarif, filters, options } = usePage<Props>().props;
    const f = useFilters({ golongan: filters.golongan ?? '', status: filters.status ?? '' });
    const [form, setForm] = useState<{ open: boolean; tarif: TarifRow | null }>({ open: false, tarif: null });
    const hapus = useConfirmAction<TarifRow>();

    const columns: ColumnDef<TarifRow>[] = [
        {
            header: 'Golongan',
            meta: { className: 'w-28' },
            cell: ({ row }) => <span className="font-mono font-medium">{row.original.golongan}</span>,
        },
        {
            header: 'Rentang daya',
            cell: ({ row: { original: t } }) =>
                t.daya_min_va === t.daya_max_va
                    ? `${formatNumber(t.daya_min_va)} VA`
                    : `${formatNumber(t.daya_min_va)} – ${formatNumber(t.daya_max_va)} VA`,
        },
        {
            header: 'Harga per kWh',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{rupiah(row.original.harga_per_kwh, 2)}</span>,
        },
        {
            header: 'Biaya beban',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{rupiah(row.original.biaya_beban)}</span>,
        },
        {
            header: 'Masa berlaku',
            cell: ({ row: { original: t } }) => (
                <span className="text-[13px]">
                    {formatTanggal(t.berlaku_mulai, 'd MMM yyyy')} sampai{' '}
                    {t.berlaku_sampai ? formatTanggal(t.berlaku_sampai, 'd MMM yyyy') : 'sekarang'}
                </span>
            ),
        },
        {
            header: 'Status',
            cell: ({ row }) =>
                row.original.berlaku ? <StatusChip label="Berlaku" tone="success" /> : <StatusChip label="Tidak berlaku" tone="neutral" />,
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: t } }) => (
                <RowActions
                    label={`tarif ${t.golongan}`}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, tarif: t }) },
                        { label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(t) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Tarif">
            <PageHeader
                title="Tarif"
                description="Harga per kWh dan biaya beban per golongan. Tarif disalin ke invoice saat invoice dibuat."
                actions={
                    <Button onClick={() => setForm({ open: true, tarif: null })}>
                        <Plus />
                        Tambah tarif
                    </Button>
                }
            />

            <Toolbar>
                <SegmentedTabs
                    label="Filter masa berlaku"
                    value={f.values.status}
                    onChange={(v) => f.set('status', v)}
                    items={[
                        { value: '', label: 'Semua' },
                        { value: 'berlaku', label: 'Berlaku' },
                        { value: 'berakhir', label: 'Sudah berakhir' },
                    ]}
                />
                <NativeSelect
                    className="md:w-44"
                    aria-label="Filter golongan"
                    value={f.values.golongan}
                    onChange={(e) => f.set('golongan', e.target.value)}
                >
                    <option value="">Semua golongan</option>
                    {options.golongan.map((g) => (
                        <option key={g} value={g}>
                            {g}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>

            <DataTable columns={columns} data={tarif} getRowId={(t) => String(t.id)} empty="Belum ada tarif." />

            <TarifFormDialog
                key={form.tarif?.id ?? 'baru'}
                open={form.open}
                tarif={form.tarif}
                onClose={() => setForm((s) => ({ ...s, open: false }))}
            />

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus tarif?"
                description="Tarif yang sudah dipakai di invoice tidak bisa dihapus. Isi tanggal berakhir sebagai gantinya."
                confirmLabel="Hapus tarif"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.tarif.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function TarifFormDialog({ open, tarif, onClose }: { open: boolean; tarif: TarifRow | null; onClose: () => void }) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        golongan: tarif?.golongan ?? '',
        daya_min_va: tarif ? String(tarif.daya_min_va) : '',
        daya_max_va: tarif ? String(tarif.daya_max_va) : '',
        harga_per_kwh: tarif ? String(tarif.harga_per_kwh) : '',
        biaya_beban: tarif ? String(tarif.biaya_beban) : '0',
        berlaku_mulai: tarif?.berlaku_mulai ?? new Date().toISOString().slice(0, 10),
        berlaku_sampai: tarif?.berlaku_sampai ?? '',
    });

    const close = () => {
        onClose();
        clearErrors();
        if (!tarif) reset();
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: close };
        if (tarif) put(route('pengaturan.tarif.update', tarif.id), opts);
        else post(route('pengaturan.tarif.store'), opts);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title={tarif ? `Ubah tarif ${tarif.golongan}` : 'Tambah tarif'}
            description="Untuk perubahan harga, sebaiknya tambah tarif baru dan isi tanggal berakhir pada tarif lama."
            submitLabel={tarif ? 'Simpan perubahan' : 'Tambah tarif'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="golongan" label="Golongan" required error={errors.golongan} hint="Contoh: R1, B1, S2.">
                <Input
                    id="golongan"
                    className="font-mono uppercase sm:w-40"
                    value={data.golongan}
                    onChange={(e) => setData('golongan', e.target.value.toUpperCase())}
                    aria-invalid={!!errors.golongan}
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="daya_min_va" label="Daya minimum (VA)" required error={errors.daya_min_va}>
                    <Input
                        id="daya_min_va"
                        type="number"
                        inputMode="numeric"
                        className="font-mono"
                        value={data.daya_min_va}
                        onChange={(e) => setData('daya_min_va', e.target.value)}
                        aria-invalid={!!errors.daya_min_va}
                    />
                </FormField>
                <FormField id="daya_max_va" label="Daya maksimum (VA)" required error={errors.daya_max_va}>
                    <Input
                        id="daya_max_va"
                        type="number"
                        inputMode="numeric"
                        className="font-mono"
                        value={data.daya_max_va}
                        onChange={(e) => setData('daya_max_va', e.target.value)}
                        aria-invalid={!!errors.daya_max_va}
                    />
                </FormField>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="harga_per_kwh" label="Harga per kWh (Rp)" required error={errors.harga_per_kwh}>
                    <Input
                        id="harga_per_kwh"
                        type="number"
                        step="0.01"
                        inputMode="decimal"
                        className="font-mono"
                        value={data.harga_per_kwh}
                        onChange={(e) => setData('harga_per_kwh', e.target.value)}
                        aria-invalid={!!errors.harga_per_kwh}
                    />
                </FormField>
                <FormField id="biaya_beban" label="Biaya beban (Rp)" required error={errors.biaya_beban}>
                    <Input
                        id="biaya_beban"
                        type="number"
                        inputMode="numeric"
                        className="font-mono"
                        value={data.biaya_beban}
                        onChange={(e) => setData('biaya_beban', e.target.value)}
                        aria-invalid={!!errors.biaya_beban}
                    />
                </FormField>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="berlaku_mulai" label="Berlaku mulai" required error={errors.berlaku_mulai}>
                    <Input
                        id="berlaku_mulai"
                        type="date"
                        value={data.berlaku_mulai}
                        onChange={(e) => setData('berlaku_mulai', e.target.value)}
                        aria-invalid={!!errors.berlaku_mulai}
                    />
                </FormField>
                <FormField id="berlaku_sampai" label="Berlaku sampai" error={errors.berlaku_sampai} hint="Kosongkan bila masih berlaku.">
                    <Input
                        id="berlaku_sampai"
                        type="date"
                        value={data.berlaku_sampai}
                        onChange={(e) => setData('berlaku_sampai', e.target.value)}
                        aria-invalid={!!errors.berlaku_sampai}
                    />
                </FormField>
            </div>
        </FormDialog>
    );
}
