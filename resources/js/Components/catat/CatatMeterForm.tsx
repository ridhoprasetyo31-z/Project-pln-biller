import { Link, useForm } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, CircleAlert, Circle, LoaderCircle, Navigation, Save, Send } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import { InputError } from '@/Components/shared/FormField';
import MeterDigits from '@/Components/shared/MeterDigits';
import Notice from '@/Components/shared/Notice';
import OptionCards from '@/Components/shared/OptionCards';
import PageHeader from '@/Components/shared/PageHeader';
import PhotoCapture, { type FotoTerpilih } from '@/Components/shared/PhotoCapture';
import StandInput from '@/Components/shared/StandInput';
import { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import UsageBars from '@/Components/shared/UsageBars';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useGeolocation } from '@/hooks/useGeolocation';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { jarakMeter, urlRute } from '@/lib/geo';
import { statusCatatan } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { CatatMeterProps } from '@/types/catat';

type Jenis = 'pascabayar' | 'prabayar';

/** "18.912,4" / "18912,4" → 18912.4 */
function keAngka(v: string): number | null {
    if (v.trim() === '') return null;
    const n = Number(v.replace(/\./g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

function keTeks(n: number | null | undefined): string {
    return n === null || n === undefined ? '' : String(n).replace('.', ',');
}

type Cek = 'ok' | 'gagal' | 'tunggu';

export default function CatatMeterForm({ props, jenis }: { props: CatatMeterProps; jenis: Jenis }) {
    const { periode, pelanggan, standLalu, rataTigaBulan, riwayat, catatan, bisaDiubah, navigasi, progres, kondisiOptions, ambang } = props;
    const pasca = jenis === 'pascabayar';
    const geo = useGeolocation();

    const { data, setData, post, processing, errors, transform } = useForm<{
        aksi: 'draf' | 'kirim';
        stand_kini: string;
        sisa_kwh: string;
        kondisi_meter: string;
        keterangan: string;
        foto: FotoTerpilih | null;
    }>({
        aksi: 'kirim',
        stand_kini: keTeks(catatan?.stand_kini),
        sisa_kwh: keTeks(catatan?.sisa_kwh),
        kondisi_meter: catatan?.kondisi_meter ?? 'normal',
        keterangan: catatan?.keterangan ?? '',
        foto: null,
    });
    const err = errors as Record<string, string>;

    // ---- Perhitungan langsung ----
    const standKini = keAngka(data.stand_kini);
    const pemakaian = pasca && standKini !== null && standLalu !== null ? Math.round((standKini - standLalu) * 10) / 10 : null;
    const persen = pemakaian !== null && rataTigaBulan ? (pemakaian / rataTigaBulan) * 100 : null;
    const normal = data.kondisi_meter === 'normal';
    const lat = data.foto?.lat ?? geo.posisi?.lat ?? catatan?.latitude ?? null;
    const lng = data.foto?.lng ?? geo.posisi?.lng ?? catatan?.longitude ?? null;
    const jarak =
        lat !== null && lng !== null && pelanggan.latitude !== null && pelanggan.longitude !== null
            ? jarakMeter(lat, lng, pelanggan.latitude, pelanggan.longitude)
            : null;
    const adaFoto = !!data.foto || !!catatan?.foto;
    const batas = new Date(`${periode.tanggal_selesai}T23:59:59+07:00`);

    const cek: { label: string; status: Cek; ket?: string }[] = [
        ...(pasca
            ? [
                  {
                      label: 'Stand tidak lebih kecil dari stand lalu',
                      status: (pemakaian === null ? 'tunggu' : pemakaian < 0 && normal ? 'gagal' : 'ok') as Cek,
                  },
                  {
                      label: `Pemakaian maksimal ${formatNumber(ambang.lonjakan_persen)}% dari rata-rata 3 bulan`,
                      status: (persen === null ? 'tunggu' : persen > ambang.lonjakan_persen ? 'gagal' : 'ok') as Cek,
                      ket: rataTigaBulan ? `Rata-rata ${formatNumber(rataTigaBulan, 1)} kWh` : 'Belum ada riwayat',
                  },
                  {
                      label: 'Pemakaian tidak nol saat meter normal',
                      status: (pemakaian === null ? 'tunggu' : pemakaian === 0 && normal ? 'gagal' : 'ok') as Cek,
                  },
              ]
            : [
                  {
                      label: 'Tidak ada indikasi pelanggaran',
                      status: (data.kondisi_meter === 'indikasi_pelanggaran' ? 'gagal' : 'ok') as Cek,
                  },
              ]),
        { label: 'Foto meter terlampir', status: adaFoto ? 'ok' : 'tunggu' },
        {
            label: `Lokasi GPS maksimal ${formatNumber(ambang.jarak_m)} m dari pelanggan`,
            status: jarak === null ? (geo.status === 'mencari' ? 'tunggu' : 'gagal') : jarak > ambang.jarak_m ? 'gagal' : 'ok',
            ket: jarak !== null ? `${formatNumber(jarak)} m` : undefined,
        },
        {
            label: `Dikirim sebelum ${formatTanggal(periode.tanggal_selesai, 'd MMM yyyy')}`,
            status: new Date() <= batas ? 'ok' : 'gagal',
        },
    ];

    const kirim = (aksi: 'draf' | 'kirim') => {
        transform((d) => ({
            aksi,
            stand_kini: pasca ? d.stand_kini : undefined,
            sisa_kwh: pasca ? undefined : d.sisa_kwh,
            kondisi_meter: d.kondisi_meter,
            keterangan: d.keterangan,
            latitude: lat ?? '',
            longitude: lng ?? '',
            foto: d.foto?.file ?? null,
            foto_diambil_at: d.foto?.diambil_at ?? '',
        }));
        post(route(`catat.${jenis}.store`, pelanggan.id), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: 'errors',
            onError: (e) => toast.error(e.catatan ?? 'Periksa kembali isian yang ditandai merah.'),
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        kirim('kirim');
    };

    const status = catatan ? statusCatatan(catatan.status, catatan.anomali[0]?.jenis) : null;

    return (
        <AppLayout
            title={`Catat ${pelanggan.nama}`}
            breadcrumbs={[
                { label: 'Pekerjaan saya' },
                { label: 'Tugas hari ini', href: route('tugas.index') },
                { label: `No. ${String(pelanggan.urutan).padStart(3, '0')}` },
            ]}
        >
            <PageHeader
                title={pelanggan.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="font-mono">{pelanggan.idpel}</span>·<span>Rute {pelanggan.rute}</span>·
                        <span>{pasca ? 'Pascabayar' : 'Prabayar'}</span>
                        {status && <StatusChip {...status} />}
                    </span>
                }
                actions={
                    <>
                        <NavButton arah="sebelumnya" item={navigasi.sebelumnya} />
                        <NavButton arah="berikutnya" item={navigasi.berikutnya} />
                    </>
                }
            />

            {catatan?.status === 'revisi' && catatan.verifikasi && (
                <Notice tone="danger" className="mb-5" title={`Dikembalikan koordinator: ${catatan.verifikasi.alasan_label ?? 'perlu diperbaiki'}`}>
                    {catatan.verifikasi.catatan && <p>{catatan.verifikasi.catatan}</p>}
                    <p className="text-xs opacity-80">
                        {catatan.verifikasi.oleh ?? 'Koordinator'} · {formatTanggalWaktu(catatan.verifikasi.waktu)} · revisi ke-
                        {catatan.jumlah_revisi}
                    </p>
                </Notice>
            )}

            {!bisaDiubah && catatan && (
                <Notice tone={catatan.status === 'disetujui' ? 'success' : 'info'} className="mb-5" title="Catatan sudah dikirim">
                    Dikirim {formatTanggalWaktu(catatan.dikirim_at)}. Catatan yang sedang diverifikasi atau sudah disetujui tidak
                    bisa diubah; perubahan setelah disetujui lewat Koreksi Rekening.
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <form onSubmit={submit} noValidate className="grid content-start gap-6">
                    <section className="grid gap-5 rounded-14 border border-border p-5">
                        {pasca ? (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="stand_kini">
                                        Stand kini
                                        <span className="text-pln-red" aria-hidden>
                                            *
                                        </span>
                                    </Label>
                                    <StandInput
                                        id="stand_kini"
                                        autoFocus={bisaDiubah}
                                        placeholder="0,0"
                                        value={data.stand_kini}
                                        onChange={(v) => setData('stand_kini', v)}
                                        disabled={!bisaDiubah}
                                        aria-invalid={!!err.stand_kini}
                                        aria-describedby="stand-lalu"
                                    />
                                    <div id="stand-lalu" className="flex flex-wrap items-center gap-2 text-[13px] text-muted-ink">
                                        Stand lalu
                                        {standLalu !== null ? (
                                            <MeterDigits value={standLalu} decimals={1} size="sm" />
                                        ) : (
                                            <span>belum ada, ini catatan pertama</span>
                                        )}
                                    </div>
                                    <InputError message={err.stand_kini} />
                                </div>
                                <PanelPemakaian
                                    pemakaian={pemakaian}
                                    persen={persen}
                                    rata={rataTigaBulan}
                                    ambang={ambang.lonjakan_persen}
                                    normal={normal}
                                />
                            </>
                        ) : (
                            <div className="grid gap-2">
                                <Label htmlFor="sisa_kwh">
                                    Sisa kWh di layar meter
                                    <span className="text-pln-red" aria-hidden>
                                        *
                                    </span>
                                </Label>
                                <StandInput
                                    id="sisa_kwh"
                                    autoFocus={bisaDiubah}
                                    desimal={2}
                                    placeholder="0,00"
                                    value={data.sisa_kwh}
                                    onChange={(v) => setData('sisa_kwh', v)}
                                    disabled={!bisaDiubah}
                                    aria-invalid={!!err.sisa_kwh}
                                />
                                <p className="text-[13px] text-muted-ink">Ketik angka sisa kWh yang tampil di layar meter prabayar.</p>
                                <InputError message={err.sisa_kwh} />
                            </div>
                        )}
                    </section>

                    <section className="rounded-14 border border-border p-5">
                        <PhotoCapture
                            id="foto"
                            label={pasca ? 'Foto meter' : 'Foto layar meter'}
                            nilai={data.foto}
                            fotoTersimpan={catatan?.foto ? { url: catatan.foto.url, diambil_at: catatan.foto.diambil_at } : null}
                            onChange={(foto) => setData('foto', foto)}
                            posisi={geo.posisi}
                            statusLokasi={geo.status}
                            onUlangiLokasi={geo.ulangi}
                            titikPelanggan={{ lat: pelanggan.latitude, lng: pelanggan.longitude }}
                            ambangJarak={ambang.jarak_m}
                            error={err.foto}
                            disabled={!bisaDiubah}
                        />
                    </section>

                    <section className="grid gap-5 rounded-14 border border-border p-5">
                        <OptionCards
                            name="kondisi_meter"
                            label="Kondisi meter"
                            value={data.kondisi_meter}
                            onChange={(v) => setData('kondisi_meter', v)}
                            options={kondisiOptions}
                            disabled={!bisaDiubah}
                        />
                        <InputError message={err.kondisi_meter} />
                        <div className="grid gap-2">
                            <Label htmlFor="keterangan">
                                Keterangan
                                {pasca && ['meter_rusak', 'akses_tertutup'].includes(data.kondisi_meter) && !data.stand_kini && (
                                    <span className="text-pln-red" aria-hidden>
                                        *
                                    </span>
                                )}
                            </Label>
                            <Textarea
                                id="keterangan"
                                rows={3}
                                maxLength={500}
                                placeholder={normal ? 'Catatan tambahan untuk koordinator' : 'Jelaskan kondisi meter yang ditemui'}
                                value={data.keterangan}
                                onChange={(e) => setData('keterangan', e.target.value)}
                                disabled={!bisaDiubah}
                                aria-invalid={!!err.keterangan}
                            />
                            <InputError message={err.keterangan} />
                        </div>
                    </section>

                    {bisaDiubah && (
                        <div className="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-14 border border-border bg-white/95 p-3 shadow-lg shadow-ink/5 backdrop-blur sm:flex-row sm:justify-end">
                            <Button type="button" variant="secondary" disabled={processing} onClick={() => kirim('draf')}>
                                <Save />
                                Simpan draf
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                                Kirim dan catat berikutnya
                            </Button>
                        </div>
                    )}
                </form>

                <aside className="grid content-start gap-4">
                    <div className="rounded-14 border border-border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-section-title">Pelanggan</h2>
                            {pelanggan.latitude !== null && pelanggan.longitude !== null && (
                                <a
                                    href={urlRute(pelanggan.latitude, pelanggan.longitude)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-[13px] font-medium text-pln-blue hover:underline"
                                >
                                    <Navigation className="size-3.5" />
                                    Petunjuk arah
                                </a>
                            )}
                        </div>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="ID pelanggan" value={<span className="font-mono">{pelanggan.idpel}</span>} />
                            <Baris label="Nomor meter" value={<span className="font-mono">{pelanggan.nomor_meter}</span>} />
                            <Baris label="Tarif" value={pelanggan.tarif} />
                            <Baris label="Alamat" value={`${pelanggan.alamat}, ${pelanggan.kelurahan}`} />
                            <Baris label="Letak meter" value={pelanggan.letak_meter ?? '–'} />
                        </dl>
                    </div>

                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">{pasca ? 'Riwayat pemakaian' : 'Riwayat sisa kWh'}</h2>
                        <p className="mb-4 text-[13px] text-muted-ink">6 bulan terakhir yang disetujui</p>
                        <UsageBars
                            data={[
                                ...riwayat.map((r) => ({ label: r.label, nilai: r.nilai })),
                                {
                                    label: 'Kini',
                                    nilai: pasca ? pemakaian : keAngka(data.sisa_kwh),
                                    kini: true,
                                },
                            ]}
                            anomali={persen !== null && persen > ambang.lonjakan_persen}
                        />
                    </div>

                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Dicek sistem saat dikirim</h2>
                        <p className="text-[13px] text-muted-ink">Yang tidak lolos tetap bisa dikirim, tapi ditandai untuk koordinator.</p>
                        <ul className="mt-3 grid gap-2.5">
                            {cek.map((c) => (
                                <li key={c.label} className="flex items-start gap-2.5 text-sm">
                                    <CekIkon status={c.status} />
                                    <span className="min-w-0">
                                        <span className={cn(c.status === 'gagal' && 'text-error-ink')}>{c.label}</span>
                                        {c.ket && <span className="block text-xs text-muted-ink">{c.ket}</span>}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="rounded-14 border border-border p-5">
                        <div className="flex items-baseline justify-between text-sm">
                            <span className="text-muted-ink">Progres rute {pelanggan.rute}</span>
                            <span className="font-medium">
                                {formatNumber(progres.terkirim)} / {formatNumber(progres.target)}
                            </span>
                        </div>
                        <ProgressBar className="mt-2" value={progres.target ? (progres.terkirim / progres.target) * 100 : 0} />
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}

function PanelPemakaian({
    pemakaian,
    persen,
    rata,
    ambang,
    normal,
}: {
    pemakaian: number | null;
    persen: number | null;
    rata: number | null;
    ambang: number;
    normal: boolean;
}) {
    if (pemakaian === null) {
        return (
            <div className="rounded-10 bg-table-head px-4 py-3 text-sm text-muted-ink">
                Pemakaian dihitung otomatis setelah stand kini diisi.
            </div>
        );
    }

    let notice: ReactNode;
    if (pemakaian < 0 && normal) {
        notice = (
            <Notice tone="danger" title="Stand kini lebih kecil dari stand lalu">
                Periksa ulang angka di meter. Bila meter memang diganti atau rusak, pilih kondisi yang sesuai.
            </Notice>
        );
    } else if (persen !== null && persen > ambang) {
        notice = (
            <Notice tone="danger" title={`Lonjakan pemakaian ${formatNumber(persen)}% dari rata-rata`}>
                Rata-rata 3 bulan {formatNumber(rata ?? 0, 1)} kWh. Pastikan angka benar dan foto jelas; catatan akan ditandai untuk
                diperiksa koordinator.
            </Notice>
        );
    } else if (pemakaian === 0 && normal) {
        notice = (
            <Notice tone="danger" title="Pemakaian 0 kWh">
                Bila rumah kosong, pilih kondisi Rumah kosong.
            </Notice>
        );
    } else {
        notice = (
            <Notice tone="success" title="Pemakaian wajar">
                {persen !== null ? `${formatNumber(persen)}% dari rata-rata 3 bulan (${formatNumber(rata ?? 0, 1)} kWh).` : 'Belum ada rata-rata pembanding.'}
            </Notice>
        );
    }

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2 rounded-10 bg-table-head px-4 py-3">
                <span className="text-[13px] text-muted-ink">Pemakaian bulan ini</span>
                <span className={cn('font-mono text-2xl font-semibold', pemakaian < 0 ? 'text-error-ink' : 'text-ink')}>
                    {formatNumber(pemakaian, 1)} <span className="text-sm font-normal text-muted-ink">kWh</span>
                </span>
            </div>
            {notice}
        </div>
    );
}

function CekIkon({ status }: { status: Cek }) {
    if (status === 'ok') {
        return (
            <span className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-success-tint text-success-ink">
                <Check className="size-3" strokeWidth={3} />
                <span className="sr-only">Lolos</span>
            </span>
        );
    }
    if (status === 'gagal') {
        return (
            <span className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-error-tint text-pln-red">
                <CircleAlert className="size-3.5" />
                <span className="sr-only">Akan ditandai</span>
            </span>
        );
    }
    return (
        <span className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center text-muted-ink/60">
            <Circle className="size-4" />
            <span className="sr-only">Belum bisa dicek</span>
        </span>
    );
}

function Baris({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-2.5">
            <dt className="shrink-0 text-muted-ink">{label}</dt>
            <dd className="min-w-0 text-right break-words">{value}</dd>
        </div>
    );
}

function NavButton({ arah, item }: { arah: 'sebelumnya' | 'berikutnya'; item: { urutan: number; nama: string; url: string } | null }) {
    const isi =
        arah === 'sebelumnya' ? (
            <>
                <ChevronLeft />
                {item ? `No. ${String(item.urutan).padStart(3, '0')}` : 'Awal rute'}
            </>
        ) : (
            <>
                {item ? `No. ${String(item.urutan).padStart(3, '0')}` : 'Akhir rute'}
                <ChevronRight />
            </>
        );

    if (!item) {
        return (
            <Button variant="secondary" size="sm" disabled>
                {isi}
            </Button>
        );
    }
    return (
        <Button variant="secondary" size="sm" asChild>
            <Link href={item.url} title={item.nama}>
                {isi}
            </Link>
        </Button>
    );
}
