import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet, MapPinOff, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import RowActions from '@/Components/shared/RowActions';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { StatusTone } from '@/lib/status';
import type { Option, PageProps, Paginated, RuteOption, UnitOption } from '@/types';

interface PelangganRow {
    id: number;
    idpel: string;
    nama: string;
    alamat: string;
    kelurahan: string;
    rute: string;
    urutan_rute: number;
    jenis: 'pascabayar' | 'prabayar';
    jenis_label: string;
    tarif: string;
    nomor_meter: string;
    status: 'aktif' | 'putus_sementara' | 'bongkar';
    status_label: string;
    punya_koordinat: boolean;
}

type Props = PageProps<{
    pelanggan: Paginated<PelangganRow>;
    filters: { q?: string; unit_id?: string; rute_id?: string; jenis?: string; status?: string };
    options: { units: UnitOption[]; rute: RuteOption[]; jenis: Option[]; status: Option[] };
    total: number;
}>;

const STATUS_TONE: Record<PelangganRow['status'], StatusTone> = {
    aktif: 'success',
    putus_sementara: 'danger',
    bongkar: 'neutral',
};

export default function PelangganIndex() {
    const { pelanggan, filters, options, total, flash } = usePage<Props>().props;
    const f = useFilters({
        q: filters.q ?? '',
        unit_id: filters.unit_id ?? '',
        rute_id: filters.rute_id ?? '',
        jenis: filters.jenis ?? '',
        status: filters.status ?? '',
    });
    const [imporOpen, setImporOpen] = useState(false);
    const hapus = useConfirmAction<PelangganRow>();

    const ruteTampil = f.values.unit_id ? options.rute.filter((r) => String(r.unit_id) === f.values.unit_id) : options.rute;

    const columns: ColumnDef<PelangganRow>[] = [
        {
            header: 'Urutan',
            meta: { className: 'w-28' },
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-mono text-[13px] font-medium">{String(p.urutan_rute).padStart(3, '0')}</p>
                    <p className="font-mono text-xs text-muted-ink">{p.rute}</p>
                </div>
            ),
        },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: p } }) => (
                <div className="min-w-0">
                    <p className="font-medium">{p.nama}</p>
                    <p className="flex items-center gap-1 text-xs text-muted-ink">
                        {p.alamat}, {p.kelurahan}
                        {!p.punya_koordinat && (
                            <MapPinOff className="size-3.5 text-pln-red" aria-label="Koordinat belum diisi" />
                        )}
                    </p>
                </div>
            ),
        },
        {
            header: 'ID pelanggan',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-mono text-[13px]">{p.idpel}</p>
                    <p className="font-mono text-xs text-muted-ink">Meter {p.nomor_meter}</p>
                </div>
            ),
        },
        {
            header: 'Tarif dan daya',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p>{p.tarif}</p>
                    <p className="text-xs text-muted-ink">{p.jenis_label}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row: { original: p } }) => <StatusChip label={p.status_label} tone={STATUS_TONE[p.status]} />,
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: p } }) => (
                <RowActions
                    label={p.nama}
                    actions={[
                        {
                            label: 'Ubah',
                            icon: Pencil,
                            onSelect: () => router.visit(route('pengaturan.pelanggan.edit', p.id)),
                        },
                        { label: 'Hapus', icon: Trash2, danger: true, onSelect: () => hapus.ask(p) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Pelanggan">
            <PageHeader
                title="Pelanggan"
                description={`${formatNumber(total)} pelanggan terdaftar, diurutkan sesuai rute baca meter.`}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setImporOpen(true)}>
                            <Upload />
                            Impor Excel
                        </Button>
                        <Button asChild>
                            <Link href={route('pengaturan.pelanggan.create')}>
                                <Plus />
                                Tambah pelanggan
                            </Link>
                        </Button>
                    </>
                }
            />

            {flash.impor && flash.impor.errors.length > 0 && <HasilImporNotice errors={flash.impor.errors} />}

            <Toolbar>
                <SegmentedTabs
                    label="Filter jenis"
                    value={f.values.jenis}
                    onChange={(v) => f.set('jenis', v)}
                    items={[{ value: '', label: 'Semua' }, ...options.jenis.map((j) => ({ value: j.value, label: j.label }))]}
                />
                <SearchInput
                    className="md:w-64"
                    value={f.values.q}
                    onChange={(v) => f.set('q', v, { debounce: true })}
                    placeholder="Cari ID, nama, atau alamat"
                />
                <NativeSelect
                    className="md:w-40"
                    aria-label="Filter unit"
                    value={f.values.unit_id}
                    onChange={(e) => f.setMany({ unit_id: e.target.value, rute_id: '' })}
                >
                    <option value="">Semua unit</option>
                    {options.units
                        .filter((u) => u.jenis === 'ulp')
                        .map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama}
                            </option>
                        ))}
                </NativeSelect>
                <NativeSelect
                    className="md:w-48"
                    aria-label="Filter rute"
                    value={f.values.rute_id}
                    onChange={(e) => f.set('rute_id', e.target.value)}
                >
                    <option value="">Semua rute</option>
                    {ruteTampil.map((r) => (
                        <option key={r.id} value={r.id}>
                            {r.kode} · {r.nama}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    className="md:w-40"
                    aria-label="Filter status"
                    value={f.values.status}
                    onChange={(e) => f.set('status', e.target.value)}
                >
                    <option value="">Semua status</option>
                    {options.status.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>

            <DataTable
                columns={columns}
                data={pelanggan}
                getRowId={(p) => String(p.id)}
                empty="Tidak ada pelanggan yang cocok dengan filter."
            />

            <ImporDialog open={imporOpen} onOpenChange={setImporOpen} units={options.units} />

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus pelanggan?"
                description={
                    <>
                        <strong className="text-ink">
                            {hapus.target?.nama} ({hapus.target?.idpel})
                        </strong>{' '}
                        tidak akan muncul lagi di daftar tugas biller. Riwayat catatan dan tagihannya tetap tersimpan.
                    </>
                }
                confirmLabel="Hapus pelanggan"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.pelanggan.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function HasilImporNotice({ errors }: { errors: string[] }) {
    return (
        <Notice tone="danger" title={`Impor dibatalkan, ${errors.length} baris perlu diperbaiki`} className="mb-4">
            <ul className="mt-1 max-h-40 list-disc overflow-y-auto pl-5">
                {errors.map((e) => (
                    <li key={e}>{e}</li>
                ))}
            </ul>
        </Notice>
    );
}

function ImporDialog({
    open,
    onOpenChange,
    units,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    units: UnitOption[];
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset, clearErrors, progress } = useForm<{
        unit_id: string;
        file: File | null;
    }>({ unit_id: '', file: null });

    const close = () => {
        onOpenChange(false);
        clearErrors();
        reset();
        if (fileRef.current) fileRef.current.value = '';
    };

    const submit = () =>
        post(route('pengaturan.pelanggan.impor'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: close,
        });

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title="Impor pelanggan dari Excel"
            description="Pelanggan dicocokkan lewat ID pelanggan: yang sudah ada diperbarui, yang belum ada ditambahkan."
            submitLabel="Impor"
            processing={processing}
            onSubmit={submit}
        >
            <Notice tone="info" icon={FileSpreadsheet} title="Gunakan template agar kolomnya cocok">
                <p>
                    Kolom wajib: idpel, nama, alamat, kelurahan, kode_rute, urutan_rute, jenis, golongan_tarif, daya_va,
                    nomor_meter. Bila ada satu baris yang salah, tidak ada data yang disimpan.
                </p>
                <a
                    href={route('pengaturan.pelanggan.template')}
                    className="mt-2 inline-flex items-center gap-1.5 font-medium text-pln-blue hover:underline"
                >
                    <Download className="size-4" />
                    Unduh template
                </a>
            </Notice>
            <FormField id="unit_id" label="Unit" required error={errors.unit_id} hint="Kode rute di berkas dicocokkan dengan rute unit ini.">
                <NativeSelect id="unit_id" value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)}>
                    <option value="">Pilih unit</option>
                    {units
                        .filter((u) => u.jenis === 'ulp')
                        .map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama}
                            </option>
                        ))}
                </NativeSelect>
            </FormField>
            <FormField id="file" label="Berkas Excel" required error={errors.file} hint=".xlsx, .xls, atau .csv, maksimal 5 MB.">
                <input
                    ref={fileRef}
                    id="file"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                    className="block w-full rounded-9 border border-border text-sm text-muted-ink file:mr-3 file:h-11 file:border-0 file:border-r file:border-border file:bg-table-head file:px-4 file:text-sm file:font-medium file:text-ink"
                />
            </FormField>
            {progress && (
                <p className="text-[13px] text-muted-ink">Mengunggah {progress.percentage ?? 0}%…</p>
            )}
        </FormDialog>
    );
}
