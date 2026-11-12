import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Download, FileSpreadsheet, FileText, RefreshCw, Trophy } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import MeterDigits from '@/Components/shared/MeterDigits';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatCard, { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import Tren from '@/Components/shared/Tren';
import TrendBars, { type TrendPoint } from '@/Components/shared/TrendBars';
import UserAvatar from '@/Components/shared/UserAvatar';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatPercent, formatRelatif, formatTanggalWaktu } from '@/lib/format';
import { KOMPONEN, rumusSkor, STATUS_KINERJA, type BarisPeringkat, type Bobot } from '@/lib/kinerja';
import { labelAnomali } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { Option, PageProps, UnitOption } from '@/types';

interface Ringkasan {
    terkirim: number;
    disetujui: number;
    target: number;
    akurasi_tim: number | null;
    terverifikasi: number;
    menunggu: number;
    ditandai: number;
    anomali: number;
}

interface AnomaliBaru {
    id: number;
    catatan_id: number;
    jenis: string;
    label: string;
    keterangan: string;
    pelanggan: string;
    idpel: string;
    biller: string;
    waktu: string;
}

type Props = PageProps<{
    filters: { periode: string | null; unit_id: number | null };
    periodeOptions: Option[];
    unitOptions: UnitOption[] | null;
    unitNama: string;
    periodeBerjalan: { id: number; kode: string } | null;
    bisaVerifikasi: boolean;
    periode: { kode: string; nama: string; final: boolean; berjalan: boolean; dihitung_at: string | null; sebelum: string | null } | null;
    peringkat?: BarisPeringkat[];
    ringkasan?: Ringkasan;
    tren?: TrendPoint[];
    anomali?: AnomaliBaru[];
    ruteOptions?: Option<number>[];
    bobot?: Bobot;
}>;

export default function DashboardIndex() {
    const props = usePage<Props>().props;
    const { filters, periodeOptions, unitOptions, unitNama, periodeBerjalan, periode } = props;
    const [menghitung, setMenghitung] = useState(false);

    const muat = (patch: Partial<Props['filters']>) => {
        const q = { ...filters, ...patch };
        router.get(
            route('dashboard'),
            Object.fromEntries(Object.entries(q).filter(([, v]) => v !== null && v !== '')),
            { preserveScroll: true, preserveState: true },
        );
    };

    const hitungUlang = () =>
        router.post(route('kinerja.hitung'), {}, { preserveScroll: true, onStart: () => setMenghitung(true), onFinish: () => setMenghitung(false) });

    const unduh = (jenis: 'excel' | 'pdf') => {
        if (!periode) return;
        const q = filters.unit_id ? `?unit_id=${filters.unit_id}` : '';
        window.location.href = route(`laporan.${jenis}`, periode.kode) + q;
    };

    return (
        <AppLayout title="Dashboard kinerja">
            <PageHeader
                title="Dashboard kinerja"
                description={
                    periode
                        ? `${unitNama} · ${periode.nama} · ${periode.final ? 'skor final' : `skor sementara, dihitung ${formatRelatif(periode.dihitung_at)}`}`
                        : unitNama
                }
                actions={
                    <>
                        {unitOptions && (
                            <NativeSelect
                                className="w-44"
                                aria-label="Unit"
                                value={filters.unit_id ?? ''}
                                onChange={(e) => muat({ unit_id: e.target.value ? Number(e.target.value) : null })}
                            >
                                <option value="">Semua unit</option>
                                {unitOptions.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.nama}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                        {periodeOptions.length > 0 && (
                            <NativeSelect
                                className="w-56"
                                aria-label="Periode"
                                value={filters.periode ?? ''}
                                onChange={(e) => muat({ periode: e.target.value })}
                            >
                                {periodeOptions.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                        {periode?.berjalan && (
                            <Button variant="secondary" onClick={hitungUlang} disabled={menghitung}>
                                <RefreshCw className={cn(menghitung && 'animate-spin')} />
                                Hitung ulang
                            </Button>
                        )}
                        {periode && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button>
                                        <Download />
                                        Unduh laporan
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onSelect={() => unduh('excel')}>
                                        <FileSpreadsheet />
                                        Excel (.xlsx)
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onSelect={() => unduh('pdf')}>
                                        <FileText />
                                        PDF
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </>
                }
            />

            {!periode ? (
                <EmptyState
                    icon={Trophy}
                    title="Skor kinerja belum dihitung"
                    action={
                        periodeBerjalan && (
                            <Button onClick={hitungUlang} disabled={menghitung}>
                                <RefreshCw className={cn(menghitung && 'animate-spin')} />
                                Hitung skor periode {periodeBerjalan.kode}
                            </Button>
                        )
                    }
                >
                    Skor dihitung otomatis tiap malam pukul 01.00 dan saat periode ditutup.
                </EmptyState>
            ) : (
                <IsiDashboard {...(props as Required<Props>)} />
            )}
        </AppLayout>
    );
}

function IsiDashboard({ periode, peringkat, ringkasan, tren, anomali, ruteOptions, bobot, bisaVerifikasi }: Required<Props>) {
    const p = periode!;
    const juara = peringkat[0];

    return (
        <>
            <div className="mb-6 grid gap-3 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                {juara ? <KartuJuara juara={juara} sebelum={p.sebelum} /> : <div />}
                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        label="Pelanggan tercatat"
                        value={
                            <>
                                {formatNumber(ringkasan.terkirim)}
                                <span className="text-base font-normal text-muted-ink"> / {formatNumber(ringkasan.target)}</span>
                            </>
                        }
                        progress={ringkasan.target ? (ringkasan.terkirim / ringkasan.target) * 100 : 0}
                        caption={`${formatNumber(ringkasan.disetujui)} sudah disetujui`}
                    />
                    <StatCard
                        label="Akurasi tim"
                        value={ringkasan.akurasi_tim !== null ? formatPercent(ringkasan.akurasi_tim) : '–'}
                        caption={`Dari ${formatNumber(ringkasan.terverifikasi)} catatan terverifikasi`}
                    />
                    <StatCard
                        label="Menunggu verifikasi"
                        value={formatNumber(ringkasan.menunggu)}
                        caption={
                            bisaVerifikasi && ringkasan.menunggu > 0 ? (
                                <Link href={route('verifikasi.index')} className="inline-flex items-center gap-1 font-medium text-pln-blue hover:underline">
                                    Buka antrean
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            ) : (
                                `${formatNumber(ringkasan.ditandai)} ditandai sistem`
                            )
                        }
                    />
                    <StatCard
                        label="Anomali"
                        value={formatNumber(ringkasan.anomali)}
                        valueClassName={ringkasan.anomali > 0 ? 'text-pln-red' : undefined}
                        caption="Ditandai sistem pada periode ini"
                    />
                </div>
            </div>

            <TabelPeringkat peringkat={peringkat} ruteOptions={ruteOptions} bobot={bobot} final={p.final} />

            <div className="mt-6 grid gap-3 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                <section className="rounded-14 border border-border p-5">
                    <h2 className="text-section-title">Rata-rata skor 6 periode</h2>
                    <p className="mb-4 text-[13px] text-muted-ink">Rata-rata skor semua biller dalam cakupan ini per periode.</p>
                    <TrendBars data={tren} sorot={p.kode} />
                </section>

                <section className="rounded-14 border border-border p-5">
                    <h2 className="text-section-title">Anomali terbaru</h2>
                    <p className="mb-3 text-[13px] text-muted-ink">Catatan ditandai sistem yang belum diputuskan.</p>
                    {anomali.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-ink">Tidak ada anomali yang menunggu.</p>
                    ) : (
                        <ul className="divide-y divide-line-row">
                            {anomali.map((a) => (
                                <li key={a.id} className="flex items-center gap-3 py-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-full bg-error-tint px-2 py-0.5 text-[11px] font-medium text-error-ink">
                                                {labelAnomali(a.jenis)}
                                            </span>
                                            <span className="text-xs text-muted-ink">{formatRelatif(a.waktu)}</span>
                                        </div>
                                        <p className="mt-1 truncate text-sm font-medium">
                                            {a.pelanggan} <span className="font-mono text-xs font-normal text-muted-ink">{a.idpel}</span>
                                        </p>
                                        <p className="truncate text-xs text-muted-ink">
                                            {a.biller} · {a.keterangan}
                                        </p>
                                    </div>
                                    {bisaVerifikasi && (
                                        <Button variant="secondary" size="sm" asChild>
                                            <Link href={route('verifikasi.index', { tab: 'ditandai', id: a.catatan_id })}>Periksa</Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {p.dihitung_at && <p className="mt-4 text-xs text-muted-ink">Skor dihitung {formatTanggalWaktu(p.dihitung_at)}.</p>}
        </>
    );
}

function KartuJuara({ juara, sebelum }: { juara: BarisPeringkat; sebelum: string | null }) {
    const naik = juara.tren !== null && juara.tren >= 0;
    return (
        <section aria-label="Biller terbaik" className="rounded-14 bg-pln-yellow p-5 text-ink">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-center gap-3">
                    <UserAvatar name={juara.nama} src={juara.foto_url} size={48} tone="dark" />
                    <div>
                        <p className="text-[13px] font-medium">Biller terbaik</p>
                        <p className="text-lg leading-tight font-semibold">{juara.nama}</p>
                        <p className="text-[13px]">
                            {juara.rute.map((r) => r.kode).join(', ')}
                            {juara.unit ? ` · ${juara.unit}` : ''}
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <MeterDigits value={juara.skor} decimals={1} size="lg" className="hidden sm:inline-flex" aria-label={`Skor ${formatNumber(juara.skor, 1)}`} />
                    <MeterDigits value={juara.skor} decimals={1} size="md" className="sm:hidden" aria-label={`Skor ${formatNumber(juara.skor, 1)}`} />
                    {juara.tren !== null && sebelum && (
                        <p className="mt-2">
                            <span className={cn('inline-flex rounded-full px-2.5 py-1 text-xs font-medium', naik ? 'bg-success-tint text-success-ink' : 'bg-error-tint text-error-ink')}>
                                {naik ? 'Naik' : 'Turun'} {formatNumber(Math.abs(juara.tren), 1)} dari {sebelum.split(' ')[0]}
                            </span>
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-5 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                {KOMPONEN.map((k) => (
                    <div key={k.key}>
                        <div className="flex justify-between text-[13px]">
                            <span>{k.label}</span>
                            <span className="font-mono font-medium">{formatPercent(juara[k.key])}</span>
                        </div>
                        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/55">
                            <div className="h-full rounded-full bg-ink" style={{ width: `${Math.min(100, juara[k.key])}%` }} />
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function TabelPeringkat({ peringkat, ruteOptions, bobot, final }: { peringkat: BarisPeringkat[]; ruteOptions: Option<number>[]; bobot: Bobot; final: boolean }) {
    const [tab, setTab] = useState<'semua' | 'pembinaan'>('semua');
    const [cari, setCari] = useState('');
    const [rute, setRute] = useState('');

    const perluPembinaan = peringkat.filter((b) => b.status !== null);
    const tampil = peringkat.filter((b) => {
        if (tab === 'pembinaan' && b.status === null) return false;
        if (rute && !b.rute.some((r) => String(r.id) === rute)) return false;
        if (cari) {
            const q = cari.toLowerCase();
            return b.nama.toLowerCase().includes(q) || b.nip.includes(q);
        }
        return true;
    });

    const persen = (k: 'akurasi' | 'ketepatan_waktu' | 'bukti_lengkap', label: string): ColumnDef<BarisPeringkat> => ({
        header: label,
        meta: { className: 'text-right' },
        cell: ({ row }) => <span className="font-mono text-[13px]">{formatPercent(row.original[k])}</span>,
    });

    const columns: ColumnDef<BarisPeringkat>[] = [
        {
            header: '#',
            meta: { className: 'w-14' },
            cell: ({ row: { original: b } }) =>
                b.peringkat <= 3 ? (
                    <span className="inline-flex size-8 items-center justify-center rounded-full bg-pln-yellow font-mono text-[13px] font-semibold text-ink">
                        {b.peringkat}
                    </span>
                ) : (
                    <span className="inline-flex size-8 items-center justify-center font-mono text-[13px] text-muted-ink">{b.peringkat}</span>
                ),
        },
        {
            header: 'Biller',
            cell: ({ row: { original: b } }) => (
                <div className="flex items-center gap-3">
                    <UserAvatar name={b.nama} src={b.foto_url} size={34} />
                    <div className="min-w-0">
                        <p className="font-medium">{b.nama}</p>
                        <p className="font-mono text-xs text-muted-ink">{b.nip}</p>
                        {b.status && <StatusChip className="mt-1 py-0.5 text-[11px]" {...STATUS_KINERJA[b.status]} />}
                    </div>
                </div>
            ),
        },
        {
            header: 'Rute',
            cell: ({ row: { original: b } }) => (
                <div className="text-[13px]">
                    <p className="font-mono">{b.rute.map((r) => r.kode).join(', ') || '–'}</p>
                    {b.unit && <p className="text-xs text-muted-ink">{b.unit}</p>}
                </div>
            ),
        },
        {
            header: 'Tercatat / target',
            meta: { className: 'w-40' },
            cell: ({ row: { original: b } }) => (
                <div>
                    <p className="font-mono text-[13px]">
                        {formatNumber(b.tercatat)} / {formatNumber(b.target)}
                    </p>
                    <ProgressBar className="mt-1.5" value={b.penyelesaian} />
                </div>
            ),
        },
        persen('akurasi', 'Akurasi'),
        persen('ketepatan_waktu', 'Tepat waktu'),
        persen('bukti_lengkap', 'Bukti'),
        {
            header: 'Skor',
            meta: { className: 'text-right' },
            cell: ({ row: { original: b } }) => <MeterDigits value={b.skor} decimals={1} size="sm" aria-label={`Skor ${formatNumber(b.skor, 1)}`} />,
        },
        { header: 'Tren', meta: { className: 'text-right' }, cell: ({ row }) => <Tren nilai={row.original.tren} /> },
    ];

    return (
        <section>
            <div className="mb-3">
                <h2 className="text-section-title">Peringkat biller</h2>
                <p className="font-mono text-xs text-muted-ink">{rumusSkor(bobot)}</p>
                {!final && (
                    <p className="mt-1 text-[13px] text-muted-ink">Periode masih berjalan: penyelesaian terus naik sampai periode ditutup, jadi label pembinaan belum diberikan.</p>
                )}
            </div>
            <Toolbar>
                <SegmentedTabs
                    label="Filter peringkat"
                    value={tab}
                    onChange={setTab}
                    items={[
                        { value: 'semua', label: 'Semua', count: peringkat.length },
                        { value: 'pembinaan', label: 'Perlu pembinaan', count: perluPembinaan.length },
                    ]}
                />
                <SearchInput className="md:w-64" value={cari} onChange={setCari} placeholder="Cari nama atau NIP" />
                <NativeSelect className="md:w-56" aria-label="Filter rute" value={rute} onChange={(e) => setRute(e.target.value)}>
                    <option value="">Semua rute</option>
                    {ruteOptions.map((r) => (
                        <option key={r.value} value={r.value}>
                            {r.label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>
            <DataTable
                columns={columns}
                data={tampil}
                getRowId={(b) => String(b.biller_id)}
                empty={tab === 'pembinaan' ? 'Tidak ada biller yang perlu pembinaan.' : 'Tidak ada biller yang cocok.'}
            />
        </section>
    );
}
