import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Navigation, Send } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import FormField, { InputError } from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import OptionCards from '@/Components/shared/OptionCards';
import PageHeader from '@/Components/shared/PageHeader';
import PhotoCapture, { type FotoTerpilih } from '@/Components/shared/PhotoCapture';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { useGeolocation } from '@/hooks/useGeolocation';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { urlRute } from '@/lib/geo';
import { rupiah, STATUS_INVOICE, type StatusInvoice } from '@/lib/tagihan';
import type { Option, PageProps } from '@/types';
import type { FotoInfo, VerifikasiInfo } from '@/types/catat';

type Hasil = 'lunas' | 'janji_bayar' | 'tidak_bertemu' | 'menolak';

type Props = PageProps<{
    invoice: { id: number; nomor: string; periode: string; total: number; jatuh_tempo: string; status: StatusInvoice; status_label: string };
    pelanggan: { idpel: string; nama: string; alamat: string; tarif: string; latitude: number | null; longitude: number | null };
    kunjungan: {
        id: number;
        hasil: Hasil;
        tanggal_janji: string | null;
        nominal_dibayar: number | null;
        keterangan: string | null;
        foto: FotoInfo | null;
        verifikasi: VerifikasiInfo | null;
        jumlah_revisi: number;
    } | null;
    hasilOptions: Option[];
    ambangJarak: number;
}>;

const PETUNJUK: Record<Hasil, string> = {
    lunas: 'Pelanggan membayar di tempat. Foto kuitansi atau bukti bayar.',
    janji_bayar: 'Pelanggan berjanji membayar pada tanggal tertentu.',
    tidak_bertemu: 'Pelanggan tidak ada di rumah. Foto rumah sebagai bukti kunjungan.',
    menolak: 'Pelanggan menolak membayar. Tulis alasannya.',
};

export default function KunjunganForm() {
    const { invoice, pelanggan, kunjungan, hasilOptions, ambangJarak } = usePage<Props>().props;
    const geo = useGeolocation();
    const ubah = kunjungan !== null;

    const { data, setData, post, processing, errors, transform } = useForm<{
        hasil: Hasil;
        tanggal_janji: string;
        nominal_dibayar: string;
        keterangan: string;
        foto: FotoTerpilih | null;
    }>({
        hasil: kunjungan?.hasil ?? 'lunas',
        tanggal_janji: kunjungan?.tanggal_janji ?? '',
        nominal_dibayar: String(Math.round(kunjungan?.nominal_dibayar ?? invoice.total)),
        keterangan: kunjungan?.keterangan ?? '',
        foto: null,
    });
    const err = errors as Record<string, string>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const lat = data.foto?.lat ?? geo.posisi?.lat ?? '';
        const lng = data.foto?.lng ?? geo.posisi?.lng ?? '';
        transform((d) => ({
            ...(ubah ? { _method: 'put' } : {}),
            hasil: d.hasil,
            tanggal_janji: d.hasil === 'janji_bayar' ? d.tanggal_janji : '',
            nominal_dibayar: d.hasil === 'lunas' ? d.nominal_dibayar : '',
            keterangan: d.keterangan,
            latitude: lat,
            longitude: lng,
            foto: d.foto?.file ?? null,
            foto_diambil_at: d.foto?.diambil_at ?? '',
        }));
        post(ubah ? route('kunjungan.update', kunjungan.id) : route('kunjungan.store', invoice.id), {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => toast.error(e.kunjungan ?? 'Periksa kembali isian yang ditandai merah.'),
        });
    };

    return (
        <AppLayout
            title="Kunjungan penagihan"
            breadcrumbs={[
                { label: 'Layanan pelanggan' },
                { label: 'Penagihan', href: route('penagihan.index') },
                { label: invoice.nomor, href: route('penagihan.show', invoice.id) },
                { label: ubah ? 'Perbaiki kunjungan' : 'Kunjungan' },
            ]}
        >
            <PageHeader
                title={ubah ? 'Perbaiki kunjungan penagihan' : 'Kunjungan penagihan'}
                description={`${pelanggan.nama} · ${invoice.nomor}`}
            />

            {kunjungan?.verifikasi && kunjungan.verifikasi.keputusan === 'ditolak' && (
                <Notice tone="danger" className="mb-5" title={`Dikembalikan koordinator: ${kunjungan.verifikasi.alasan_label ?? ''}`}>
                    {kunjungan.verifikasi.catatan && <p>{kunjungan.verifikasi.catatan}</p>}
                    <p className="text-xs opacity-80">
                        {kunjungan.verifikasi.oleh} · {formatTanggalWaktu(kunjungan.verifikasi.waktu)}
                    </p>
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <form onSubmit={submit} noValidate className="grid content-start gap-6">
                    <section className="grid gap-5 rounded-14 border border-border p-5">
                        <OptionCards
                            name="hasil"
                            label="Hasil kunjungan"
                            value={data.hasil}
                            onChange={(v) => setData('hasil', v as Hasil)}
                            options={hasilOptions.map((o) => ({ value: o.value, label: o.label }))}
                        />
                        <p className="-mt-2 text-[13px] text-muted-ink">{PETUNJUK[data.hasil]}</p>
                        <InputError message={err.hasil} />

                        {data.hasil === 'lunas' && (
                            <FormField id="nominal_dibayar" label="Nominal dibayar" required error={err.nominal_dibayar} hint={`Tagihan ${rupiah(invoice.total)}.`}>
                                <div className="relative sm:w-64">
                                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-ink">Rp</span>
                                    <Input
                                        id="nominal_dibayar"
                                        inputMode="numeric"
                                        className="pl-9 font-mono"
                                        value={data.nominal_dibayar}
                                        onChange={(e) => setData('nominal_dibayar', e.target.value.replace(/\D/g, ''))}
                                        aria-invalid={!!err.nominal_dibayar}
                                    />
                                </div>
                            </FormField>
                        )}
                        {data.hasil === 'janji_bayar' && (
                            <FormField id="tanggal_janji" label="Tanggal janji bayar" required error={err.tanggal_janji}>
                                <Input
                                    id="tanggal_janji"
                                    type="date"
                                    className="sm:w-52"
                                    min={new Date().toISOString().slice(0, 10)}
                                    value={data.tanggal_janji}
                                    onChange={(e) => setData('tanggal_janji', e.target.value)}
                                    aria-invalid={!!err.tanggal_janji}
                                />
                            </FormField>
                        )}
                        <FormField id="keterangan" label="Keterangan" required={data.hasil === 'menolak'} error={err.keterangan}>
                            <Textarea
                                id="keterangan"
                                rows={3}
                                maxLength={500}
                                placeholder="Catatan untuk koordinator"
                                value={data.keterangan}
                                onChange={(e) => setData('keterangan', e.target.value)}
                                aria-invalid={!!err.keterangan}
                            />
                        </FormField>
                    </section>

                    <section className="rounded-14 border border-border p-5">
                        <PhotoCapture
                            id="foto"
                            label="Foto bukti kunjungan"
                            nilai={data.foto}
                            fotoTersimpan={kunjungan?.foto ? { url: kunjungan.foto.url, diambil_at: kunjungan.foto.diambil_at } : null}
                            onChange={(foto) => setData('foto', foto)}
                            posisi={geo.posisi}
                            statusLokasi={geo.status}
                            onUlangiLokasi={geo.ulangi}
                            titikPelanggan={{ lat: pelanggan.latitude, lng: pelanggan.longitude }}
                            ambangJarak={ambangJarak}
                            error={err.foto}
                            petunjuk="Belum ada foto. Foto kuitansi, rumah pelanggan, atau meter sebagai bukti kunjungan."
                        />
                    </section>

                    <div className="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-14 border border-border bg-white/95 p-3 shadow-lg shadow-ink/5 backdrop-blur sm:flex-row sm:justify-end">
                        <Button type="button" variant="secondary" asChild>
                            <Link href={route('penagihan.show', invoice.id)}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                            {ubah ? 'Kirim perbaikan' : 'Kirim kunjungan'}
                        </Button>
                    </div>
                </form>

                <aside className="grid content-start gap-4">
                    <div className="rounded-14 border border-border p-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-section-title">Rekening</h2>
                            <StatusChip {...STATUS_INVOICE[invoice.status]} />
                        </div>
                        <p className="mt-3 font-mono text-2xl font-semibold">{rupiah(invoice.total)}</p>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="Nomor" nilai={<span className="font-mono text-[13px]">{invoice.nomor}</span>} />
                            <Baris label="Periode" nilai={invoice.periode} />
                            <Baris label="Jatuh tempo" nilai={formatTanggal(invoice.jatuh_tempo, 'd MMM yyyy')} />
                        </dl>
                    </div>
                    <div className="rounded-14 border border-border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-section-title">Pelanggan</h2>
                            {pelanggan.latitude !== null && pelanggan.longitude !== null && (
                                <a href={urlRute(pelanggan.latitude, pelanggan.longitude)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-[13px] font-medium text-pln-blue hover:underline">
                                    <Navigation className="size-3.5" />
                                    Petunjuk arah
                                </a>
                            )}
                        </div>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="Nama" nilai={pelanggan.nama} />
                            <Baris label="ID pelanggan" nilai={<span className="font-mono">{pelanggan.idpel}</span>} />
                            <Baris label="Tarif" nilai={pelanggan.tarif} />
                            <Baris label="Alamat" nilai={pelanggan.alamat} />
                        </dl>
                        <p className="mt-3 text-xs text-muted-ink">
                            Lokasi lebih dari {formatNumber(ambangJarak)} m dari titik pelanggan akan ditandai untuk koordinator.
                        </p>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}

function Baris({ label, nilai }: { label: string; nilai: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-2.5">
            <dt className="shrink-0 text-muted-ink">{label}</dt>
            <dd className="min-w-0 text-right break-words">{nilai}</dd>
        </div>
    );
}
