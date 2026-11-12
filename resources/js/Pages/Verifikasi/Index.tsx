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
import TabelPerbandingan, { type BarisPerbandingan } from '@/Components/shared/TabelPerbandingan';
import UsageBars from '@/Components/shared/UsageBars';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { urlPeta } from '@/lib/geo';
import { labelAnomali, statusCatatan, type StatusCatatan } from '@/lib/status';
import { rupiah } from '@/lib/tagihan';
import { cn } from '@/lib/utils';
import type { Option, PageProps, Paginated } from '@/types';
import type { AnomaliItem, FotoInfo, VerifikasiInfo } from '@/types/catat';

type Tab = 'ditandai' | 'normal' | 'diperiksa';
type Tipe = 'pencatatan_meter' | 'kunjungan_penagihan' | 'koreksi_rekening' | 'pdil' | 'tusbug';

interface ItemAntrean {
    tipe: Tipe;
    id: number;
    jenis_label: string;
    idpel: string;
    nama: string;
    biller: string;
    waktu: string | null;
    keterangan: string | null;
    status: StatusCatatan;
    anomali: string[];
    thumb: string | null;
    jumlah_revisi: number;
}

interface Pelanggan {
    idpel: string;
    nama: string;
    alamat: string;
    tarif: string;
    nomor_meter?: string;
    latitude: number | null;
    longitude: number | null;
}

interface DasarDetail {
    tipe: Tipe;
    jenis_label: string;
    id: number;
    status: StatusCatatan;
    jumlah_revisi: number;
    anomali: AnomaliItem[];
    riwayatVerifikasi: VerifikasiInfo[];
    bisaDiputuskan: boolean;
    pelanggan: Pelanggan;
    biller: { name: string; nip: string };
    foto: FotoInfo | null;
}

interface DetailMeter extends DasarDetail {
    tipe: 'pencatatan_meter';
    jenis: 'pascabayar' | 'prabayar';
    periode: string;
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
    bars: { label: string; nilai: number | null; kini: boolean }[];
}

interface DetailKunjungan extends DasarDetail {
    tipe: 'kunjungan_penagihan';
    invoice: { id: number; nomor: string; periode: string; total: number; jatuh_tempo: string; status: string; status_label: string };
    hasil: string;
    hasil_label: string;
    tanggal_janji: string | null;
    nominal_dibayar: number | null;
    keterangan: string | null;
    latitude: number | null;
    longitude: number | null;
    jarak_m: number | null;
    dikunjungi_at: string | null;
    kunjungan_sebelumnya: number;
}

interface DetailKoreksi extends DasarDetail {
    tipe: 'koreksi_rekening';
    periode: string;
    invoice: { id: number; nomor: string; status: string; status_label: string } | null;
    pencatat: string | null;
    penyebab: string;
    penyebab_label: string;
    kurangi_akurasi: boolean;
    alasan: string;
    stand_lalu: number | null;
    stand_lama: number;
    stand_baru: number;
    kwh_lama: number;
    kwh_baru: number;
    tagihan_lama: number;
    tagihan_baru: number;
    diajukan_at: string;
    foto_asli: FotoInfo | null;
}

interface DetailPdil extends DasarDetail {
    tipe: 'pdil';
    jenis: 'pelanggan_baru' | 'perubahan_data';
    jenis_pdil_label: string;
    rute: string;
    perbandingan: BarisPerbandingan[];
    keterangan: string | null;
    diajukan_at: string;
    foto_meter: FotoInfo | null;
}

interface DetailTusbug extends DasarDetail {
    tipe: 'tusbug';
    nomor_surat: string;
    penugas: string;
    tanggal_rencana: string;
    jumlah_lembar: number;
    total_tunggakan: number;
    invoice: { nomor: string; total: number; status_label: string }[];
    hasil: string | null;
    hasil_label: string | null;
    efek: string;
    keterangan: string | null;
    latitude: number | null;
    longitude: number | null;
    jarak_m: number | null;
    dilaksanakan_at: string | null;
    foto_sesudah: FotoInfo | null;
}

type Detail = DetailMeter | DetailKunjungan | DetailKoreksi | DetailPdil | DetailTusbug;

type Props = PageProps<{
    tab: Tab;
    jenis: Tipe | null;
    jumlah: Record<Tab, number>;
    jenisOptions: { value: Tipe; label: string; menunggu: number }[];
    antrean: Paginated<ItemAntrean>;
    detail: Detail | null;
    alasanOptions: Option[];
}>;

export default function VerifikasiIndex() {
    const { tab, jenis, jumlah, jenisOptions, antrean, detail, alasanOptions, auth } = usePage<Props>().props;

    const muat = (q: { tab?: Tab; jenis?: string | null }) =>
        router.get(route('verifikasi.index'), Object.fromEntries(Object.entries({ tab, jenis, ...q }).filter(([, v]) => v)), { preserveScroll: true });

    return (
        <AppLayout title="Verifikasi">
            <PageHeader
                title="Verifikasi"
                description={`Satu antrean untuk catat meter, penagihan, koreksi rekening, PDIL, dan TUSBUG dari biller ${auth.user.unit?.nama ?? ''}.`}
            />

            <div className="mb-4 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
                <SegmentedTabs
                    label="Antrean verifikasi"
                    value={tab}
                    onChange={(t) => muat({ tab: t })}
                    items={[
                        { value: 'ditandai', label: 'Ditandai sistem', count: jumlah.ditandai },
                        { value: 'normal', label: 'Normal', count: jumlah.normal },
                        { value: 'diperiksa', label: 'Sudah diperiksa', count: jumlah.diperiksa },
                    ]}
                />
                <SegmentedTabs
                    label="Jenis catatan"
                    value={jenis ?? ''}
                    onChange={(j) => muat({ jenis: j || null })}
                    items={[
                        { value: '', label: 'Semua jenis' },
                        ...jenisOptions.map((j) => ({ value: j.value, label: j.label, count: j.menunggu })),
                    ]}
                />
            </div>

            {antrean.data.length === 0 ? (
                <EmptyState icon={ClipboardCheck} title={tab === 'diperiksa' ? 'Belum ada catatan yang diperiksa' : 'Antrean kosong'}>
                    {tab === 'ditandai'
                        ? 'Tidak ada catatan yang ditandai sistem saat ini.'
                        : tab === 'normal'
                          ? 'Semua catatan normal sudah diperiksa.'
                          : 'Catatan yang Anda setujui atau tolak dalam 60 hari terakhir muncul di sini.'}
                </EmptyState>
            ) : (
                <div className="grid gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <DaftarAntrean tab={tab} jenis={jenis} antrean={antrean} dipilih={detail ? `${detail.tipe}-${detail.id}` : null} />
                    {detail ? <PanelDetail key={`${detail.tipe}-${detail.id}`} detail={detail} tab={tab} jenis={jenis} alasanOptions={alasanOptions} /> : null}
                </div>
            )}
        </AppLayout>
    );
}

function DaftarAntrean({ tab, jenis, antrean, dipilih }: { tab: Tab; jenis: Tipe | null; antrean: Paginated<ItemAntrean>; dipilih: string | null }) {
    const pindah = (url: string | null) => url && router.get(url, {}, { preserveState: true });

    return (
        <div className="grid content-start gap-2">
            <ul className="grid gap-2 lg:max-h-[calc(100dvh-260px)] lg:overflow-y-auto lg:pr-1">
                {antrean.data.map((item) => {
                    const kunci = `${item.tipe}-${item.id}`;
                    const aktif = kunci === dipilih;
                    return (
                        <li key={kunci}>
                            <Link
                                href={route('verifikasi.index', { tab, ...(jenis ? { jenis } : {}), tipe: item.tipe, id: item.id, page: antrean.current_page })}
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
                                        <span className="shrink-0 text-xs text-muted-ink">{formatRelatif(item.waktu)}</span>
                                    </span>
                                    <span className="block truncate text-sm font-medium text-ink">{item.nama}</span>
                                    <span className="block truncate text-xs text-muted-ink">
                                        {item.jenis_label}
                                        {item.keterangan ? ` · ${item.keterangan}` : ''}
                                    </span>
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

function PanelDetail({ detail: d, tab, jenis, alasanOptions }: { detail: Detail; tab: Tab; jenis: Tipe | null; alasanOptions: Option[] }) {
    const statusChip = d.tipe === 'pencatatan_meter' ? statusCatatan(d.status, d.anomali[0]?.jenis) : statusCatatan(d.status);

    return (
        <section aria-label="Detail catatan" className="overflow-hidden rounded-14 border border-border">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line-row px-5 py-4">
                <div className="min-w-0">
                    <p className="text-xs font-medium text-pln-blue">{d.jenis_label}</p>
                    <h2 className="text-section-title">{d.pelanggan.nama}</h2>
                    <p className="text-[13px] text-muted-ink">
                        <span className="font-mono">{d.pelanggan.idpel}</span> · {d.pelanggan.alamat} · {d.pelanggan.tarif}
                    </p>
                </div>
                <StatusChip {...statusChip} />
            </header>

            <div className="grid gap-5 p-5">
                {d.tipe === 'pencatatan_meter' && <IsiMeter d={d} />}
                {d.tipe === 'kunjungan_penagihan' && <IsiKunjungan d={d} />}
                {d.tipe === 'koreksi_rekening' && <IsiKoreksi d={d} />}
                {d.tipe === 'pdil' && <IsiPdil d={d} />}
                {d.tipe === 'tusbug' && <IsiTusbug d={d} />}

                {d.anomali.map((a, i) => (
                    <Notice key={`${a.jenis}-${i}`} tone="danger" title={`Ditandai sistem: ${a.label.toLowerCase()}`}>
                        {a.keterangan}
                    </Notice>
                ))}

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

            {d.bisaDiputuskan && <Keputusan d={d} tab={tab} jenis={jenis} alasanOptions={alasanOptions} />}
        </section>
    );
}

function IsiMeter({ d }: { d: DetailMeter }) {
    const pasca = d.jenis === 'pascabayar';
    const adaAnomali = d.anomali.length > 0;

    return (
        <>
            <Stepper d={d} waktu={d.dikirim_at} />
            <div className="grid gap-5 xl:grid-cols-2">
                <FotoPanel foto={d.foto} />
                <div className="grid content-start gap-4">
                    {pasca ? (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <AngkaMeter label="Stand bulan lalu" nilai={d.stand_lalu} />
                            <AngkaMeter label="Stand diketik biller" nilai={d.stand_kini} kosong="Tidak terbaca" />
                        </div>
                    ) : (
                        <AngkaMeter label="Sisa kWh diketik biller" nilai={d.sisa_kwh} desimal={2} />
                    )}
                    {pasca && (
                        <p className="text-sm">
                            Pemakaian{' '}
                            <span className={cn('font-mono font-semibold', adaAnomali && 'text-error-ink')}>
                                {d.pemakaian_kwh !== null ? `${formatNumber(d.pemakaian_kwh, 1)} kWh` : '–'}
                            </span>
                            {d.rata_tiga_bulan !== null && <span className="text-muted-ink"> · rata-rata 3 bulan {formatNumber(d.rata_tiga_bulan, 1)} kWh</span>}
                        </p>
                    )}
                    <div>
                        <p className="mb-2 text-[13px] text-muted-ink">{pasca ? 'Pemakaian 4 bulan (kWh)' : 'Sisa kWh 4 bulan'}</p>
                        <UsageBars data={d.bars} anomali={adaAnomali} tinggi={110} />
                    </div>
                </div>
            </div>
            <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                <Lokasi jarak={d.jarak_m} lat={d.latitude} lng={d.longitude} jauh={d.anomali.some((a) => a.jenis === 'lokasi_jauh')} />
                <Info label="Waktu kirim">
                    {formatTanggalWaktu(d.dikirim_at)}
                    {d.tepat_waktu === false && <span className="text-error-ink"> · lewat batas</span>}
                </Info>
                <Info label="Kondisi meter">{d.kondisi}</Info>
                <InfoBiller biller={d.biller} />
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
        </>
    );
}

function IsiKunjungan({ d }: { d: DetailKunjungan }) {
    return (
        <>
            <Stepper d={d} waktu={d.dikunjungi_at} judulAwal="Dikunjungi biller" />
            <div className="grid gap-5 xl:grid-cols-2">
                <FotoPanel foto={d.foto} label="Foto bukti kunjungan" />
                <div className="grid content-start gap-3">
                    <div className="rounded-10 bg-table-head p-4">
                        <p className="text-[13px] text-muted-ink">Hasil kunjungan</p>
                        <p className="text-lg font-semibold">{d.hasil_label}</p>
                        {d.nominal_dibayar !== null && (
                            <p className="font-mono text-sm">
                                Dibayar {rupiah(d.nominal_dibayar)} dari {rupiah(d.invoice.total)}
                            </p>
                        )}
                        {d.tanggal_janji && <p className="text-sm">Janji bayar {formatTanggal(d.tanggal_janji)}</p>}
                        {d.keterangan && <p className="mt-1 text-sm text-muted-ink">“{d.keterangan}”</p>}
                    </div>
                    <p className="text-[13px] text-muted-ink">
                        {d.hasil === 'lunas' ? 'Bila disetujui, rekening langsung ditandai lunas.' : 'Bila disetujui, rekening tetap belum lunas.'}
                    </p>
                </div>
            </div>
            <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                <Info label="Rekening">
                    <Link href={route('penagihan.show', d.invoice.id)} className="font-mono text-pln-blue hover:underline">
                        {d.invoice.nomor}
                    </Link>
                </Info>
                <Info label="Status rekening">
                    {d.invoice.status_label} · jatuh tempo {formatTanggal(d.invoice.jatuh_tempo, 'd MMM yyyy')}
                </Info>
                <Lokasi jarak={d.jarak_m} lat={d.latitude} lng={d.longitude} jauh={d.anomali.some((a) => a.jenis === 'lokasi_jauh')} />
                <Info label="Waktu kunjungan">{formatTanggalWaktu(d.dikunjungi_at)}</Info>
                <InfoBiller biller={d.biller} />
                <Info label="Kunjungan sebelumnya">{d.kunjungan_sebelumnya > 0 ? `${d.kunjungan_sebelumnya} kali` : 'Belum pernah'}</Info>
            </dl>
        </>
    );
}

function IsiKoreksi({ d }: { d: DetailKoreksi }) {
    const selisih = d.tagihan_baru - d.tagihan_lama;
    return (
        <>
            <Stepper d={d} waktu={d.diajukan_at} judulAwal="Diajukan biller" />
            <div className="overflow-hidden rounded-10 border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-table-head text-xs text-muted-ink">
                        <tr>
                            <th className="px-4 py-2 text-left font-medium" />
                            <th className="px-4 py-2 text-right font-medium">Sebelum</th>
                            <th className="px-4 py-2 text-right font-medium">Sesudah</th>
                        </tr>
                    </thead>
                    <tbody className="font-mono">
                        {[
                            ['Stand', formatNumber(d.stand_lama, 1), formatNumber(d.stand_baru, 1)],
                            ['Pemakaian', `${formatNumber(d.kwh_lama, 1)} kWh`, `${formatNumber(d.kwh_baru, 1)} kWh`],
                            ['Tagihan', rupiah(d.tagihan_lama), rupiah(d.tagihan_baru)],
                        ].map(([l, a, b]) => (
                            <tr key={l} className="border-t border-line-row">
                                <td className="px-4 py-2.5 font-sans text-muted-ink">{l}</td>
                                <td className="px-4 py-2.5 text-right">{a}</td>
                                <td className="px-4 py-2.5 text-right font-semibold">{b}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p className={cn('border-t border-line-row px-4 py-2.5 text-[13px]', selisih <= 0 ? 'text-success-ink' : 'text-error-ink')}>
                    Tagihan {selisih <= 0 ? 'turun' : 'naik'} {rupiah(Math.abs(selisih))} · stand lalu {d.stand_lalu !== null ? formatNumber(d.stand_lalu, 1) : '–'}
                </p>
            </div>
            {d.kurangi_akurasi && (
                <Notice tone="waiting" title="Penyebab salah catat biller">
                    Bila disetujui, catatan ini tidak lagi dihitung "tanpa revisi", sehingga akurasi {d.pencatat ?? 'biller pencatat'} periode {d.periode} turun.
                </Notice>
            )}
            <div className="grid gap-5 sm:grid-cols-2">
                <FotoPanel foto={d.foto} label="Foto bukti koreksi" />
                <FotoPanel foto={d.foto_asli} label="Foto saat dicatat" />
            </div>
            <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                <Info label="Penyebab">{d.penyebab_label}</Info>
                <Info label="Periode">{d.periode}</Info>
                <InfoBiller biller={d.biller} label="Diajukan oleh" />
                <Info label="Rekening">
                    {d.invoice ? (
                        <Link href={route('penagihan.show', d.invoice.id)} className="font-mono text-pln-blue hover:underline">
                            {d.invoice.nomor}
                        </Link>
                    ) : (
                        '–'
                    )}
                </Info>
                <div className="sm:col-span-2">
                    <Info label="Penjelasan biller">{d.alasan}</Info>
                </div>
            </dl>
        </>
    );
}

function IsiPdil({ d }: { d: DetailPdil }) {
    return (
        <>
            <Stepper d={d} waktu={d.diajukan_at} judulAwal="Diajukan biller" />
            <Notice tone="info" title={d.jenis_pdil_label}>
                {d.jenis === 'pelanggan_baru'
                    ? `Bila disetujui, pelanggan baru dibuat di rute ${d.rute} dengan ID pelanggan otomatis dan masuk urutan terakhir.`
                    : 'Bila disetujui, data pelanggan diperbarui sesuai kolom yang disorot.'}
            </Notice>
            <TabelPerbandingan baris={d.perbandingan} />
            <div className="grid gap-5 sm:grid-cols-2">
                <FotoPanel foto={d.foto} label="Foto rumah" />
                <FotoPanel foto={d.foto_meter} label="Foto meter" />
            </div>
            <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                <Info label="Rute">{d.rute}</Info>
                <InfoBiller biller={d.biller} label="Diajukan oleh" />
                {d.pelanggan.latitude !== null && d.pelanggan.longitude !== null && (
                    <Info label="Titik lokasi">
                        <a href={urlPeta(d.pelanggan.latitude, d.pelanggan.longitude)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-pln-blue hover:underline">
                            <MapPin className="size-3.5" />
                            Lihat di peta
                        </a>
                    </Info>
                )}
                {d.keterangan && <Info label="Keterangan biller">{d.keterangan}</Info>}
            </dl>
        </>
    );
}

function IsiTusbug({ d }: { d: DetailTusbug }) {
    return (
        <>
            <Stepper d={d} waktu={d.dilaksanakan_at} judulAwal="Dilaksanakan biller" />
            <div className="rounded-10 bg-table-head p-4">
                <p className="text-[13px] text-muted-ink">Hasil pelaksanaan</p>
                <p className="text-lg font-semibold">{d.hasil_label ?? '–'}</p>
                {d.keterangan && <p className="mt-1 text-sm text-muted-ink">“{d.keterangan}”</p>}
                <p className="mt-2 text-[13px]">{d.efek}</p>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <FotoPanel foto={d.foto} label="Foto sebelum" />
                <FotoPanel foto={d.foto_sesudah} label="Foto sesudah" />
            </div>
            <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                <Info label="Nomor surat">
                    <span className="font-mono">{d.nomor_surat}</span>
                </Info>
                <Info label="Tunggakan">
                    {d.jumlah_lembar} lembar · {rupiah(d.total_tunggakan)}
                </Info>
                <Lokasi jarak={d.jarak_m} lat={d.latitude} lng={d.longitude} jauh={d.anomali.some((a) => a.jenis === 'lokasi_jauh')} />
                <Info label="Rencana / pelaksanaan">
                    {formatTanggal(d.tanggal_rencana, 'd MMM')} / {formatTanggalWaktu(d.dilaksanakan_at)}
                </Info>
                <InfoBiller biller={d.biller} />
                <Info label="Ditugaskan oleh">{d.penugas}</Info>
            </dl>
        </>
    );
}

function Keputusan({ d, tab, jenis, alasanOptions }: { d: Detail; tab: Tab; jenis: Tipe | null; alasanOptions: Option[] }) {
    const [menyetujui, setMenyetujui] = useState(false);
    const tolak = useForm({ alasan: '', catatan: '', tab, jenis: jenis ?? '' });

    const setujui = () =>
        router.post(
            route('verifikasi.setujui', { tipe: d.tipe, id: d.id }),
            { tab, jenis: jenis ?? '' },
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

    const proses = menyetujui || tolak.processing;

    return (
        <footer className="grid gap-4 border-t border-line-row bg-table-head/60 px-5 py-4">
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-[13px] font-medium">Alasan bila ditolak</legend>
                <div className="flex flex-wrap gap-2">
                    {alasanOptions.map((o) => (
                        <label
                            key={o.value}
                            className={cn(
                                'inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-9 border px-3 text-[13px] has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pln-blue/25',
                                tolak.data.alasan === o.value ? 'border-pln-red bg-error-tint text-error-deep' : 'border-border bg-white text-ink hover:bg-table-head',
                            )}
                        >
                            <input type="radio" name="alasan" value={o.value} checked={tolak.data.alasan === o.value} onChange={() => tolak.setData('alasan', o.value)} className="sr-only" />
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
    );
}

function Stepper({ d, waktu, judulAwal = 'Dicatat biller' }: { d: Detail; waktu: string | null; judulAwal?: string }) {
    const adaAnomali = d.anomali.length > 0;
    const terakhir = d.riwayatVerifikasi[0];
    const langkah: { judul: string; ket: string; tone: 'biru' | 'merah' | 'kuning' | 'hijau'; ikon: ReactNode }[] = [
        { judul: judulAwal, ket: formatTanggalWaktu(waktu), tone: 'biru', ikon: <Check className="size-3.5" strokeWidth={3} /> },
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
    const warna = { biru: 'bg-pln-blue text-white', merah: 'bg-pln-red text-white', kuning: 'bg-pln-yellow text-ink', hijau: 'bg-success-ink text-white' };

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

function FotoPanel({ foto, label = 'Foto meter dari biller' }: { foto: FotoInfo | null; label?: string }) {
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
                <img src={foto.url} alt={label} className="h-full w-full object-contain transition-transform" style={{ transform: `rotate(${putar}deg)` }} />
                <div className="absolute right-2 bottom-2 flex gap-1.5">
                    <button type="button" onClick={() => setPutar((p) => (p + 90) % 360)} className="inline-flex size-10 items-center justify-center rounded-9 bg-white/90 text-ink hover:bg-white">
                        <RotateCw className="size-4" />
                        <span className="sr-only">Putar foto</span>
                    </button>
                    <button type="button" onClick={() => setBesar(true)} className="inline-flex size-10 items-center justify-center rounded-9 bg-white/90 text-ink hover:bg-white">
                        <Maximize2 className="size-4" />
                        <span className="sr-only">Perbesar foto</span>
                    </button>
                </div>
            </div>
            <p className="text-xs text-muted-ink">
                {label} · diambil {formatTanggalWaktu(foto.diambil_at)} · {formatNumber(foto.ukuran_kb)} KB
            </p>
            <Dialog open={besar} onOpenChange={setBesar}>
                <DialogContent className="max-w-5xl bg-ink p-2">
                    <DialogTitle className="sr-only">{label}</DialogTitle>
                    <DialogDescription className="sr-only">{label} ukuran penuh</DialogDescription>
                    <img src={foto.url} alt={`${label} ukuran penuh`} className="max-h-[85dvh] w-full object-contain" style={{ transform: `rotate(${putar}deg)` }} />
                </DialogContent>
            </Dialog>
        </div>
    );
}

function AngkaMeter({ label, nilai, desimal = 1, kosong = '–' }: { label: string; nilai: number | null; desimal?: number; kosong?: string }) {
    return (
        <div>
            <p className="mb-1.5 text-[13px] text-muted-ink">{label}</p>
            {nilai !== null ? <MeterDigits value={nilai} decimals={desimal} size="sm" /> : <span className="text-sm">{kosong}</span>}
        </div>
    );
}

function Lokasi({ jarak, lat, lng, jauh }: { jarak: number | null; lat: number | null; lng: number | null; jauh: boolean }) {
    return (
        <Info label="Lokasi saat mencatat">
            {jarak !== null ? <span className={cn(jauh && 'text-error-ink')}>{formatNumber(jarak)} m dari titik pelanggan</span> : <span className="text-error-ink">Tanpa GPS</span>}
            {lat !== null && lng !== null && (
                <a href={urlPeta(lat, lng)} target="_blank" rel="noreferrer" className="ml-2 inline-flex items-center gap-1 text-pln-blue hover:underline">
                    <MapPin className="size-3.5" />
                    Peta
                </a>
            )}
        </Info>
    );
}

function InfoBiller({ biller, label = 'Biller' }: { biller: { name: string; nip: string }; label?: string }) {
    return (
        <Info label={label}>
            {biller.name} <span className="font-mono text-xs text-muted-ink">{biller.nip}</span>
        </Info>
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
