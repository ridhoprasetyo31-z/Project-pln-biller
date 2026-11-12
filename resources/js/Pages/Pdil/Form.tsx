import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, LocateFixed, Send } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import PhotoCapture, { type FotoTerpilih } from '@/Components/shared/PhotoCapture';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';
import { useGeolocation } from '@/hooks/useGeolocation';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { jarakMeter } from '@/lib/geo';
import { cn } from '@/lib/utils';
import type { Option, PageProps } from '@/types';
import type { FotoInfo, VerifikasiInfo } from '@/types/catat';

interface DataPelanggan {
    nama: string;
    alamat: string;
    kelurahan: string;
    jenis_pelanggan: string;
    golongan_tarif: string;
    daya_va: number;
    nomor_meter: string | null;
    letak_meter: string | null;
    latitude: number | null;
    longitude: number | null;
}

type Props = PageProps<{
    jenis: 'pelanggan_baru' | 'perubahan_data';
    pelanggan: { id: number; idpel: string; rute_id: number; data: DataPelanggan } | null;
    pdil: { id: number; rute_id: number; keterangan: string | null; foto_rumah: FotoInfo | null; foto_meter: FotoInfo | null; verifikasi: VerifikasiInfo | null } | null;
    nilai: DataPelanggan | null;
    ruteOptions: (Option<number> & { kelurahan: string })[];
    golonganOptions: string[];
    jenisPelangganOptions: Option[];
    ambangJarak: number;
}>;

const LABEL: Record<keyof DataPelanggan, string> = {
    nama: 'Nama',
    alamat: 'Alamat',
    kelurahan: 'Kelurahan',
    jenis_pelanggan: 'Jenis',
    golongan_tarif: 'Golongan',
    daya_va: 'Daya',
    nomor_meter: 'Nomor meter',
    letak_meter: 'Letak meter',
    latitude: 'Latitude',
    longitude: 'Longitude',
};

const teks = (v: unknown) => (v === null || v === undefined ? '' : String(v));

export default function PdilForm() {
    const { jenis, pelanggan, pdil, nilai, ruteOptions, golonganOptions, jenisPelangganOptions, ambangJarak } = usePage<Props>().props;
    const geo = useGeolocation();
    const ubah = pdil !== null;
    const perubahan = jenis === 'perubahan_data';
    const lama = pelanggan?.data ?? null;

    const { data, setData, post, processing, errors, transform } = useForm({
        rute_id: String(pdil?.rute_id ?? pelanggan?.rute_id ?? ruteOptions[0]?.value ?? ''),
        nama: teks(nilai?.nama),
        alamat: teks(nilai?.alamat),
        kelurahan: teks(nilai?.kelurahan) || (ruteOptions[0]?.kelurahan ?? ''),
        jenis_pelanggan: nilai?.jenis_pelanggan ?? 'pascabayar',
        golongan_tarif: nilai?.golongan_tarif ?? 'R1',
        daya_va: teks(nilai?.daya_va ?? 1300),
        nomor_meter: teks(nilai?.nomor_meter),
        letak_meter: teks(nilai?.letak_meter),
        latitude: teks(nilai?.latitude),
        longitude: teks(nilai?.longitude),
        keterangan: pdil?.keterangan ?? '',
        foto_rumah: null as FotoTerpilih | null,
        foto_meter: null as FotoTerpilih | null,
    });
    const err = errors as Record<string, string>;

    const berubah = (k: keyof DataPelanggan) => {
        if (!lama) return false;
        const a = teks(lama[k]);
        const b = teks(data[k as keyof typeof data] as string);
        if (k === 'latitude' || k === 'longitude' || k === 'daya_va') return a === '' ? b !== '' : Math.abs(Number(a) - Number(b)) > 0.0000005;
        return a !== b;
    };
    const daftarBerubah = (Object.keys(LABEL) as (keyof DataPelanggan)[]).filter(berubah);

    const geser =
        lama?.latitude != null && lama.longitude != null && data.latitude && data.longitude
            ? jarakMeter(Number(data.latitude), Number(data.longitude), lama.latitude, lama.longitude)
            : null;

    const pakaiLokasi = () => {
        if (!geo.posisi) {
            toast.error('Lokasi GPS belum tersedia. Pastikan izin lokasi aktif.');
            return;
        }
        setData((d) => ({ ...d, latitude: geo.posisi!.lat.toFixed(7), longitude: geo.posisi!.lng.toFixed(7) }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform((d) => ({
            ...(ubah ? { _method: 'put' } : { jenis, ...(perubahan ? { pelanggan_id: pelanggan!.id } : { rute_id: d.rute_id }) }),
            nama: d.nama,
            alamat: d.alamat,
            kelurahan: d.kelurahan,
            jenis_pelanggan: d.jenis_pelanggan,
            golongan_tarif: d.golongan_tarif,
            daya_va: d.daya_va,
            nomor_meter: d.nomor_meter,
            letak_meter: d.letak_meter,
            latitude: d.latitude,
            longitude: d.longitude,
            keterangan: d.keterangan,
            foto_rumah: d.foto_rumah?.file ?? null,
            foto_meter: d.foto_meter?.file ?? null,
            foto_rumah_diambil_at: d.foto_rumah?.diambil_at ?? '',
            foto_meter_diambil_at: d.foto_meter?.diambil_at ?? '',
        }));
        post(ubah ? route('pdil.update', pdil.id) : route('pdil.store'), {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => toast.error(e.pdil ?? 'Periksa kembali isian yang ditandai merah.'),
        });
    };

    const field = (k: keyof DataPelanggan, label: string, input: ReactNode, required = true) => (
        <FormField
            id={k}
            label={label}
            required={required}
            error={err[k]}
            hint={berubah(k) && lama ? `Sebelumnya: ${teks(lama[k]) || 'kosong'}` : undefined}
            className={cn(berubah(k) && 'rounded-10 bg-pln-yellow-light/60 p-2 -m-2')}
        >
            {input}
        </FormField>
    );

    return (
        <AppLayout
            title="PDIL"
            breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'PDIL', href: route('pdil.index') }, { label: perubahan ? 'Perubahan data' : 'Pelanggan baru' }]}
        >
            <PageHeader
                title={perubahan ? 'Perubahan data pelanggan' : 'Pelanggan baru'}
                description={perubahan ? `${lama?.nama} · ${pelanggan?.idpel}` : 'Data pelanggan baru di rute Anda. ID pelanggan dibuat otomatis setelah disetujui koordinator.'}
            />

            {pdil?.verifikasi?.keputusan === 'ditolak' && (
                <Notice tone="danger" className="mb-5" title={`Dikembalikan koordinator: ${pdil.verifikasi.alasan_label ?? ''}`}>
                    {pdil.verifikasi.catatan && <p>{pdil.verifikasi.catatan}</p>}
                    <p className="text-xs opacity-80">
                        {pdil.verifikasi.oleh} · {formatTanggalWaktu(pdil.verifikasi.waktu)}
                    </p>
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <form onSubmit={submit} noValidate className="grid content-start gap-6">
                    <section className="grid gap-5 rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Data pelanggan</h2>
                        {!perubahan && (
                            <FormField id="rute_id" label="Rute" required error={err.rute_id}>
                                <NativeSelect
                                    id="rute_id"
                                    value={data.rute_id}
                                    disabled={ubah}
                                    onChange={(e) => {
                                        const r = ruteOptions.find((o) => String(o.value) === e.target.value);
                                        setData((d) => ({ ...d, rute_id: e.target.value, kelurahan: d.kelurahan || r?.kelurahan || '' }));
                                    }}
                                >
                                    {ruteOptions.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        )}
                        {field('nama', 'Nama pelanggan', <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!err.nama} />)}
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field('alamat', 'Alamat', <Input id="alamat" value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} aria-invalid={!!err.alamat} />)}
                            {field('kelurahan', 'Kelurahan', <Input id="kelurahan" value={data.kelurahan} onChange={(e) => setData('kelurahan', e.target.value)} aria-invalid={!!err.kelurahan} />)}
                        </div>
                        {field(
                            'jenis_pelanggan',
                            'Jenis pelanggan',
                            <SegmentedTabs
                                label="Jenis pelanggan"
                                value={data.jenis_pelanggan}
                                onChange={(v) => setData('jenis_pelanggan', v)}
                                items={jenisPelangganOptions.map((o) => ({ value: o.value, label: o.label }))}
                            />,
                        )}
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field(
                                'golongan_tarif',
                                'Golongan tarif',
                                <NativeSelect id="golongan_tarif" value={data.golongan_tarif} onChange={(e) => setData('golongan_tarif', e.target.value)}>
                                    {golonganOptions.map((g) => (
                                        <option key={g} value={g}>
                                            {g}
                                        </option>
                                    ))}
                                </NativeSelect>,
                            )}
                            {field(
                                'daya_va',
                                'Daya (VA)',
                                <Input id="daya_va" type="number" inputMode="numeric" className="font-mono" value={data.daya_va} onChange={(e) => setData('daya_va', e.target.value)} aria-invalid={!!err.daya_va} />,
                            )}
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field(
                                'nomor_meter',
                                'Nomor meter',
                                <Input id="nomor_meter" className="font-mono" value={data.nomor_meter} onChange={(e) => setData('nomor_meter', e.target.value.replace(/\s/g, ''))} aria-invalid={!!err.nomor_meter} />,
                            )}
                            {field('letak_meter', 'Letak meter', <Input id="letak_meter" placeholder="Contoh: Teras depan" value={data.letak_meter} onChange={(e) => setData('letak_meter', e.target.value)} />, false)}
                        </div>
                    </section>

                    <section className="grid gap-4 rounded-14 border border-border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-section-title">Titik lokasi meter</h2>
                                <p className="text-[13px] text-muted-ink">Berdiri di dekat meter, lalu tekan "Pakai lokasi saya".</p>
                            </div>
                            <Button type="button" variant="secondary" onClick={pakaiLokasi} disabled={!geo.posisi}>
                                {geo.status === 'mencari' ? <LoaderCircle className="animate-spin" /> : <LocateFixed />}
                                Pakai lokasi saya
                            </Button>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field('latitude', 'Latitude', <Input id="latitude" inputMode="decimal" className="font-mono" value={data.latitude} onChange={(e) => setData('latitude', e.target.value)} />, false)}
                            {field('longitude', 'Longitude', <Input id="longitude" inputMode="decimal" className="font-mono" value={data.longitude} onChange={(e) => setData('longitude', e.target.value)} />, false)}
                        </div>
                        {geser !== null && geser > ambangJarak && (
                            <Notice tone="waiting" title={`Titik baru ${formatNumber(geser)} m dari titik lama`}>
                                Lebih dari {formatNumber(ambangJarak)} m; koordinator akan memeriksa perpindahan lokasi ini.
                            </Notice>
                        )}
                    </section>

                    <section className="grid gap-6 rounded-14 border border-border p-5 md:grid-cols-2">
                        <PhotoCapture
                            id="foto_rumah"
                            label="Foto rumah"
                            nilai={data.foto_rumah}
                            fotoTersimpan={pdil?.foto_rumah ? { url: pdil.foto_rumah.url, diambil_at: pdil.foto_rumah.diambil_at } : null}
                            onChange={(f) => setData('foto_rumah', f)}
                            posisi={geo.posisi}
                            statusLokasi={geo.status}
                            onUlangiLokasi={geo.ulangi}
                            ambangJarak={ambangJarak}
                            error={err.foto_rumah}
                            petunjuk="Belum ada foto. Foto tampak depan rumah beserta nomor rumah."
                        />
                        <PhotoCapture
                            id="foto_meter"
                            label="Foto meter"
                            nilai={data.foto_meter}
                            fotoTersimpan={pdil?.foto_meter ? { url: pdil.foto_meter.url, diambil_at: pdil.foto_meter.diambil_at } : null}
                            onChange={(f) => setData('foto_meter', f)}
                            posisi={geo.posisi}
                            statusLokasi={geo.status}
                            onUlangiLokasi={geo.ulangi}
                            ambangJarak={ambangJarak}
                            error={err.foto_meter}
                            petunjuk="Belum ada foto. Foto meter dengan nomor meter terbaca jelas."
                        />
                    </section>

                    <section className="rounded-14 border border-border p-5">
                        <FormField id="keterangan" label="Keterangan untuk koordinator" error={err.keterangan}>
                            <Textarea id="keterangan" rows={3} maxLength={500} value={data.keterangan} onChange={(e) => setData('keterangan', e.target.value)} />
                        </FormField>
                    </section>

                    <div className="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-14 border border-border bg-white/95 p-3 shadow-lg shadow-ink/5 backdrop-blur sm:flex-row sm:justify-end">
                        <Button type="button" variant="secondary" asChild>
                            <Link href={route('pdil.index')}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing || (perubahan && daftarBerubah.length === 0)}>
                            {processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                            {ubah ? 'Kirim perbaikan' : 'Kirim PDIL'}
                        </Button>
                    </div>
                </form>

                <aside className="grid content-start gap-4">
                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">{perubahan ? 'Yang berubah' : 'Ringkasan'}</h2>
                        {perubahan ? (
                            daftarBerubah.length === 0 ? (
                                <p className="mt-2 text-sm text-muted-ink">Belum ada data yang diubah. Kolom yang diubah akan disorot kuning.</p>
                            ) : (
                                <ul className="mt-3 divide-y divide-line-row text-sm">
                                    {daftarBerubah.map((k) => (
                                        <li key={k} className="py-2.5">
                                            <p className="text-[13px] text-muted-ink">{LABEL[k]}</p>
                                            <p className="text-error-ink line-through">{teks(lama?.[k]) || 'kosong'}</p>
                                            <p className="font-medium text-success-ink">{teks(data[k as keyof typeof data] as string) || 'kosong'}</p>
                                        </li>
                                    ))}
                                </ul>
                            )
                        ) : (
                            <dl className="mt-3 divide-y divide-line-row text-sm">
                                <div className="flex justify-between gap-3 py-2.5">
                                    <dt className="text-muted-ink">Rute</dt>
                                    <dd>{ruteOptions.find((r) => String(r.value) === data.rute_id)?.label ?? '–'}</dd>
                                </div>
                                <div className="flex justify-between gap-3 py-2.5">
                                    <dt className="text-muted-ink">Tarif</dt>
                                    <dd>
                                        {data.golongan_tarif} / {formatNumber(Number(data.daya_va) || 0)} VA
                                    </dd>
                                </div>
                                <div className="py-2.5 text-[13px] text-muted-ink">Pelanggan baru ditaruh di urutan terakhir rute dan langsung masuk daftar tugas periode berikutnya.</div>
                            </dl>
                        )}
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
