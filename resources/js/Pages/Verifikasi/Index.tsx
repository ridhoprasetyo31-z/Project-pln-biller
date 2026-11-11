import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, CircleAlert, ClipboardCheck, LoaderCircle, MapPin, Maximize2, RotateCw, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import EmptyState from '@/Components/shared/EmptyState';
import { InputError } from '@/Components/shared/FormField';
import MeterDigits from '@/Components/shared/MeterDigits';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import UsageBars from '@/Components/shared/UsageBars';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif, formatTanggalWaktu } from '@/lib/format';
import { urlPeta } from '@/lib/geo';
import { labelAnomali, statusCatatan, type StatusCatatan } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { Option, PageProps, Paginated } from '@/types';
import type { AnomaliItem, FotoInfo, VerifikasiInfo } from '@/types/catat';

type Tab = 'ditandai' | 'normal' | 'diperiksa';

interface ItemAntrean {
    id: number;
    idpel: string;
    nama: string;
    biller: string;
    dikirim_at: string | null;
    status: StatusCatatan;
    anomali: string[];
    thumb: string | null;
    jumlah_revisi: number;
}

interface Detail {
    id: number;
    tipe: string;
    status: StatusCatatan;
    jenis: 'pascabayar' | 'prabayar';
    periode: string;
    pelanggan: { idpel: string; nama: string; alamat: string; tarif: string; nomor_meter: string; latitude: number | null; longitude: number | null };
    biller: { name: string; nip: string };
    stand_lalu: number | null;
    stand_kini: number | null;
    pemakaian_kwh: number | null;
    sisa_kwh: number | null;
    rata_tiga_bulan: number | null;
    kondisi: string;
    keterangan: string | null;
    latitude: number | null;
    longitude: number | null;
    jarak_m: number | null;
    dikirim_at: string | null;
    tepat_waktu: boolean | null;
    jumlah_revisi: number;
    foto: FotoInfo | null;
    anomali: AnomaliItem[];
    riwayatVerifikasi: VerifikasiInfo[];
    bars: { label: string; nilai: number | null; kini: boolean }[];
    bisaDiputuskan: boolean;
}

type Props = PageProps<{
    tab: Tab;
    jumlah: Record<Tab, number>;
    antrean: Paginated<ItemAntrean>;
    detail: Detail | null;
    alasanOptions: Option[];
}>;

export default function VerifikasiIndex() {
    const { tab, jumlah, antrean, detail, alasanOptions, auth } = usePage<Props>().props;

    const keTab = (t: Tab) => router.get(route('verifikasi.index'), { tab: t }, { preserveScroll: true });

    return (
        <AppLayout title="Verifikasi">
            <PageHeader
                title="Verifikasi"
                description={`Periksa catatan meter dari biller ${auth.user.unit?.nama ?? ''}. Catatan yang disetujui masuk ke perhitungan skor.`}
            />

            <SegmentedTabs
                className="mb-4"
                label="Antrean verifikasi"
                value={tab}
                onChange={keTab}
                items={[
                    { value: 'ditandai', label: 'Ditandai sistem', count: jumlah.ditandai },
                    { value: 'normal', label: 'Normal', count: jumlah.normal },
                    { value: 'diperiksa', label: 'Sudah diperiksa', count: jumlah.diperiksa },
                ]}
            />

            {antrean.data.length === 0 ? (
                <EmptyState icon={ClipboardCheck} title={tab === 'diperiksa' ? 'Belum ada catatan yang diperiksa' : 'Antrean kosong'}>
                    {tab === 'ditandai'
                        ? 'Tidak ada catatan yang ditandai sistem saat ini.'
                        : tab === 'normal'
                          ? 'Semua catatan normal sudah diperiksa.'
                          : 'Catatan yang Anda setujui atau tolak akan muncul di sini.'}
                </EmptyState>
            ) : (
                <div className="grid gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <DaftarAntrean tab={tab} antrean={antrean} dipilih={detail?.id ?? null} />
                    {detail ? <PanelDetail key={detail.id} detail={detail} tab={tab} alasanOptions={alasanOptions} /> : null}
                </div>
            )}
        </AppLayout>
    );
}

function DaftarAntrean({ tab, antrean, dipilih }: { tab: Tab; antrean: Paginated<ItemAntrean>; dipilih: number | null }) {
    const pindah = (url: string | null) => url && router.get(url, {}, { preserveState: true });

    return (
        <div className="grid content-start gap-2">
            <ul className="grid max-h-none gap-2 lg:max-h-[calc(100dvh-260px)] lg:overflow-y-auto lg:pr-1">
                {antrean.data.map((item) => {
                    const aktif = item.id === dipilih;
                    return (
                        <li key={item.id}>
                            <Link
                                href={route('verifikasi.index', { tab, id: item.id, page: antrean.current_page })}
                                preserveScroll
                                preserveState
                                aria-current={aktif ? 'true' : undefined}
                                className={cn(
                                    'flex gap-3 rounded-14 border p-3 transition-colors',
                                    aktif ? 'border-pln-blue bg-pln-blue-light ring-1 ring-pln-blue' : 'border-border bg-white hover:bg-table-head',
                                )}
                            >
                                {item.thumb ? (
                                    <img src={item.thumb} alt="" className="size-14 shrink-0 rounded-10 bg-ink object-cover" />
                                ) : (
                                    <span className="size-14 shrink-0 rounded-10 bg-ink" />
                                )}
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-baseline justify-between gap-2">
                                        <span className="truncate font-mono text-xs text-muted-ink">{item.idpel}</span>
                                        <span className="shrink-0 text-xs text-muted-ink">{formatRelatif(item.dikirim_at)}</span>
                                    </span>
                                    <span className="block truncate text-sm font-medium text-ink">{item.nama}</span>
                                    <span className="mt-1 flex flex-wrap gap-1">
                                        {item.anomali.map((a) => (
                                            <span key={a} className="rounded-full bg-error-tint px-2 py-0.5 text-[11px] font-medium text-error-ink">
                                                {labelAnomali(a)}
                                            </span>
                                        ))}
                                        {tab === 'diperiksa' && <StatusChip className="py-0.5 text-[11px]" {...statusCatatan(item.status)} />}
                                    </span>
                                    <span className="mt-1 block truncate text-xs text-muted-ink">
                                        {item.biller}
                                        {item.jumlah_revisi > 0 ? ` · revisi ke-${item.jumlah_revisi}` : ''}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
            {antrean.last_page > 1 && (
                <div className="flex items-center justify-between gap-2 text-[13px] text-muted-ink">
                    <Button variant="secondary" size="icon-sm" disabled={!antrean.prev_page_url} onClick={() => pindah(antrean.prev_page_url)}>
                        <ChevronLeft />
                        <span className="sr-only">Sebelumnya</span>
                    </Button>
                    {formatNumber(antrean.from ?? 0)}–{formatNumber(antrean.to ?? 0)} dari {formatNumber(antrean.total)}
                    <Button variant="secondary" size="icon-sm" disabled={!antrean.next_page_url} onClick={() => pindah(antrean.next_page_url)}>
                        <ChevronRight />
                        <span className="sr-only">Berikutnya</span>
                    </Button>
                </div>
            )}
        </div>
    );
}

function PanelDetail({ detail: d, tab, alasanOptions }: { detail: Detail; tab: Tab; alasanOptions: Option[] }) {
    const pasca = d.jenis === 'pascabayar';
    const adaAnomali = d.anomali.length > 0;
    const [menyetujui, setMenyetujui] = useState(false);
    const tolak = useForm({ alasan: '', catatan: '', tab });

    const setujui = () =>
        router.post(
            route('verifikasi.setujui', { tipe: d.tipe, id: d.id }),
            { tab },
            {
                preserveScroll: true,
                onStart: () => setMenyetujui(true),
                onFinish: () => setMenyetujui(false),
                onError: (e) => toast.error(e.catatan ?? 'Catatan gagal disetujui.'),
            },
        );

    const kirimTolak = () =>
        tolak.post(route('verifikasi.tolak', { tipe: d.tipe, id: d.id }), {
            preserveScroll: true,
            onError: (e) => e.catatan && !e.alasan && toast.error(e.catatan),
        });

    const terakhir = d.riwayatVerifikasi[0];
    const proses = menyetujui || tolak.processing;

    return (
        <section aria-label="Detail catatan" className="overflow-hidden rounded-14 border border-border">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line-row px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-section-title">{d.pelanggan.nama}</h2>
                    <p className="text-[13px] text-muted-ink">
                        <span className="font-mono">{d.pelanggan.idpel}</span> · {d.pelanggan.alamat} · {d.pelanggan.tarif}
                    </p>
                </div>
                <StatusChip {...statusCatatan(d.status, d.anomali[0]?.jenis)} />
            </header>

            <div className="grid gap-5 p-5">
                <Stepper d={d} terakhir={terakhir} />

                <div className="grid gap-5 xl:grid-cols-2">
                    <FotoPanel foto={d.foto} />

                    <div className="grid content-start gap-4">
                        {pasca ? (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <div>
                                    <p className="mb-1.5 text-[13px] text-muted-ink">Stand bulan lalu</p>
                                    {d.stand_lalu !== null ? <MeterDigits value={d.stand_lalu} decimals={1} size="sm" /> : <span className="text-sm">–</span>}
                                </div>
                                <div>
                                    <p className="mb-1.5 text-[13px] text-muted-ink">Stand diketik biller</p>
                                    {d.stand_kini !== null ? <MeterDigits value={d.stand_kini} decimals={1} size="sm" /> : <span className="text-sm">Tidak terbaca</span>}
                                </div>
                            </div>
                        ) : (
                            <div>
                                <p className="mb-1.5 text-[13px] text-muted-ink">Sisa kWh diketik biller</p>
                                {d.sisa_kwh !== null ? <MeterDigits value={d.sisa_kwh} decimals={2} size="sm" /> : '–'}
                            </div>
                        )}

                        {pasca && (
                            <p className="text-sm">
                                Pemakaian{' '}
                                <span className={cn('font-mono font-semibold', adaAnomali && 'text-error-ink')}>
                                    {d.pemakaian_kwh !== null ? `${formatNumber(d.pemakaian_kwh, 1)} kWh` : '–'}
                                </span>
                                {d.rata_tiga_bulan !== null && (
                                    <span className="text-muted-ink"> · rata-rata 3 bulan {formatNumber(d.rata_tiga_bulan, 1)} kWh</span>
                                )}
                            </p>
                        )}

                        <div>
                            <p className="mb-2 text-[13px] text-muted-ink">{pasca ? 'Pemakaian 4 bulan (kWh)' : 'Sisa kWh 4 bulan'}</p>
                            <UsageBars data={d.bars} anomali={adaAnomali} tinggi={110} />
                        </div>
                    </div>
                </div>

                {d.anomali.map((a) => (
                    <Notice key={a.jenis} tone="danger" title={`Ditandai sistem: ${a.label.toLowerCase()}`}>
                        {a.keterangan}
                    </Notice>
                ))}

                <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                    <Info label="Lokasi saat mencatat">
                        {d.jarak_m !== null ? (
                            <span className={cn(adaAnomali && d.anomali.some((a) => a.jenis === 'lokasi_jauh') && 'text-error-ink')}>
                                {formatNumber(d.jarak_m)} m dari titik pelanggan
                            </span>
                        ) : (
                            <span className="text-error-ink">Tanpa GPS</span>
                        )}
                        {d.latitude !== null && d.longitude !== null && (
                            <a
                                href={urlPeta(d.latitude, d.longitude)}
                                target="_blank"
                                rel="noreferrer"
                                className="ml-2 inline-flex items-center gap-1 text-pln-blue hover:underline"
                            >
                                <MapPin className="size-3.5" />
                                Peta
                            </a>
                        )}
                    </Info>
                    <Info label="Waktu kirim">
                        {formatTanggalWaktu(d.dikirim_at)}
                        {d.tepat_waktu === false && <span className="text-error-ink"> · lewat batas</span>}
                    </Info>
                    <Info label="Kondisi meter">{d.kondisi}</Info>
                    <Info label="Biller">
                        {d.biller.name} <span className="font-mono text-xs text-muted-ink">{d.biller.nip}</span>
                    </Info>
                    <Info label="Nomor meter">
                        <span className="font-mono">{d.pelanggan.nomor_meter}</span>
                    </Info>
                    <Info label="Revisi">{d.jumlah_revisi > 0 ? `Sudah ${d.jumlah_revisi} kali dikembalikan` : 'Belum pernah'}</Info>
                    {d.keterangan && (
                        <div className="sm:col-span-2">
                            <Info label="Keterangan biller">{d.keterangan}</Info>
                        </div>
                    )}
                </dl>

                {d.riwayatVerifikasi.length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-semibold">Riwayat verifikasi</h3>
                        <ul className="grid gap-1.5 text-[13px]">
                            {d.riwayatVerifikasi.map((v, i) => (
                                <li key={i} className="flex flex-wrap gap-x-2 text-muted-ink">
                                    <span className={v.keputusan === 'disetujui' ? 'text-success-ink' : 'text-error-ink'}>
                                        {v.keputusan === 'disetujui' ? 'Disetujui' : `Ditolak: ${v.alasan_label}`}
                                    </span>
                                    {v.catatan && <span>“{v.catatan}”</span>}
                                    <span>
                                        · {v.oleh} · {formatTanggalWaktu(v.waktu)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            {d.bisaDiputuskan && (
                <footer className="grid gap-4 border-t border-line-row bg-table-head/60 px-5 py-4">
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-[13px] font-medium">Alasan bila ditolak</legend>
                        <div className="flex flex-wrap gap-2">
                            {alasanOptions.map((o) => (
                                <label
                                    key={o.value}
                                    className={cn(
                                        'inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-9 border px-3 text-[13px] has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pln-blue/25',
                                        tolak.data.alasan === o.value
                                            ? 'border-pln-red bg-error-tint text-error-deep'
                                            : 'border-border bg-white text-ink hover:bg-table-head',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="alasan"
                                        value={o.value}
                                        checked={tolak.data.alasan === o.value}
                                        onChange={() => tolak.setData('alasan', o.value)}
                                        className="sr-only"
                                    />
                                    {o.label}
                                </label>
                            ))}
                        </div>
                        <InputError message={tolak.errors.alasan} />
                    </fieldset>
                    {tolak.data.alasan && (
                        <div className="grid gap-2">
                            <Label htmlFor="catatan-tolak">
                                Catatan untuk biller
                                {tolak.data.alasan === 'lainnya' && (
                                    <span className="text-pln-red" aria-hidden>
                                        *
                                    </span>
                                )}
                            </Label>
                            <Textarea
                                id="catatan-tolak"
                                rows={2}
                                maxLength={500}
                                placeholder="Contoh: angka digit terakhir tidak terlihat, foto ulang dari dekat."
                                value={tolak.data.catatan}
                                onChange={(e) => tolak.setData('catatan', e.target.value)}
                            />
                            <InputError message={tolak.errors.catatan} />
                        </div>
                    )}
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button variant="reject" disabled={proses} onClick={kirimTolak}>
                            {tolak.processing ? <LoaderCircle className="animate-spin" /> : <X />}
                            Tolak dan minta revisi
                        </Button>
                        <Button disabled={proses} onClick={setujui}>
                            {menyetujui ? <LoaderCircle className="animate-spin" /> : <Check />}
                            Setujui dan lanjut
                        </Button>
                    </div>
                </footer>
            )}
        </section>
    );
}

function Stepper({ d, terakhir }: { d: Detail; terakhir?: VerifikasiInfo }) {
    const adaAnomali = d.anomali.length > 0;
    const langkah: { judul: string; ket: string; tone: 'biru' | 'merah' | 'kuning' | 'hijau'; ikon: ReactNode }[] = [
        { judul: 'Dicatat biller', ket: formatTanggalWaktu(d.dikirim_at), tone: 'biru', ikon: <Check className="size-3.5" strokeWidth={3} /> },
        {
            judul: 'Validasi sistem',
            ket: adaAnomali ? `${d.anomali.length} hal ditandai` : 'Semua aturan lolos',
            tone: adaAnomali ? 'merah' : 'biru',
            ikon: adaAnomali ? <span className="text-xs font-bold">!</span> : <Check className="size-3.5" strokeWidth={3} />,
        },
        d.bisaDiputuskan
            ? { judul: 'Verifikasi', ket: 'Giliran Anda', tone: 'kuning', ikon: <span className="text-xs font-bold">3</span> }
            : {
                  judul: 'Verifikasi',
                  ket: terakhir ? `${terakhir.keputusan === 'disetujui' ? 'Disetujui' : 'Ditolak'} ${terakhir.oleh ?? ''}` : 'Selesai',
                  tone: d.status === 'disetujui' ? 'hijau' : 'merah',
                  ikon: d.status === 'disetujui' ? <Check className="size-3.5" strokeWidth={3} /> : <X className="size-3.5" strokeWidth={3} />,
              },
    ];

    const warna = {
        biru: 'bg-pln-blue text-white',
        merah: 'bg-pln-red text-white',
        kuning: 'bg-pln-yellow text-ink',
        hijau: 'bg-success-ink text-white',
    };

    return (
        <ol className="grid gap-3 sm:grid-cols-3">
            {langkah.map((l, i) => (
                <li key={l.judul} className="flex items-center gap-2.5">
                    <span className={cn('inline-flex size-7 shrink-0 items-center justify-center rounded-full', warna[l.tone])}>{l.ikon}</span>
                    <span className="min-w-0">
                        <span className="block text-[13px] font-medium">{l.judul}</span>
                        <span className={cn('block truncate text-xs', l.tone === 'merah' ? 'text-error-ink' : 'text-muted-ink')}>{l.ket}</span>
                    </span>
                    {i < langkah.length - 1 && <span aria-hidden className="ml-auto hidden h-px flex-1 bg-line sm:block" />}
                </li>
            ))}
        </ol>
    );
}

function FotoPanel({ foto }: { foto: FotoInfo | null }) {
    const [putar, setPutar] = useState(0);
    const [besar, setBesar] = useState(false);

    if (!foto) {
        return (
            <div className="flex aspect-[4/3] items-center justify-center rounded-14 bg-ink text-sm text-white/70">
                <CircleAlert className="mr-2 size-4" />
                Tanpa foto
            </div>
        );
    }

    return (
        <div className="grid content-start gap-2">
            <div className="relative aspect-[4/3] overflow-hidden rounded-14 bg-ink">
                <img
                    src={foto.url}
                    alt="Foto meter dari biller"
                    className="h-full w-full object-contain transition-transform"
                    style={{ transform: `rotate(${putar}deg)` }}
                />
                <div className="absolute right-2 bottom-2 flex gap-1.5">
                    <button
                        type="button"
                        onClick={() => setPutar((p) => (p + 90) % 360)}
                        className="inline-flex size-10 items-center justify-center rounded-9 bg-white/90 text-ink hover:bg-white"
                    >
                        <RotateCw className="size-4" />
                        <span className="sr-only">Putar foto</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setBesar(true)}
                        className="inline-flex size-10 items-center justify-center rounded-9 bg-white/90 text-ink hover:bg-white"
                    >
                        <Maximize2 className="size-4" />
                        <span className="sr-only">Perbesar foto</span>
                    </button>
                </div>
            </div>
            <p className="text-xs text-muted-ink">
                Diambil {formatTanggalWaktu(foto.diambil_at)} · {formatNumber(foto.ukuran_kb)} KB
            </p>
            <Dialog open={besar} onOpenChange={setBesar}>
                <DialogContent className="max-w-5xl bg-ink p-2">
                    <DialogTitle className="sr-only">Foto meter</DialogTitle>
                    <DialogDescription className="sr-only">Foto meter ukuran penuh</DialogDescription>
                    <img
                        src={foto.url}
                        alt="Foto meter ukuran penuh"
                        className="max-h-[85dvh] w-full object-contain"
                        style={{ transform: `rotate(${putar}deg)` }}
                    />
                </DialogContent>
            </Dialog>
        </div>
    );
}

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 border-b border-line-row py-2.5 sm:block">
            <dt className="text-[13px] text-muted-ink">{label}</dt>
            <dd className="text-right sm:mt-0.5 sm:text-left">{children}</dd>
        </div>
    );
}
