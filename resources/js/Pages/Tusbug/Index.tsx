import { Link, useForm, usePage } from '@inertiajs/react';
import { Unplug } from 'lucide-react';
import { useMemo, useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import { statusTusbug } from '@/lib/tusbug';
import { rupiah } from '@/lib/tagihan';
import type { Option, PageProps } from '@/types';

interface Saran {
    pelanggan_id: number;
    idpel: string;
    nama: string;
    alamat: string;
    rute: string | null;
    biller_id: number | null;
    biller: string | null;
    lembar: number;
    total: number;
    tertua: string;
}

interface Tugas {
    id: number;
    nomor_surat: string;
    pelanggan: { idpel: string; nama: string; alamat: string; rute: string | null; status_label: string };
    biller: string;
    jumlah_lembar: number;
    total_tunggakan: number;
    tanggal_rencana: string;
    status: string;
    hasil_label: string | null;
    dilaksanakan_at: string | null;
}

type Tab = 'saran' | 'berjalan' | 'selesai' | 'tugas';

type Props = PageProps<{
    tab: Tab;
    koordinator: boolean;
    saran: Saran[];
    berjalan: Tugas[];
    selesai: Tugas[];
    minLembar: number;
    billerOptions: Option<number>[];
}>;

export default function TusbugIndex() {
    const { tab: tabAwal, koordinator, saran, berjalan, selesai, minLembar, billerOptions } = usePage<Props>().props;
    const [tab, setTab] = useState<Tab>(koordinator ? (tabAwal === 'tugas' ? 'saran' : tabAwal) : tabAwal === 'selesai' ? 'selesai' : 'tugas');
    const [pilih, setPilih] = useState<Set<number>>(new Set());
    const [tugaskanOpen, setTugaskanOpen] = useState(false);

    const semuaDipilih = saran.length > 0 && pilih.size === saran.length;
    const ubahPilih = (id: number, v: boolean) =>
        setPilih((s) => {
            const n = new Set(s);
            if (v) n.add(id);
            else n.delete(id);
            return n;
        });

    const kolomSaran: ColumnDef<Saran>[] = [
        {
            id: 'pilih',
            header: () => (
                <Checkbox
                    aria-label="Pilih semua"
                    checked={semuaDipilih ? true : pilih.size > 0 ? 'indeterminate' : false}
                    onCheckedChange={(v) => setPilih(v === true ? new Set(saran.map((s) => s.pelanggan_id)) : new Set())}
                />
            ),
            meta: { className: 'w-12' },
            cell: ({ row: { original: s } }) => (
                <Checkbox aria-label={`Pilih ${s.nama}`} checked={pilih.has(s.pelanggan_id)} onCheckedChange={(v) => ubahPilih(s.pelanggan_id, v === true)} />
            ),
        },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: s } }) => (
                <div>
                    <p className="font-medium">{s.nama}</p>
                    <p className="text-xs text-muted-ink">
                        <span className="font-mono">{s.idpel}</span> · {s.alamat}
                    </p>
                </div>
            ),
        },
        { header: 'Rute', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.rute ?? '–'}</span> },
        {
            header: 'Tunggakan',
            meta: { className: 'text-right' },
            cell: ({ row: { original: s } }) => (
                <div>
                    <p className="font-semibold text-error-ink">{s.lembar} lembar</p>
                    <p className="font-mono text-xs">{rupiah(s.total)}</p>
                </div>
            ),
        },
        { header: 'Jatuh tempo tertua', cell: ({ row }) => <span className="text-[13px]">{formatTanggal(row.original.tertua, 'd MMM yyyy')}</span> },
        { header: 'Biller pemegang rute', cell: ({ row }) => row.original.biller ?? <span className="text-error-ink">Belum ada</span> },
    ];

    const kolomTugas: ColumnDef<Tugas>[] = [
        {
            header: 'Surat',
            cell: ({ row: { original: t } }) => (
                <div>
                    <p className="font-mono text-[13px]">{t.nomor_surat}</p>
                    <p className="text-xs text-muted-ink">Rencana {formatTanggal(t.tanggal_rencana, 'd MMM yyyy')}</p>
                </div>
            ),
        },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: t } }) => (
                <div>
                    <p className="font-medium">{t.pelanggan.nama}</p>
                    <p className="text-xs text-muted-ink">
                        <span className="font-mono">{t.pelanggan.idpel}</span> · {t.pelanggan.rute} · {t.pelanggan.status_label}
                    </p>
                </div>
            ),
        },
        ...(koordinator ? [{ header: 'Biller', cell: ({ row }: { row: { original: Tugas } }) => row.original.biller } as ColumnDef<Tugas>] : []),
        {
            header: 'Tunggakan',
            meta: { className: 'text-right' },
            cell: ({ row: { original: t } }) => (
                <div>
                    <p>{t.jumlah_lembar} lembar</p>
                    <p className="font-mono text-xs text-muted-ink">{rupiah(t.total_tunggakan)}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row: { original: t } }) => (
                <div className="grid justify-items-start gap-1">
                    <StatusChip {...statusTusbug(t.status)} />
                    {t.hasil_label && <span className="text-xs text-muted-ink">Hasil: {t.hasil_label}</span>}
                </div>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-32 text-right' },
            cell: ({ row: { original: t } }) => {
                const laksanakan = !koordinator && (t.status === 'ditugaskan' || t.status === 'revisi');
                return (
                    <Button variant={laksanakan ? (t.status === 'revisi' ? 'reject' : 'default') : 'secondary'} size="sm" asChild>
                        <Link href={route('tusbug.show', t.id)}>{laksanakan ? (t.status === 'revisi' ? 'Perbaiki' : 'Laksanakan') : 'Lihat'}</Link>
                    </Button>
                );
            },
        },
    ];

    const tabs = koordinator
        ? [
              { value: 'saran' as Tab, label: 'Saran', count: saran.length },
              { value: 'berjalan' as Tab, label: 'Berjalan', count: berjalan.length },
              { value: 'selesai' as Tab, label: 'Selesai', count: selesai.length },
          ]
        : [
              { value: 'tugas' as Tab, label: 'Tugas saya', count: berjalan.length },
              { value: 'selesai' as Tab, label: 'Selesai', count: selesai.length },
          ];

    return (
        <AppLayout title="TUSBUG">
            <PageHeader
                title="TUSBUG"
                description={
                    koordinator
                        ? `Pemutusan sementara untuk pelanggan dengan tunggakan ${minLembar} lembar atau lebih. Pilih pelanggan, lalu tugaskan ke biller.`
                        : 'Tugas pemutusan dari koordinator. Laporkan hasil dengan foto sebelum dan sesudah serta lokasi GPS.'
                }
                actions={
                    koordinator &&
                    tab === 'saran' && (
                        <Button disabled={pilih.size === 0} onClick={() => setTugaskanOpen(true)}>
                            <Unplug />
                            Tugaskan {pilih.size > 0 ? `${formatNumber(pilih.size)} pelanggan` : ''}
                        </Button>
                    )
                }
            />

            <SegmentedTabs className="mb-4" label="Daftar TUSBUG" value={tab} onChange={setTab} items={tabs} />

            {tab === 'saran' && (
                <DataTable
                    columns={kolomSaran}
                    data={saran}
                    getRowId={(s) => String(s.pelanggan_id)}
                    rowClassName={(s) => (pilih.has(s.pelanggan_id) ? 'bg-pln-blue-light/60' : undefined)}
                    empty={`Tidak ada pelanggan aktif dengan tunggakan ${minLembar} lembar atau lebih yang belum ditugaskan.`}
                />
            )}
            {(tab === 'berjalan' || tab === 'tugas') &&
                (berjalan.length === 0 ? (
                    <EmptyState icon={Unplug} title={koordinator ? 'Tidak ada TUSBUG berjalan' : 'Tidak ada tugas TUSBUG'}>
                        {koordinator ? 'Tugaskan dari tab Saran.' : 'Tugas baru dari koordinator akan muncul di sini dan di lonceng notifikasi.'}
                    </EmptyState>
                ) : (
                    <DataTable columns={kolomTugas} data={berjalan} getRowId={(t) => String(t.id)} rowClassName={(t) => (t.status === 'revisi' ? 'bg-error-row' : undefined)} />
                ))}
            {tab === 'selesai' && <DataTable columns={kolomTugas} data={selesai} getRowId={(t) => String(t.id)} empty="Belum ada TUSBUG yang selesai." />}

            {koordinator && (
                <TugaskanDialog
                    open={tugaskanOpen}
                    onOpenChange={setTugaskanOpen}
                    dipilih={saran.filter((s) => pilih.has(s.pelanggan_id))}
                    billerOptions={billerOptions}
                    onSelesai={() => setPilih(new Set())}
                />
            )}
        </AppLayout>
    );
}

function TugaskanDialog({
    open,
    onOpenChange,
    dipilih,
    billerOptions,
    onSelesai,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    dipilih: Saran[];
    billerOptions: Option<number>[];
    onSelesai: () => void;
}) {
    const besok = useMemo(() => {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        return d.toISOString().slice(0, 10);
    }, []);
    const { data, setData, post, processing, errors, transform } = useForm({ biller_id: '', tanggal_rencana: besok });
    const err = errors as Record<string, string>;
    const tanpaBiller = dipilih.filter((s) => !s.biller_id).length;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Tugaskan TUSBUG untuk ${dipilih.length} pelanggan`}
            description={`Total tunggakan ${rupiah(dipilih.reduce((n, s) => n + s.total, 0))}. Nomor surat dibuat otomatis dan biller menerima notifikasi.`}
            submitLabel="Tugaskan"
            processing={processing}
            onSubmit={() => {
                transform((d) => ({ ...d, biller_id: d.biller_id || null, pelanggan_ids: dipilih.map((s) => s.pelanggan_id) }));
                post(route('tusbug.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        onOpenChange(false);
                        onSelesai();
                    },
                });
            }}
        >
            <FormField id="biller_id" label="Biller pelaksana" error={err.biller_id} hint={tanpaBiller > 0 && !data.biller_id ? `${tanpaBiller} pelanggan berada di rute tanpa biller; pilih biller tertentu.` : undefined}>
                <NativeSelect id="biller_id" value={data.biller_id} onChange={(e) => setData('biller_id', e.target.value)}>
                    <option value="">Biller pemegang rute masing-masing</option>
                    {billerOptions.map((b) => (
                        <option key={b.value} value={b.value}>
                            {b.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField id="tanggal_rencana" label="Tanggal rencana" required error={err.tanggal_rencana}>
                <Input id="tanggal_rencana" type="date" className="sm:w-52" min={new Date().toISOString().slice(0, 10)} value={data.tanggal_rencana} onChange={(e) => setData('tanggal_rencana', e.target.value)} />
            </FormField>
            {err.pelanggan_ids && <p className="text-[13px] text-error-ink">{err.pelanggan_ids}</p>}
            <ul className="max-h-40 divide-y divide-line-row overflow-y-auto rounded-10 border border-border text-[13px]">
                {dipilih.map((s) => (
                    <li key={s.pelanggan_id} className="flex justify-between gap-3 px-3 py-2">
                        <span className="truncate">{s.nama}</span>
                        <span className="shrink-0 text-muted-ink">{s.lembar} lembar</span>
                    </li>
                ))}
            </ul>
        </FormDialog>
    );
}

