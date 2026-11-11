import { router, useForm, usePage } from '@inertiajs/react';
import { CalendarCog, Lock, Pencil, Play, RefreshCw, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import RowActions from '@/Components/shared/RowActions';
import StatCard from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { jadwal, STATUS_PERIODE, type PeriodeRingkas } from '@/lib/periode';
import type { BillerOption, PageProps } from '@/types';

interface PenugasanRow {
    id: number;
    rute_id: number;
    rute: { kode: string; nama: string; unit_id: number; unit: string };
    biller_id: number;
    biller: { name: string; nip: string; is_active: boolean };
    target_pelanggan: number;
    pencatatan_count: number;
}

interface RuteKosong {
    id: number;
    kode: string;
    nama: string;
    unit_id: number;
    unit: string;
    biller_id: number | null;
    pelanggan_aktif_count: number;
}

type Props = PageProps<{
    periode: PeriodeRingkas;
    penugasan: PenugasanRow[];
    ruteTanpaPenugasan: RuteKosong[];
    billers: BillerOption[];
    can: { ubah: boolean; mulai: boolean; tutup: boolean; hapus: boolean };
}>;

type Aksi = 'mulai' | 'tutup' | 'hapus';

export default function PeriodeShow() {
    const { periode, penugasan, ruteTanpaPenugasan, billers, can } = usePage<Props>().props;
    const [aksi, setAksi] = useState<Aksi | null>(null);
    const [prosesAksi, setProsesAksi] = useState(false);
    const [jadwalOpen, setJadwalOpen] = useState(false);
    const [ubah, setUbah] = useState<PenugasanRow | null>(null);
    const [tugaskan, setTugaskan] = useState<RuteKosong | null>(null);
    const [menyimpan, setMenyimpan] = useState<number | null>(null);
    const hapusPenugasan = useConfirmAction<PenugasanRow>();

    const target = penugasan.reduce((s, p) => s + p.target_pelanggan, 0);
    const tercatat = penugasan.reduce((s, p) => s + p.pencatatan_count, 0);
    const jumlahBiller = new Set(penugasan.map((p) => p.biller_id)).size;

    const jalankan = (a: Aksi) => {
        const opts = {
            preserveScroll: true,
            onStart: () => setProsesAksi(true),
            onFinish: () => {
                setProsesAksi(false);
                setAksi(null);
            },
            onError: (e: Record<string, string>) => toast.error(e.periode ?? 'Aksi gagal dijalankan.'),
        };
        if (a === 'mulai') router.post(route('pengaturan.periode.mulai', periode.id), {}, opts);
        if (a === 'tutup') router.post(route('pengaturan.periode.tutup', periode.id), {}, opts);
        if (a === 'hapus') router.delete(route('pengaturan.periode.destroy', periode.id), opts);
    };

    const gantiBiller = (p: PenugasanRow, billerId: string) =>
        router.put(
            route('pengaturan.penugasan.update', p.id),
            { biller_id: billerId, target_pelanggan: p.target_pelanggan },
            {
                preserveScroll: true,
                onStart: () => setMenyimpan(p.id),
                onFinish: () => setMenyimpan(null),
                onError: (e) => toast.error(e.biller_id ?? 'Penugasan gagal diubah.'),
            },
        );

    const columns: ColumnDef<PenugasanRow>[] = [
        {
            header: 'Rute',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-mono text-[13px] font-medium">{p.rute.kode}</p>
                    <p className="text-xs text-muted-ink">
                        {p.rute.nama} · {p.rute.unit}
                    </p>
                </div>
            ),
        },
        {
            header: 'Biller',
            meta: { className: 'w-72' },
            cell: ({ row: { original: p } }) =>
                can.ubah ? (
                    <NativeSelect
                        aria-label={`Biller untuk rute ${p.rute.kode}`}
                        value={p.biller_id}
                        disabled={menyimpan === p.id}
                        onChange={(e) => gantiBiller(p, e.target.value)}
                    >
                        {!p.biller.is_active && <option value={p.biller_id}>{p.biller.name} (nonaktif)</option>}
                        {billers
                            .filter((b) => b.unit_id === p.rute.unit_id)
                            .map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                    </NativeSelect>
                ) : (
                    <div>
                        <p>{p.biller.name}</p>
                        <p className="font-mono text-xs text-muted-ink">{p.biller.nip}</p>
                    </div>
                ),
        },
        {
            header: 'Target',
            meta: { className: 'text-right' },
            cell: ({ row }) => `${formatNumber(row.original.target_pelanggan)} pelanggan`,
        },
        {
            header: 'Tercatat',
            meta: { className: 'text-right' },
            cell: ({ row }) => formatNumber(row.original.pencatatan_count),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: p } }) => (
                <RowActions
                    label={`penugasan rute ${p.rute.kode}`}
                    actions={[
                        { label: 'Ubah target', icon: Pencil, hidden: !can.ubah, onSelect: () => setUbah(p) },
                        {
                            label: 'Hapus penugasan',
                            icon: Trash2,
                            danger: true,
                            hidden: !can.ubah || p.pencatatan_count > 0,
                            onSelect: () => hapusPenugasan.ask(p),
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout
            title={`Periode ${periode.kode}`}
            breadcrumbs={[
                { label: 'Data master' },
                { label: 'Periode', href: route('pengaturan.periode.index') },
                { label: periode.kode },
            ]}
        >
            <PageHeader
                title={periode.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span className="font-mono">{periode.kode}</span>·<span>{jadwal(periode)}</span>
                        <StatusChip {...STATUS_PERIODE[periode.status]} />
                    </span>
                }
                actions={
                    <>
                        {can.hapus && (
                            <Button variant="reject" onClick={() => setAksi('hapus')}>
                                <Trash2 />
                                Hapus
                            </Button>
                        )}
                        {can.ubah && (
                            <Button variant="secondary" onClick={() => setJadwalOpen(true)}>
                                <CalendarCog />
                                Ubah jadwal
                            </Button>
                        )}
                        {can.tutup && (
                            <Button variant="secondary" onClick={() => setAksi('tutup')}>
                                <Lock />
                                Tutup periode
                            </Button>
                        )}
                        {can.mulai && (
                            <Button onClick={() => setAksi('mulai')}>
                                <Play />
                                Mulai periode
                            </Button>
                        )}
                    </>
                }
            />

            {periode.status === 'ditutup' && (
                <Notice tone="success" className="mb-6" title="Periode sudah ditutup">
                    Ditutup {formatTanggalWaktu(periode.ditutup_at)} oleh {periode.ditutup_oleh ?? '–'}. Penugasan dan catatan
                    periode ini tidak bisa diubah lagi.
                </Notice>
            )}

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Rute ditugaskan" value={formatNumber(penugasan.length)} caption={`${ruteTanpaPenugasan.length} rute aktif belum ditugaskan`} />
                <StatCard label="Biller terlibat" value={formatNumber(jumlahBiller)} />
                <StatCard label="Target pelanggan" value={formatNumber(target)} caption="Pelanggan aktif saat penugasan disusun" />
                <StatCard
                    label="Tercatat"
                    value={formatNumber(tercatat)}
                    progress={target ? (tercatat / target) * 100 : 0}
                    caption={target ? `${formatNumber((tercatat / target) * 100, 1)}% dari target` : undefined}
                />
            </div>

            <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-section-title">Penugasan rute</h2>
                    <p className="text-[13px] text-muted-ink">Satu rute dikerjakan satu biller dalam periode ini.</p>
                </div>
                {can.ubah && (
                    <Button
                        variant="secondary"
                        onClick={() => router.post(route('pengaturan.penugasan.sinkron', periode.id), {}, { preserveScroll: true })}
                    >
                        <RefreshCw />
                        Susun ulang dari rute
                    </Button>
                )}
            </div>

            <DataTable
                columns={columns}
                data={penugasan}
                getRowId={(p) => String(p.id)}
                empty="Belum ada penugasan. Pastikan rute aktif sudah punya biller pemegang, lalu susun ulang."
            />

            {ruteTanpaPenugasan.length > 0 && (
                <section className="mt-6">
                    <h2 className="text-section-title">Rute belum ditugaskan</h2>
                    <p className="mb-3 text-[13px] text-muted-ink">Rute aktif ini tidak akan dikerjakan pada periode {periode.kode}.</p>
                    <ul className="divide-y divide-line-row rounded-14 border border-border">
                        {ruteTanpaPenugasan.map((r) => (
                            <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <p className="font-mono text-[13px] font-medium">{r.kode}</p>
                                    <p className="text-xs text-muted-ink">
                                        {r.nama} · {r.unit} · {formatNumber(r.pelanggan_aktif_count)} pelanggan aktif
                                    </p>
                                </div>
                                {can.ubah && (
                                    <Button variant="secondary" size="sm" onClick={() => setTugaskan(r)}>
                                        <UserPlus />
                                        Tugaskan
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <JadwalDialog key={`jadwal-${periode.tanggal_mulai}-${periode.tanggal_selesai}`} open={jadwalOpen} onOpenChange={setJadwalOpen} periode={periode} />

            {ubah && (
                <PenugasanDialog
                    key={`ubah-${ubah.id}`}
                    title={`Ubah penugasan ${ubah.rute.kode}`}
                    url={route('pengaturan.penugasan.update', ubah.id)}
                    method="put"
                    unitId={ubah.rute.unit_id}
                    billers={billers}
                    awal={{ biller_id: String(ubah.biller_id), target_pelanggan: String(ubah.target_pelanggan) }}
                    onClose={() => setUbah(null)}
                />
            )}

            {tugaskan && (
                <PenugasanDialog
                    key={`baru-${tugaskan.id}`}
                    title={`Tugaskan rute ${tugaskan.kode}`}
                    url={route('pengaturan.penugasan.store', periode.id)}
                    method="post"
                    unitId={tugaskan.unit_id}
                    billers={billers}
                    ruteId={tugaskan.id}
                    awal={{
                        biller_id: tugaskan.biller_id ? String(tugaskan.biller_id) : '',
                        target_pelanggan: String(tugaskan.pelanggan_aktif_count),
                    }}
                    onClose={() => setTugaskan(null)}
                />
            )}

            <ConfirmDialog
                open={aksi === 'mulai'}
                onOpenChange={(v) => !v && setAksi(null)}
                title={`Mulai periode ${periode.kode}?`}
                description={`${formatNumber(penugasan.length)} rute dengan target ${formatNumber(target)} pelanggan akan muncul di Tugas hari ini milik biller.`}
                confirmLabel="Mulai periode"
                processing={prosesAksi}
                onConfirm={() => jalankan('mulai')}
            />
            <ConfirmDialog
                open={aksi === 'tutup'}
                onOpenChange={(v) => !v && setAksi(null)}
                title={`Tutup periode ${periode.kode}?`}
                description={`Biller tidak bisa lagi mencatat atau merevisi catatan periode ini, dan skor periode ini menjadi final. Saat ini ${formatNumber(tercatat)} dari ${formatNumber(target)} pelanggan sudah tercatat.`}
                confirmLabel="Tutup periode"
                processing={prosesAksi}
                onConfirm={() => jalankan('tutup')}
            />
            <ConfirmDialog
                open={aksi === 'hapus'}
                onOpenChange={(v) => !v && setAksi(null)}
                title={`Hapus periode ${periode.kode}?`}
                description="Periode draf beserta penugasannya akan dihapus."
                confirmLabel="Hapus periode"
                tone="destructive"
                processing={prosesAksi}
                onConfirm={() => jalankan('hapus')}
            />
            <ConfirmDialog
                open={hapusPenugasan.open}
                onOpenChange={(v) => !v && hapusPenugasan.close()}
                title="Hapus penugasan?"
                description={`Rute ${hapusPenugasan.target?.rute.kode ?? ''} tidak akan dikerjakan pada periode ${periode.kode}.`}
                confirmLabel="Hapus penugasan"
                tone="destructive"
                processing={hapusPenugasan.processing}
                onConfirm={() =>
                    hapusPenugasan.target && hapusPenugasan.run('delete', route('pengaturan.penugasan.destroy', hapusPenugasan.target.id))
                }
            />
        </AppLayout>
    );
}

function JadwalDialog({ open, onOpenChange, periode }: { open: boolean; onOpenChange: (v: boolean) => void; periode: PeriodeRingkas }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        tanggal_mulai: periode.tanggal_mulai,
        tanggal_selesai: periode.tanggal_selesai,
    });

    const close = () => {
        onOpenChange(false);
        clearErrors();
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title={`Ubah jadwal ${periode.kode}`}
            submitLabel="Simpan jadwal"
            processing={processing}
            onSubmit={() => put(route('pengaturan.periode.update', periode.id), { preserveScroll: true, onSuccess: close })}
        >
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="tanggal_mulai" label="Mulai pencatatan" required error={errors.tanggal_mulai}>
                    <Input id="tanggal_mulai" type="date" value={data.tanggal_mulai} onChange={(e) => setData('tanggal_mulai', e.target.value)} />
                </FormField>
                <FormField id="tanggal_selesai" label="Batas pencatatan" required error={errors.tanggal_selesai}>
                    <Input id="tanggal_selesai" type="date" value={data.tanggal_selesai} onChange={(e) => setData('tanggal_selesai', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function PenugasanDialog({
    title,
    url,
    method,
    unitId,
    billers,
    ruteId,
    awal,
    onClose,
}: {
    title: string;
    url: string;
    method: 'post' | 'put';
    unitId: number;
    billers: BillerOption[];
    ruteId?: number;
    awal: { biller_id: string; target_pelanggan: string };
    onClose: () => void;
}) {
    const { data, setData, submit, processing, errors, transform } = useForm(awal);
    const pilihan = billers.filter((b) => b.unit_id === unitId);

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title={title}
            submitLabel="Simpan penugasan"
            processing={processing}
            onSubmit={() => {
                transform((d) => (ruteId ? { ...d, rute_id: ruteId } : d));
                submit(method, url, { preserveScroll: true, onSuccess: onClose });
            }}
        >
            <FormField id="biller_id" label="Biller" required error={errors.biller_id}>
                <NativeSelect id="biller_id" value={data.biller_id} onChange={(e) => setData('biller_id', e.target.value)}>
                    <option value="">Pilih biller</option>
                    {pilihan.map((b) => (
                        <option key={b.id} value={b.id}>
                            {b.name} · {b.nip}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="target_pelanggan"
                label="Target pelanggan"
                required
                error={errors.target_pelanggan}
                hint="Jumlah pelanggan yang wajib dicatat; dipakai untuk menghitung penyelesaian."
            >
                <Input
                    id="target_pelanggan"
                    type="number"
                    min={0}
                    inputMode="numeric"
                    className="font-mono sm:w-40"
                    value={data.target_pelanggan}
                    onChange={(e) => setData('target_pelanggan', e.target.value)}
                />
            </FormField>
            {(errors as Record<string, string>).rute_id && (
                <p className="text-[13px] text-error-ink">{(errors as Record<string, string>).rute_id}</p>
            )}
        </FormDialog>
    );
}
