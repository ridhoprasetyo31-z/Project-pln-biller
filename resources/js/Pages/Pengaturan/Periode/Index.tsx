import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { jadwal, STATUS_PERIODE, type PeriodeRingkas } from '@/lib/periode';
import type { PageProps, Paginated } from '@/types';

interface PeriodeRow extends PeriodeRingkas {
    penugasan_count: number;
    target_total: number;
}

type Props = PageProps<{
    periode: Paginated<PeriodeRow>;
    saran: { bulan: string; tanggal_mulai: string; tanggal_selesai: string };
    adaBerjalan: string | null;
}>;

export default function PeriodeIndex() {
    const { periode, saran, adaBerjalan } = usePage<Props>().props;
    const [buatOpen, setBuatOpen] = useState(false);

    const columns: ColumnDef<PeriodeRow>[] = [
        {
            header: 'Periode',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-medium">{p.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">{p.kode}</p>
                </div>
            ),
        },
        { header: 'Jadwal pencatatan', cell: ({ row }) => jadwal(row.original) },
        {
            header: 'Status',
            cell: ({ row: { original: p } }) => <StatusChip {...STATUS_PERIODE[p.status]} />,
        },
        {
            header: 'Penugasan',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p>{formatNumber(p.penugasan_count)} rute</p>
                    <p className="text-xs text-muted-ink">Target {formatNumber(p.target_total)} pelanggan</p>
                </div>
            ),
        },
        {
            header: 'Ditutup',
            cell: ({ row: { original: p } }) =>
                p.ditutup_at ? (
                    <div className="text-[13px]">
                        <p>{formatTanggalWaktu(p.ditutup_at)}</p>
                        <p className="text-xs text-muted-ink">oleh {p.ditutup_oleh ?? '–'}</p>
                    </div>
                ) : (
                    <span className="text-muted-ink">–</span>
                ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-32 text-right' },
            cell: ({ row: { original: p } }) => (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={route('pengaturan.periode.show', p.id)}>
                        Kelola
                        <ArrowRight />
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Periode">
            <PageHeader
                title="Periode pencatatan"
                description="Satu periode per bulan. Periode yang berjalan menentukan tugas biller; periode yang ditutup menjadi dasar skor final."
                actions={
                    <Button onClick={() => setBuatOpen(true)}>
                        <Plus />
                        Buat periode
                    </Button>
                }
            />

            {adaBerjalan && (
                <Notice tone="info" className="mb-4" title={`Periode ${adaBerjalan} sedang berjalan`}>
                    Periode baru bisa dibuat sebagai draf sekarang, lalu dimulai setelah periode {adaBerjalan} ditutup.
                </Notice>
            )}

            <DataTable
                columns={columns}
                data={periode}
                getRowId={(p) => String(p.id)}
                rowClassName={(p) => (p.status === 'berjalan' ? 'bg-pln-blue-light/40' : undefined)}
                empty="Belum ada periode."
            />

            <BuatPeriodeDialog open={buatOpen} onOpenChange={setBuatOpen} saran={saran} />
        </AppLayout>
    );
}

function BuatPeriodeDialog({
    open,
    onOpenChange,
    saran,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    saran: Props['saran'];
}) {
    const { data, setData, post, processing, errors, clearErrors } = useForm(saran);

    const gantiBulan = (bulan: string) => {
        if (!/^\d{4}-\d{2}$/.test(bulan)) {
            setData('bulan', bulan);
            return;
        }
        const [y, m] = bulan.split('-').map(Number);
        const akhir = new Date(y, m, 0).getDate();
        setData({ bulan, tanggal_mulai: `${bulan}-01`, tanggal_selesai: `${bulan}-${String(akhir).padStart(2, '0')}` });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => {
                onOpenChange(v);
                if (!v) clearErrors();
            }}
            title="Buat periode"
            description="Periode dibuat sebagai draf. Penugasan disusun otomatis dari biller pemegang tiap rute aktif dan bisa diubah sebelum periode dimulai."
            submitLabel="Buat periode"
            processing={processing}
            onSubmit={() => post(route('pengaturan.periode.store'))}
        >
            <FormField id="bulan" label="Bulan" required error={errors.bulan}>
                <Input id="bulan" type="month" value={data.bulan} onChange={(e) => gantiBulan(e.target.value)} aria-invalid={!!errors.bulan} />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="tanggal_mulai" label="Mulai pencatatan" required error={errors.tanggal_mulai}>
                    <Input
                        id="tanggal_mulai"
                        type="date"
                        value={data.tanggal_mulai}
                        onChange={(e) => setData('tanggal_mulai', e.target.value)}
                        aria-invalid={!!errors.tanggal_mulai}
                    />
                </FormField>
                <FormField
                    id="tanggal_selesai"
                    label="Batas pencatatan"
                    required
                    error={errors.tanggal_selesai}
                    hint="Catatan yang dikirim setelah tanggal ini ditandai lewat jadwal."
                >
                    <Input
                        id="tanggal_selesai"
                        type="date"
                        value={data.tanggal_selesai}
                        onChange={(e) => setData('tanggal_selesai', e.target.value)}
                        aria-invalid={!!errors.tanggal_selesai}
                    />
                </FormField>
            </div>
        </FormDialog>
    );
}
