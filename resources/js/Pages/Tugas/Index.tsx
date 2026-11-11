import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, CircleCheck, ListTodo, Map, PencilLine, RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import MeterDigits from '@/Components/shared/MeterDigits';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal, hariIni } from '@/lib/format';
import { urlRute } from '@/lib/geo';
import { statusCatatan, type StatusCatatan } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface Baris {
    id: number;
    urutan: number;
    idpel: string;
    nama: string;
    alamat: string;
    jalan: string;
    jenis: 'pascabayar' | 'prabayar';
    tarif: string;
    stand_lalu: number | null;
    status: StatusCatatan | null;
    anomali: string | null;
    alasan_revisi: string | null;
    url: string;
    latitude: number | null;
    longitude: number | null;
}

type Props = PageProps<{
    periode: { kode: string; nama: string; tanggal_selesai: string } | null;
    penugasan?: { id: number; rute: { kode: string; nama: string; kelurahan: string }; target: number } | null;
    daftarPenugasan?: { id: number; label: string }[];
    progres?: { terkirim: number; target: number; sisa_hari: number; per_hari: number; lewat_batas: boolean };
    skorLalu?: {
        periode: string;
        skor: number;
        peringkat: number | null;
        akurasi: number;
        penyelesaian: number;
        ketepatan_waktu: number;
        bukti_lengkap: number;
    } | null;
    pelanggan?: Baris[];
}>;

type Tab = 'belum' | 'sudah' | 'revisi';

const tabDari = (s: StatusCatatan | null): Tab => (s === 'revisi' ? 'revisi' : !s || s === 'draft' ? 'belum' : 'sudah');

export default function TugasIndex() {
    const props = usePage<Props>().props;
    const { periode, penugasan } = props;

    if (!periode || !penugasan) {
        return (
            <AppLayout title="Tugas hari ini">
                <PageHeader title="Tugas hari ini" description={hariIni()} />
                <EmptyState
                    icon={ListTodo}
                    title={periode ? `Belum ada rute untuk Anda di periode ${periode.nama}` : 'Belum ada periode pencatatan yang berjalan'}
                >
                    Koordinator akan menugaskan rute untuk periode pencatatan. Daftar pelanggan yang harus dicatat muncul di sini
                    sesuai urutan rute.
                </EmptyState>
            </AppLayout>
        );
    }

    return <TugasRute {...(props as RuteProps)} periode={periode} penugasan={penugasan} />;
}

type RuteProps = { [K in keyof Required<Props>]: NonNullable<Props[K]> } & { skorLalu: Props['skorLalu'] };

function TugasRute({ periode, penugasan, daftarPenugasan, progres, skorLalu, pelanggan }: RuteProps) {
    const [tab, setTab] = useState<Tab>(() => {
        const q = new URLSearchParams(window.location.search).get('tab');
        return q === 'sudah' || q === 'revisi' ? q : 'belum';
    });
    const [cari, setCari] = useState('');
    const [jalan, setJalan] = useState('');

    const berikutnya = pelanggan.find((p) => tabDari(p.status) === 'belum') ?? null;
    const revisi = pelanggan.filter((p) => p.status === 'revisi');
    const jumlah = {
        belum: pelanggan.filter((p) => tabDari(p.status) === 'belum').length,
        sudah: pelanggan.filter((p) => tabDari(p.status) === 'sudah').length,
        revisi: revisi.length,
    };
    const daftarJalan = useMemo(() => [...new Set(pelanggan.map((p) => p.jalan))], [pelanggan]);

    const tampil = pelanggan.filter((p) => {
        if (tabDari(p.status) !== tab) return false;
        if (jalan && p.jalan !== jalan) return false;
        if (cari) {
            const q = cari.toLowerCase();
            return p.nama.toLowerCase().includes(q) || p.idpel.includes(q) || p.alamat.toLowerCase().includes(q);
        }
        return true;
    });

    const columns: ColumnDef<Baris>[] = [
        {
            header: 'No.',
            meta: { className: 'w-16' },
            cell: ({ row: { original: p } }) =>
                p.id === berikutnya?.id ? (
                    <span className="inline-flex size-8 items-center justify-center rounded-full bg-pln-yellow font-mono text-[13px] font-semibold text-ink">
                        {String(p.urutan).padStart(3, '0')}
                    </span>
                ) : (
                    <span className="font-mono text-[13px] text-muted-ink">{String(p.urutan).padStart(3, '0')}</span>
                ),
        },
        {
            header: 'Pelanggan',
            cell: ({ row: { original: p } }) => (
                <div className="min-w-0">
                    <p className="font-medium">{p.nama}</p>
                    <p className="text-xs text-muted-ink">{p.alamat}</p>
                    {p.alasan_revisi && <p className="text-xs text-error-ink">Alasan: {p.alasan_revisi}</p>}
                </div>
            ),
        },
        { header: 'ID pelanggan', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.idpel}</span> },
        {
            header: 'Tarif / daya',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p>{p.tarif}</p>
                    <p className="text-xs text-muted-ink">{p.jenis === 'pascabayar' ? 'Pascabayar' : 'Prabayar'}</p>
                </div>
            ),
        },
        {
            header: 'Stand lalu',
            meta: { className: 'text-right' },
            cell: ({ row: { original: p } }) =>
                p.jenis === 'prabayar' ? (
                    <span className="text-xs text-muted-ink">Prabayar</span>
                ) : (
                    <span className="font-mono text-[13px]">{p.stand_lalu !== null ? formatNumber(p.stand_lalu, 1) : '–'}</span>
                ),
        },
        { header: 'Status', cell: ({ row: { original: p } }) => <StatusChip {...statusCatatan(p.status, p.anomali)} /> },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-28 text-right' },
            cell: ({ row: { original: p } }) => {
                const t = tabDari(p.status);
                const utama = p.id === berikutnya?.id;
                return (
                    <Button variant={utama ? 'default' : t === 'revisi' ? 'reject' : 'secondary'} size="sm" asChild>
                        <Link href={p.url}>{t === 'sudah' ? 'Lihat' : t === 'revisi' ? 'Perbaiki' : 'Catat'}</Link>
                    </Button>
                );
            },
        },
    ];

    const persenProgres = progres.target ? (progres.terkirim / progres.target) * 100 : 0;

    return (
        <AppLayout title="Tugas hari ini">
            <PageHeader
                title="Tugas hari ini"
                description={`${hariIni()} · Rute ${penugasan.rute.kode} ${penugasan.rute.nama} · batas ${formatTanggal(periode.tanggal_selesai, 'd MMM yyyy')}`}
                actions={
                    <>
                        {berikutnya?.latitude != null && berikutnya.longitude != null && (
                            <Button variant="secondary" asChild>
                                <a href={urlRute(berikutnya.latitude, berikutnya.longitude)} target="_blank" rel="noreferrer">
                                    <Map />
                                    Lihat di peta
                                </a>
                            </Button>
                        )}
                        {berikutnya && (
                            <Button asChild>
                                <Link href={berikutnya.url}>
                                    Catat pelanggan berikutnya
                                    <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            {daftarPenugasan.length > 1 && (
                <NativeSelect
                    className="mb-4 md:w-80"
                    aria-label="Pilih rute"
                    value={penugasan.id}
                    onChange={(e) => router.get(route('tugas.index'), { penugasan: e.target.value }, { preserveScroll: true })}
                >
                    {daftarPenugasan.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.label}
                        </option>
                    ))}
                </NativeSelect>
            )}

            <div className="mb-6 grid gap-3 lg:grid-cols-3">
                <div className="rounded-14 border border-border p-5">
                    <p className="text-[13px] text-muted-ink">Progres rute {periode.nama}</p>
                    <div className="mt-3 flex flex-wrap items-end gap-3">
                        <MeterDigits value={progres.terkirim} minDigits={3} size="md" aria-label={`${progres.terkirim} terkirim`} />
                        <span className="pb-1 text-sm text-muted-ink">dari {formatNumber(progres.target)} pelanggan</span>
                    </div>
                    <ProgressBar className="mt-4" value={persenProgres} />
                    <p className="mt-2 text-xs text-muted-ink">
                        {progres.terkirim >= progres.target
                            ? 'Target rute tercapai.'
                            : progres.lewat_batas
                              ? 'Batas pencatatan sudah lewat; catatan berikutnya ditandai lewat jadwal.'
                              : `Perlu ${formatNumber(progres.per_hari)} pelanggan per hari, ${formatNumber(progres.sisa_hari)} hari lagi.`}
                    </p>
                </div>

                {revisi.length > 0 ? (
                    <div className="flex flex-col justify-between gap-4 rounded-14 bg-error-tint p-5 text-error-deep">
                        <div className="flex gap-3">
                            <RotateCcw className="mt-0.5 size-4 shrink-0 text-pln-red" />
                            <div>
                                <p className="font-semibold">{revisi.length} catatan dikembalikan</p>
                                <p className="text-sm">
                                    {revisi
                                        .slice(0, 2)
                                        .map((r) => `${r.nama}${r.alasan_revisi ? ` (${r.alasan_revisi.toLowerCase()})` : ''}`)
                                        .join(', ')}
                                    {revisi.length > 2 ? `, dan ${revisi.length - 2} lainnya` : ''}. Perbaiki sebelum batas pencatatan.
                                </p>
                            </div>
                        </div>
                        <Button variant="reject" className="self-start" asChild>
                            <Link href={revisi[0].url}>
                                <PencilLine />
                                Perbaiki sekarang
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="flex gap-3 rounded-14 bg-success-tint p-5 text-success-ink">
                        <CircleCheck className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-semibold">Tidak ada catatan yang dikembalikan</p>
                            <p className="text-sm">Catatan yang ditolak koordinator akan muncul di sini.</p>
                        </div>
                    </div>
                )}

                <div className="rounded-14 border border-border p-5">
                    <p className="text-[13px] text-muted-ink">Skor saya bulan lalu</p>
                    {skorLalu ? (
                        <>
                            <div className="mt-3 flex items-end gap-3">
                                <MeterDigits value={skorLalu.skor} decimals={1} size="md" />
                                {skorLalu.peringkat && <span className="pb-1 text-sm text-muted-ink">peringkat {skorLalu.peringkat}</span>}
                            </div>
                            <p className="mt-3 text-xs text-muted-ink">{saran(skorLalu)}</p>
                        </>
                    ) : (
                        <>
                            <p className="mt-3 text-2xl font-semibold text-muted-ink">–</p>
                            <p className="mt-2 text-xs text-muted-ink">
                                Skor periode sebelumnya belum dihitung. Skor tampil setelah perhitungan kinerja dijalankan.
                            </p>
                        </>
                    )}
                </div>
            </div>

            <Toolbar>
                <SegmentedTabs
                    label="Status pencatatan"
                    value={tab}
                    onChange={setTab}
                    items={[
                        { value: 'belum', label: 'Belum', count: jumlah.belum },
                        { value: 'sudah', label: 'Sudah', count: jumlah.sudah },
                        { value: 'revisi', label: 'Revisi', count: jumlah.revisi },
                    ]}
                />
                <SearchInput className="md:w-72" value={cari} onChange={setCari} placeholder="Cari nama, ID, atau alamat" />
                <NativeSelect className="md:w-56" aria-label="Filter jalan" value={jalan} onChange={(e) => setJalan(e.target.value)}>
                    <option value="">Semua jalan</option>
                    {daftarJalan.map((j) => (
                        <option key={j} value={j}>
                            {j}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>

            <DataTable
                columns={columns}
                data={tampil}
                getRowId={(p) => String(p.id)}
                rowClassName={(p) => cn(p.status === 'revisi' && 'bg-error-row')}
                empty={
                    tab === 'belum'
                        ? 'Semua pelanggan di rute ini sudah dicatat.'
                        : tab === 'revisi'
                          ? 'Tidak ada catatan yang perlu direvisi.'
                          : 'Belum ada catatan yang dikirim.'
                }
            />
        </AppLayout>
    );
}

function saran(s: NonNullable<Props['skorLalu']>): string {
    const komponen: [string, number][] = [
        ['akurasi', s.akurasi],
        ['penyelesaian', s.penyelesaian],
        ['ketepatan waktu', s.ketepatan_waktu],
        ['kelengkapan bukti', s.bukti_lengkap],
    ];
    const [nama, nilai] = komponen.reduce((min, k) => (k[1] < min[1] ? k : min));
    return nilai >= 95 ? 'Pertahankan, semua komponen di atas 95%.' : `Tingkatkan ${nama} (${formatNumber(nilai, 1)}%) untuk menaikkan skor.`;
}
