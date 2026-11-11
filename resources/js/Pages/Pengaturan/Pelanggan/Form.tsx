import { Link, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, LoaderCircle, LocateFixed } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';

import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { Option, PageProps, RuteOption, UnitOption } from '@/types';

interface PelangganData {
    id: number;
    idpel: string;
    rute_id: number;
    urutan_rute: number;
    nama: string;
    alamat: string;
    kelurahan: string;
    jenis: string;
    golongan_tarif: string;
    daya_va: number;
    nomor_meter: string;
    letak_meter: string | null;
    latitude: number | null;
    longitude: number | null;
    status: string;
}

type Props = PageProps<{
    pelanggan: PelangganData | null;
    options: {
        units: UnitOption[];
        rute: RuteOption[];
        jenis: Option[];
        status: Option[];
        golongan: string[];
        urutan_berikutnya: Record<string, number>;
    };
}>;

const DAYA_UMUM = [450, 900, 1300, 2200, 3500, 4400, 5500, 7700, 11000, 13200];

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-14 border border-border">
            <h2 className="border-b border-line-row px-5 py-4 text-section-title">{title}</h2>
            <div className="grid gap-5 p-5">{children}</div>
        </section>
    );
}

export default function PelangganForm() {
    const { pelanggan, options } = usePage<Props>().props;
    const edit = pelanggan !== null;
    const ruteAwal = pelanggan ? options.rute.find((r) => r.id === pelanggan.rute_id) : undefined;
    const [unitId, setUnitId] = useState(ruteAwal ? String(ruteAwal.unit_id) : '');
    const [mencariLokasi, setMencariLokasi] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        idpel: pelanggan?.idpel ?? '',
        rute_id: pelanggan ? String(pelanggan.rute_id) : '',
        urutan_rute: pelanggan ? String(pelanggan.urutan_rute) : '',
        nama: pelanggan?.nama ?? '',
        alamat: pelanggan?.alamat ?? '',
        kelurahan: pelanggan?.kelurahan ?? '',
        jenis: pelanggan?.jenis ?? 'pascabayar',
        golongan_tarif: pelanggan?.golongan_tarif ?? 'R1',
        daya_va: pelanggan ? String(pelanggan.daya_va) : '1300',
        nomor_meter: pelanggan?.nomor_meter ?? '',
        letak_meter: pelanggan?.letak_meter ?? '',
        latitude: pelanggan?.latitude != null ? String(pelanggan.latitude) : '',
        longitude: pelanggan?.longitude != null ? String(pelanggan.longitude) : '',
        status: pelanggan?.status ?? 'aktif',
    });

    const ruteUnit = options.rute.filter((r) => !unitId || String(r.unit_id) === unitId);

    const pilihRute = (id: string) => {
        const rute = options.rute.find((r) => String(r.id) === id);
        setData((d) => ({
            ...d,
            rute_id: id,
            kelurahan: d.kelurahan || rute?.kelurahan || '',
            urutan_rute: edit ? d.urutan_rute : String(options.urutan_berikutnya[id] ?? 1),
        }));
    };

    const ambilLokasi = () => {
        if (!navigator.geolocation) {
            toast.error('Perangkat ini tidak mendukung lokasi GPS.');
            return;
        }
        setMencariLokasi(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData((d) => ({
                    ...d,
                    latitude: pos.coords.latitude.toFixed(7),
                    longitude: pos.coords.longitude.toFixed(7),
                }));
                setMencariLokasi(false);
            },
            () => {
                toast.error('Lokasi tidak bisa diambil. Pastikan izin lokasi aktif.');
                setMencariLokasi(false);
            },
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (edit) put(route('pengaturan.pelanggan.update', pelanggan.id));
        else post(route('pengaturan.pelanggan.store'));
    };

    const rute = options.rute.find((r) => String(r.id) === data.rute_id);
    const punyaKoordinat = data.latitude !== '' && data.longitude !== '';

    return (
        <AppLayout
            title={edit ? `Ubah ${pelanggan.nama}` : 'Tambah pelanggan'}
            breadcrumbs={[
                { label: 'Data master' },
                { label: 'Pelanggan', href: route('pengaturan.pelanggan.index') },
                { label: edit ? 'Ubah' : 'Tambah' },
            ]}
        >
            <PageHeader
                title={edit ? 'Ubah pelanggan' : 'Tambah pelanggan'}
                description={edit ? `${pelanggan.nama} · ${pelanggan.idpel}` : 'Pelanggan baru langsung masuk ke daftar tugas rute yang dipilih.'}
            />

            <form onSubmit={submit} noValidate className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid gap-6">
                    <Section title="Identitas">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField id="idpel" label="ID pelanggan" required error={errors.idpel} hint="12 digit angka.">
                                <Input
                                    id="idpel"
                                    inputMode="numeric"
                                    maxLength={12}
                                    className="font-mono"
                                    value={data.idpel}
                                    onChange={(e) => setData('idpel', e.target.value.replace(/\D/g, ''))}
                                    aria-invalid={!!errors.idpel}
                                />
                            </FormField>
                            <FormField id="nomor_meter" label="Nomor meter" required error={errors.nomor_meter}>
                                <Input
                                    id="nomor_meter"
                                    className="font-mono"
                                    value={data.nomor_meter}
                                    onChange={(e) => setData('nomor_meter', e.target.value.replace(/\s/g, ''))}
                                    aria-invalid={!!errors.nomor_meter}
                                />
                            </FormField>
                        </div>
                        <FormField id="nama" label="Nama pelanggan" required error={errors.nama}>
                            <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!errors.nama} />
                        </FormField>
                        <FormField id="status" label="Status sambungan" required error={errors.status}>
                            <SegmentedTabs
                                label="Status sambungan"
                                value={data.status}
                                onChange={(v) => setData('status', v)}
                                items={options.status.map((s) => ({ value: s.value, label: s.label }))}
                            />
                        </FormField>
                    </Section>

                    <Section title="Rute dan lokasi">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField id="unit" label="Unit">
                                <NativeSelect
                                    id="unit"
                                    value={unitId}
                                    onChange={(e) => {
                                        setUnitId(e.target.value);
                                        setData('rute_id', '');
                                    }}
                                >
                                    <option value="">Semua unit</option>
                                    {options.units
                                        .filter((u) => u.jenis === 'ulp')
                                        .map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.nama}
                                            </option>
                                        ))}
                                </NativeSelect>
                            </FormField>
                            <FormField id="rute_id" label="Rute" required error={errors.rute_id}>
                                <NativeSelect
                                    id="rute_id"
                                    value={data.rute_id}
                                    onChange={(e) => pilihRute(e.target.value)}
                                    aria-invalid={!!errors.rute_id}
                                >
                                    <option value="">Pilih rute</option>
                                    {ruteUnit.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.kode} · {r.nama}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-[140px_1fr]">
                            <FormField id="urutan_rute" label="Urutan" required error={errors.urutan_rute}>
                                <Input
                                    id="urutan_rute"
                                    type="number"
                                    min={1}
                                    inputMode="numeric"
                                    className="font-mono"
                                    value={data.urutan_rute}
                                    onChange={(e) => setData('urutan_rute', e.target.value)}
                                    aria-invalid={!!errors.urutan_rute}
                                />
                            </FormField>
                            <FormField id="alamat" label="Alamat" required error={errors.alamat}>
                                <Input id="alamat" value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} aria-invalid={!!errors.alamat} />
                            </FormField>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField id="kelurahan" label="Kelurahan" required error={errors.kelurahan}>
                                <Input
                                    id="kelurahan"
                                    value={data.kelurahan}
                                    onChange={(e) => setData('kelurahan', e.target.value)}
                                    aria-invalid={!!errors.kelurahan}
                                />
                            </FormField>
                            <FormField id="letak_meter" label="Letak meter" error={errors.letak_meter}>
                                <Input
                                    id="letak_meter"
                                    placeholder="Contoh: Teras depan"
                                    value={data.letak_meter}
                                    onChange={(e) => setData('letak_meter', e.target.value)}
                                />
                            </FormField>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                            <FormField id="latitude" label="Latitude" error={errors.latitude}>
                                <Input
                                    id="latitude"
                                    inputMode="decimal"
                                    className="font-mono"
                                    placeholder="0.4650000"
                                    value={data.latitude}
                                    onChange={(e) => setData('latitude', e.target.value)}
                                    aria-invalid={!!errors.latitude}
                                />
                            </FormField>
                            <FormField id="longitude" label="Longitude" error={errors.longitude}>
                                <Input
                                    id="longitude"
                                    inputMode="decimal"
                                    className="font-mono"
                                    placeholder="101.3790000"
                                    value={data.longitude}
                                    onChange={(e) => setData('longitude', e.target.value)}
                                    aria-invalid={!!errors.longitude}
                                />
                            </FormField>
                            <Button type="button" variant="secondary" className="sm:mt-[21px]" onClick={ambilLokasi} disabled={mencariLokasi}>
                                {mencariLokasi ? <LoaderCircle className="animate-spin" /> : <LocateFixed />}
                                Lokasi saya
                            </Button>
                        </div>
                        <p className="-mt-2 text-xs text-muted-ink">
                            Titik ini dipakai untuk memeriksa jarak GPS biller saat mencatat meter.
                        </p>
                    </Section>

                    <Section title="Tarif">
                        <FormField id="jenis" label="Jenis pelanggan" required error={errors.jenis}>
                            <SegmentedTabs
                                label="Jenis pelanggan"
                                value={data.jenis}
                                onChange={(v) => setData('jenis', v)}
                                items={options.jenis.map((j) => ({ value: j.value, label: j.label }))}
                            />
                        </FormField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField id="golongan_tarif" label="Golongan tarif" required error={errors.golongan_tarif}>
                                <NativeSelect
                                    id="golongan_tarif"
                                    value={data.golongan_tarif}
                                    onChange={(e) => setData('golongan_tarif', e.target.value)}
                                    aria-invalid={!!errors.golongan_tarif}
                                >
                                    {options.golongan.map((g) => (
                                        <option key={g} value={g}>
                                            {g}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField id="daya_va" label="Daya (VA)" required error={errors.daya_va}>
                                <Input
                                    id="daya_va"
                                    type="number"
                                    inputMode="numeric"
                                    list="daya-umum"
                                    className="font-mono"
                                    value={data.daya_va}
                                    onChange={(e) => setData('daya_va', e.target.value)}
                                    aria-invalid={!!errors.daya_va}
                                />
                                <datalist id="daya-umum">
                                    {DAYA_UMUM.map((d) => (
                                        <option key={d} value={d} />
                                    ))}
                                </datalist>
                            </FormField>
                        </div>
                    </Section>
                </div>

                <aside className="grid gap-4 self-start lg:sticky lg:top-6">
                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Ringkasan</h2>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Ringkas label="ID pelanggan" value={<span className="font-mono">{data.idpel || '–'}</span>} />
                            <Ringkas label="Rute" value={rute ? `${rute.kode} · urutan ${data.urutan_rute || '–'}` : '–'} />
                            <Ringkas
                                label="Tarif"
                                value={data.golongan_tarif && data.daya_va ? `${data.golongan_tarif} / ${formatNumber(Number(data.daya_va))} VA` : '–'}
                            />
                            <Ringkas label="Jenis" value={options.jenis.find((j) => j.value === data.jenis)?.label ?? '–'} />
                            <Ringkas
                                label="Koordinat"
                                value={
                                    punyaKoordinat ? (
                                        <a
                                            href={`https://www.google.com/maps?q=${data.latitude},${data.longitude}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-pln-blue hover:underline"
                                        >
                                            Buka di peta
                                            <ExternalLink className="size-3.5" />
                                        </a>
                                    ) : (
                                        <span className="text-error-ink">Belum diisi</span>
                                    )
                                }
                            />
                        </dl>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <Button type="submit" className="sm:order-2 lg:order-1" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {edit ? 'Simpan perubahan' : 'Tambah pelanggan'}
                        </Button>
                        <Button type="button" variant="secondary" asChild className="sm:order-1 lg:order-2">
                            <Link href={route('pengaturan.pelanggan.index')}>Batal</Link>
                        </Button>
                    </div>
                </aside>
            </form>
        </AppLayout>
    );
}

function Ringkas({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-2.5">
            <dt className="text-muted-ink">{label}</dt>
            <dd className="text-right">{value}</dd>
        </div>
    );
}
