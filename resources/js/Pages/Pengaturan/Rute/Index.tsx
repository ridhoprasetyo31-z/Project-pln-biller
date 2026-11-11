import { router, useForm, usePage } from '@inertiajs/react';
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
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { BillerOption, PageProps, Paginated, UnitOption } from '@/types';

interface RuteRow {
    id: number;
    unit_id: number;
    unit: string;
    kode: string;
    nama: string;
    kelurahan: string;
    biller_id: number | null;
    biller: { name: string; nip: string } | null;
    is_active: boolean;
    pelanggan_count: number;
    pelanggan_aktif_count: number;
}

type Props = PageProps<{
    rute: Paginated<RuteRow>;
    filters: { q?: string; unit_id?: string; biller?: string };
    options: { units: UnitOption[]; billers: BillerOption[] };
}>;

export default function RuteIndex() {
    const { rute, filters, options } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', unit_id: filters.unit_id ?? '', biller: filters.biller ?? '' });
    const [form, setForm] = useState<{ open: boolean; rute: RuteRow | null }>({ open: false, rute: null });
    const hapus = useConfirmAction<RuteRow>();
    const [menyimpan, setMenyimpan] = useState<number | null>(null);

    const gantiBiller = (r: RuteRow, billerId: string) =>
        router.patch(
            route('pengaturan.rute.biller', r.id),
            { biller_id: billerId || null },
            {
                preserveScroll: true,
                onStart: () => setMenyimpan(r.id),
                onFinish: () => setMenyimpan(null),
            },
        );

    const columns: ColumnDef<RuteRow>[] = [
        {
            header: 'Rute',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p className="font-mono text-[13px] font-medium">{r.kode}</p>
                    <p className="text-xs text-muted-ink">{r.nama}</p>
                </div>
            ),
        },
        {
            header: 'Unit dan kelurahan',
            cell: ({ row: { original: r } }) => (
                <div>
                    <p>{r.unit}</p>
                    <p className="text-xs text-muted-ink">{r.kelurahan}</p>
                </div>
            ),
        },
        {
            header: 'Pelanggan aktif',
            meta: { className: 'text-right' },
            cell: ({ row: { original: r } }) => (
                <span>
                    {formatNumber(r.pelanggan_aktif_count)}
                    <span className="text-muted-ink"> / {formatNumber(r.pelanggan_count)}</span>
                </span>
            ),
        },
        {
            header: 'Biller pemegang',
            meta: { className: 'w-64' },
            cell: ({ row: { original: r } }) => (
                <NativeSelect
                    aria-label={`Biller pemegang rute ${r.kode}`}
                    value={r.biller_id ?? ''}
                    disabled={menyimpan === r.id}
                    onChange={(e) => gantiBiller(r, e.target.value)}
                >
                    <option value="">Belum ada biller</option>
                    {options.billers
                        .filter((b) => b.unit_id === r.unit_id)
                        .map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                </NativeSelect>
            ),
        },
        {
            header: 'Status',
            cell: ({ row }) =>
                row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />,
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: r } }) => (
                <RowActions
                    label={`rute ${r.kode}`}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, rute: r }) },
                        { label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(r) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Rute">
            <PageHeader
                title="Rute"
                description="Rute baca meter per unit. Biller pemegang dipakai sebagai penugasan awal setiap periode baru."
                actions={
                    <Button onClick={() => setForm({ open: true, rute: null })}>
                        <Plus />
                        Tambah rute
                    </Button>
                }
            />

            <Toolbar>
                <SegmentedTabs
                    label="Filter biller"
                    value={f.values.biller}
                    onChange={(v) => f.set('biller', v)}
                    items={[
                        { value: '', label: 'Semua' },
                        { value: 'kosong', label: 'Belum ada biller' },
                    ]}
                />
                <SearchInput
                    className="md:w-72"
                    value={f.values.q}
                    onChange={(v) => f.set('q', v, { debounce: true })}
                    placeholder="Cari kode, nama, atau kelurahan"
                />
                <NativeSelect
                    className="md:w-48"
                    aria-label="Filter unit"
                    value={f.values.unit_id}
                    onChange={(e) => f.set('unit_id', e.target.value)}
                >
                    <option value="">Semua unit</option>
                    {options.units.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.nama}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>

            <DataTable
                columns={columns}
                data={rute}
                getRowId={(r) => String(r.id)}
                rowClassName={(r) => (r.biller_id ? undefined : 'bg-pln-yellow-light/40')}
                empty="Tidak ada rute yang cocok dengan filter."
            />

            <RuteFormDialog
                key={form.rute?.id ?? 'baru'}
                open={form.open}
                rute={form.rute}
                options={options}
                onClose={() => setForm((s) => ({ ...s, open: false }))}
            />

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus rute?"
                description={
                    <>
                        Rute <strong className="text-ink">{hapus.target?.kode}</strong> hanya bisa dihapus bila belum punya
                        pelanggan dan penugasan. Bila sudah dipakai, nonaktifkan saja.
                    </>
                }
                confirmLabel="Hapus rute"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.rute.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function RuteFormDialog({
    open,
    rute,
    options,
    onClose,
}: {
    open: boolean;
    rute: RuteRow | null;
    options: Props['options'];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        unit_id: rute ? String(rute.unit_id) : '',
        kode: rute?.kode ?? '',
        nama: rute?.nama ?? '',
        kelurahan: rute?.kelurahan ?? '',
        biller_id: rute?.biller_id ? String(rute.biller_id) : '',
        is_active: rute?.is_active ?? true,
    });

    const billers = options.billers.filter((b) => String(b.unit_id) === data.unit_id);

    const close = () => {
        onClose();
        clearErrors();
        if (!rute) reset();
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: close };
        if (rute) put(route('pengaturan.rute.update', rute.id), opts);
        else post(route('pengaturan.rute.store'), opts);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title={rute ? `Ubah rute ${rute.kode}` : 'Tambah rute'}
            submitLabel={rute ? 'Simpan perubahan' : 'Tambah rute'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="unit_id" label="Unit" required error={errors.unit_id}>
                <NativeSelect
                    id="unit_id"
                    value={data.unit_id}
                    onChange={(e) => setData((d) => ({ ...d, unit_id: e.target.value, biller_id: '' }))}
                    aria-invalid={!!errors.unit_id}
                >
                    <option value="">Pilih unit</option>
                    {options.units.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.nama}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <div className="grid gap-5 sm:grid-cols-[160px_1fr]">
                <FormField id="kode" label="Kode rute" required error={errors.kode}>
                    <Input
                        id="kode"
                        className="font-mono uppercase"
                        placeholder="PNM-04"
                        value={data.kode}
                        onChange={(e) => setData('kode', e.target.value)}
                        aria-invalid={!!errors.kode}
                    />
                </FormField>
                <FormField id="nama" label="Nama rute" required error={errors.nama}>
                    <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!errors.nama} />
                </FormField>
            </div>
            <FormField id="kelurahan" label="Kelurahan" required error={errors.kelurahan}>
                <Input
                    id="kelurahan"
                    value={data.kelurahan}
                    onChange={(e) => setData('kelurahan', e.target.value)}
                    aria-invalid={!!errors.kelurahan}
                />
            </FormField>
            <FormField
                id="biller_id"
                label="Biller pemegang"
                error={errors.biller_id}
                hint={data.unit_id ? 'Hanya biller aktif dari unit yang sama.' : 'Pilih unit lebih dulu.'}
            >
                <NativeSelect
                    id="biller_id"
                    value={data.biller_id}
                    disabled={!data.unit_id}
                    onChange={(e) => setData('biller_id', e.target.value)}
                    aria-invalid={!!errors.biller_id}
                >
                    <option value="">Belum ada biller</option>
                    {billers.map((b) => (
                        <option key={b.id} value={b.id}>
                            {b.name} · {b.nip}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <div className="flex items-center gap-2.5">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                <Label htmlFor="is_active" className="text-sm font-normal">
                    Rute aktif (ikut disusun saat membuat periode baru)
                </Label>
            </div>
        </FormDialog>
    );
}
