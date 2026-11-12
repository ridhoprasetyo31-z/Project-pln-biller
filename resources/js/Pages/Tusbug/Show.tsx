import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Navigation, Send, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import FormField, { InputError } from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import OptionCards from '@/Components/shared/OptionCards';
import PageHeader from '@/Components/shared/PageHeader';
import PhotoCapture, { type FotoTerpilih } from '@/Components/shared/PhotoCapture';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useGeolocation } from '@/hooks/useGeolocation';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { urlRute } from '@/lib/geo';
import { rupiah, STATUS_INVOICE, type StatusInvoice } from '@/lib/tagihan';
import { statusTusbug } from '@/lib/tusbug';
import type { Option, PageProps } from '@/types';
import type { AnomaliItem, FotoInfo, VerifikasiInfo } from '@/types/catat';

type Props = PageProps<{
    tusbug: {
        id: number;
        nomor_surat: string;
        status: string;
        biller: string;
        penugas: string;
        tanggal_rencana: string;
        jumlah_lembar: number;
        total_tunggakan: number;
        hasil: string | null;
        hasil_label: string | null;
        keterangan: string | null;
        jarak_m: number | null;
        dilaksanakan_at: string | null;
        jumlah_revisi: number;
        invoice: { id: number; nomor: string; periode: string; total: number; status: StatusInvoice; status_label: string }[];
        anomali: AnomaliItem[];
        foto_sebelum: FotoInfo | null;
        foto_sesudah: FotoInfo | null;
        riwayat: VerifikasiInfo[];
    };
    pelanggan: { idpel: string; nama: string; alamat: string; tarif: string; nomor_meter: string; letak_meter: string | null; rute: string | null; status: string; status_label: string; latitude: number | null; longitude: number | null };
    hasilOptions: Option[];
    ambangJarak: number;
    can: { laksanakan: boolean; batal: boolean };
}>;

export default function TusbugShow() {
    const { tusbug: t, pelanggan: p, hasilOptions, ambangJarak, can } = usePage<Props>().props;
    const batal = useConfirmAction<number>();
    const ditolak = t.riwayat[0]?.keputusan === 'ditolak' ? t.riwayat[0] : null;

    return (
        <AppLayout title={t.nomor_surat} breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'TUSBUG', href: route('tusbug.index') }, { label: t.nomor_surat }]}>
            <PageHeader
                title={p.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span className="font-mono">{t.nomor_surat}</span>·<span>rencana {formatTanggal(t.tanggal_rencana)}</span>
                        <StatusChip {...statusTusbug(t.status, t.anomali[0]?.jenis)} />
                    </span>
                }
                actions={
                    can.batal && (
                        <Button variant="reject" onClick={() => batal.ask(t.id)}>
                            <Trash2 />
                            Batalkan tugas
                        </Button>
                    )
                }
            />

            {t.status === 'revisi' && ditolak && (
                <Notice tone="danger" className="mb-5" title={`Dikembalikan koordinator: ${ditolak.alasan_label ?? ''}`}>
                    {ditolak.catatan && <p>{ditolak.catatan}</p>}
                    <p className="text-xs opacity-80">
                        {ditolak.oleh} · {formatTanggalWaktu(ditolak.waktu)}
                    </p>
                </Notice>
            )}
            {t.status === 'disetujui' && (
                <Notice tone="success" className="mb-5" title={`Selesai: ${t.hasil_label}`}>
                    Status pelanggan sekarang {p.status_label.toLowerCase()}.
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid content-start gap-6">
                    {can.laksanakan ? (
                        <FormLaksanakan t={t} p={p} hasilOptions={hasilOptions} ambangJarak={ambangJarak} />
                    ) : (
                        t.hasil && (
                            <section className="grid gap-4 rounded-14 border border-border p-5">
                                <h2 className="text-section-title">Hasil pelaksanaan</h2>
                                <dl className="grid gap-x-6 text-sm sm:grid-cols-2">
                                    <Baris label="Hasil" nilai={t.hasil_label} />
                                    <Baris label="Dilaksanakan" nilai={formatTanggalWaktu(t.dilaksanakan_at)} />
                                    <Baris label="Lokasi" nilai={t.jarak_m !== null ? `${formatNumber(t.jarak_m)} m dari titik pelanggan` : 'Tanpa GPS'} />
                                    <Baris label="Biller" nilai={t.biller} />
                                </dl>
                                {t.keterangan && <p className="text-sm text-muted-ink">“{t.keterangan}”</p>}
                                {t.anomali.map((a, i) => (
                                    <Notice key={i} tone="danger" title={`Ditandai sistem: ${a.label.toLowerCase()}`}>
                                        {a.keterangan}
                                    </Notice>
                                ))}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Foto judul="Sebelum" foto={t.foto_sebelum} />
                                    <Foto judul="Sesudah" foto={t.foto_sesudah} />
                                </div>
                            </section>
                        )
                    )}

                    <section>
                        <h2 className="text-section-title">Rekening menunggak</h2>
                        <p className="mb-3 text-[13px] text-muted-ink">
                            {t.jumlah_lembar} lembar, total {rupiah(t.total_tunggakan)} saat ditugaskan oleh {t.penugas}.
                        </p>
                        <ul className="divide-y divide-line-row rounded-14 border border-border">
                            {t.invoice.map((i) => (
                                <li key={i.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <span>
                                        <span className="font-mono text-[13px]">{i.nomor}</span>
                                        <span className="block text-xs text-muted-ink">Periode {i.periode}</span>
                                    </span>
                                    <span className="flex items-center gap-3">
                                        <span className="font-mono">{rupiah(i.total)}</span>
                                        <StatusChip {...STATUS_INVOICE[i.status]} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    {t.riwayat.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-section-title">Riwayat verifikasi</h2>
                            <ul className="grid gap-1.5 text-[13px] text-muted-ink">
                                {t.riwayat.map((v, i) => (
                                    <li key={i}>
                                        <span className={v.keputusan === 'disetujui' ? 'text-success-ink' : 'text-error-ink'}>
                                            {v.keputusan === 'disetujui' ? 'Disetujui' : `Ditolak: ${v.alasan_label}`}
                                        </span>
                                        {v.catatan ? ` “${v.catatan}”` : ''} · {v.oleh} · {formatTanggalWaktu(v.waktu)}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>

                <aside className="grid content-start gap-4">
                    <div className="rounded-14 border border-border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-section-title">Pelanggan</h2>
                            {p.latitude !== null && p.longitude !== null && (
                                <a href={urlRute(p.latitude, p.longitude)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-[13px] font-medium text-pln-blue hover:underline">
                                    <Navigation className="size-3.5" />
                                    Petunjuk arah
                                </a>
                            )}
                        </div>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="ID pelanggan" nilai={<span className="font-mono">{p.idpel}</span>} />
                            <Baris label="Nomor meter" nilai={<span className="font-mono">{p.nomor_meter}</span>} />
                            <Baris label="Tarif" nilai={p.tarif} />
                            <Baris label="Alamat" nilai={p.alamat} />
                            <Baris label="Letak meter" nilai={p.letak_meter ?? '–'} />
                            <Baris label="Status" nilai={p.status_label} />
                        </dl>
                    </div>
                </aside>
            </div>

            <ConfirmDialog
                open={batal.open}
                onOpenChange={(v) => !v && batal.close()}
                title="Batalkan tugas TUSBUG?"
                description="Tugas yang belum dilaksanakan akan dihapus dan pelanggan kembali muncul di daftar saran."
                confirmLabel="Batalkan tugas"
                tone="destructive"
                processing={batal.processing}
                onConfirm={() => batal.run('delete', route('tusbug.destroy', t.id))}
            />
        </AppLayout>
    );
}

function FormLaksanakan({ t, p, hasilOptions, ambangJarak }: { t: Props['tusbug']; p: Props['pelanggan']; hasilOptions: Option[]; ambangJarak: number }) {
    const geo = useGeolocation();
    const { data, setData, post, processing, errors, transform } = useForm({
        hasil: t.hasil ?? 'diputus',
        keterangan: t.keterangan ?? '',
        foto_sebelum: null as FotoTerpilih | null,
        foto_sesudah: null as FotoTerpilih | null,
    });
    const err = errors as Record<string, string>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const lat = data.foto_sesudah?.lat ?? data.foto_sebelum?.lat ?? geo.posisi?.lat ?? '';
        const lng = data.foto_sesudah?.lng ?? data.foto_sebelum?.lng ?? geo.posisi?.lng ?? '';
        transform((d) => ({
            hasil: d.hasil,
            keterangan: d.keterangan,
            latitude: lat,
            longitude: lng,
            foto_sebelum: d.foto_sebelum?.file ?? null,
            foto_sesudah: d.foto_sesudah?.file ?? null,
            foto_sebelum_diambil_at: d.foto_sebelum?.diambil_at ?? '',
            foto_sesudah_diambil_at: d.foto_sesudah?.diambil_at ?? '',
        }));
        post(route('tusbug.laksanakan', t.id), {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => toast.error(e.tusbug ?? 'Periksa kembali isian yang ditandai merah.'),
        });
    };

    const efek: Record<string, string> = {
        diputus: 'Setelah disetujui koordinator, pelanggan berstatus putus sementara sampai tunggakan lunas.',
        bayar_di_tempat: 'Setelah disetujui koordinator, rekening tunggakan ditandai lunas.',
        ditunda: 'Pemutusan ditunda. Tulis alasannya, misalnya pelanggan berjanji melunasi.',
        tidak_dapat_diakses: 'Meter tidak bisa dijangkau. Tulis kendalanya.',
    };

    return (
        <form onSubmit={submit} noValidate className="grid gap-6">
            <section className="grid gap-4 rounded-14 border border-border p-5">
                <OptionCards name="hasil" label="Hasil pelaksanaan" value={data.hasil} onChange={(v) => setData('hasil', v)} options={hasilOptions.map((o) => ({ value: o.value, label: o.label }))} />
                <p className="-mt-1 text-[13px] text-muted-ink">{efek[data.hasil]}</p>
                <InputError message={err.hasil} />
                <FormField id="keterangan" label="Keterangan" required={['ditunda', 'tidak_dapat_diakses'].includes(data.hasil)} error={err.keterangan}>
                    <Textarea id="keterangan" rows={3} maxLength={500} value={data.keterangan} onChange={(e) => setData('keterangan', e.target.value)} />
                </FormField>
            </section>
            <section className="grid gap-6 rounded-14 border border-border p-5 md:grid-cols-2">
                <PhotoCapture
                    id="foto_sebelum"
                    label="Foto sebelum"
                    nilai={data.foto_sebelum}
                    fotoTersimpan={t.foto_sebelum ? { url: t.foto_sebelum.url, diambil_at: t.foto_sebelum.diambil_at } : null}
                    onChange={(f) => setData('foto_sebelum', f)}
                    posisi={geo.posisi}
                    statusLokasi={geo.status}
                    onUlangiLokasi={geo.ulangi}
                    titikPelanggan={{ lat: p.latitude, lng: p.longitude }}
                    ambangJarak={ambangJarak}
                    error={err.foto_sebelum}
                    petunjuk="Belum ada foto. Foto meter dan MCB sebelum tindakan."
                />
                <PhotoCapture
                    id="foto_sesudah"
                    label="Foto sesudah"
                    nilai={data.foto_sesudah}
                    fotoTersimpan={t.foto_sesudah ? { url: t.foto_sesudah.url, diambil_at: t.foto_sesudah.diambil_at } : null}
                    onChange={(f) => setData('foto_sesudah', f)}
                    posisi={geo.posisi}
                    statusLokasi={geo.status}
                    onUlangiLokasi={geo.ulangi}
                    titikPelanggan={{ lat: p.latitude, lng: p.longitude }}
                    ambangJarak={ambangJarak}
                    error={err.foto_sesudah}
                    petunjuk="Belum ada foto. Foto meter sesudah tindakan (segel, MCB dicabut, atau bukti bayar)."
                />
            </section>
            <div className="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-14 border border-border bg-white/95 p-3 shadow-lg shadow-ink/5 backdrop-blur sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" asChild>
                    <Link href={route('tusbug.index')}>Batal</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                    Kirim hasil
                </Button>
            </div>
        </form>
    );
}

function Foto({ judul, foto }: { judul: string; foto: FotoInfo | null }) {
    if (!foto) return <div className="flex aspect-[4/3] items-center justify-center rounded-10 bg-table-head text-sm text-muted-ink">Tanpa foto {judul.toLowerCase()}</div>;
    return (
        <div>
            <a href={foto.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-10 bg-ink">
                <img src={foto.thumb} alt={`Foto ${judul.toLowerCase()}`} className="aspect-[4/3] w-full object-contain" />
            </a>
            <p className="mt-1 text-xs text-muted-ink">
                {judul} · {formatTanggalWaktu(foto.diambil_at)}
            </p>
        </div>
    );
}

function Baris({ label, nilai }: { label: string; nilai: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 border-b border-line-row py-2.5 last:border-0">
            <dt className="shrink-0 text-muted-ink">{label}</dt>
            <dd className="min-w-0 text-right break-words">{nilai}</dd>
        </div>
    );
}
