import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Send } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import FormField, { InputError } from '@/Components/shared/FormField';
import MeterDigits from '@/Components/shared/MeterDigits';
import Notice from '@/Components/shared/Notice';
import OptionCards from '@/Components/shared/OptionCards';
import PageHeader from '@/Components/shared/PageHeader';
import PhotoCapture, { type FotoTerpilih } from '@/Components/shared/PhotoCapture';
import StandInput from '@/Components/shared/StandInput';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useGeolocation } from '@/hooks/useGeolocation';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { keAngka, rincianTagihan, rupiah } from '@/lib/tagihan';
import { cn } from '@/lib/utils';
import type { Option, PageProps } from '@/types';
import type { FotoInfo, VerifikasiInfo } from '@/types/catat';

type Props = PageProps<{
    invoice: { id: number; nomor: string; periode: string; status_label: string; pemakaian_kwh: number; harga_per_kwh: number; biaya_beban: number; ppj_persen: number; total: number };
    pelanggan: { idpel: string; nama: string; alamat: string; tarif: string };
    pencatatan: { stand_lalu: number | null; stand_kini: number | null; foto: FotoInfo | null };
    koreksi: { id: number; stand_baru: number; penyebab: string; alasan: string; foto: FotoInfo | null; verifikasi: VerifikasiInfo | null; jumlah_revisi: number } | null;
    penyebabOptions: Option[];
}>;

const HINT: Record<string, string> = {
    salah_catat_biller: 'Mengurangi akurasi biller pencatat',
    salah_input_sistem: 'Tidak memengaruhi skor',
    komplain_pelanggan: 'Tidak memengaruhi skor',
    meter_rusak: 'Tidak memengaruhi skor',
};

export default function KoreksiForm() {
    const { invoice, pelanggan, pencatatan, koreksi, penyebabOptions } = usePage<Props>().props;
    const geo = useGeolocation();
    const ubah = koreksi !== null;

    const { data, setData, post, processing, errors, transform } = useForm<{ stand_baru: string; penyebab: string; alasan: string; foto: FotoTerpilih | null }>({
        stand_baru: koreksi ? String(koreksi.stand_baru).replace('.', ',') : '',
        penyebab: koreksi?.penyebab ?? 'salah_catat_biller',
        alasan: koreksi?.alasan ?? '',
        foto: null,
    });
    const err = errors as Record<string, string>;

    const standBaru = keAngka(data.stand_baru);
    const standLalu = pencatatan.stand_lalu ?? 0;
    const baru = standBaru !== null ? rincianTagihan(standBaru - standLalu, invoice.harga_per_kwh, invoice.biaya_beban, invoice.ppj_persen) : null;
    const selisih = baru ? baru.total - invoice.total : null;
    const dibawahLalu = standBaru !== null && pencatatan.stand_lalu !== null && standBaru < pencatatan.stand_lalu;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform((d) => ({
            ...(ubah ? { _method: 'put' } : { invoice_id: invoice.id }),
            stand_baru: d.stand_baru,
            penyebab: d.penyebab,
            alasan: d.alasan,
            foto: d.foto?.file ?? null,
            foto_diambil_at: d.foto?.diambil_at ?? '',
        }));
        post(ubah ? route('koreksi-rekening.update', koreksi.id) : route('koreksi-rekening.store'), {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => toast.error(e.koreksi ?? 'Periksa kembali isian yang ditandai merah.'),
        });
    };

    return (
        <AppLayout
            title="Koreksi rekening"
            breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'Koreksi rekening', href: route('koreksi-rekening.index') }, { label: ubah ? 'Perbaiki' : 'Ajukan' }]}
        >
            <PageHeader title={ubah ? 'Perbaiki koreksi rekening' : 'Ajukan koreksi rekening'} description={`${pelanggan.nama} · ${invoice.nomor} · ${invoice.periode}`} />

            {koreksi?.verifikasi?.keputusan === 'ditolak' && (
                <Notice tone="danger" className="mb-5" title={`Dikembalikan koordinator: ${koreksi.verifikasi.alasan_label ?? ''}`}>
                    {koreksi.verifikasi.catatan && <p>{koreksi.verifikasi.catatan}</p>}
                    <p className="text-xs opacity-80">
                        {koreksi.verifikasi.oleh} · {formatTanggalWaktu(koreksi.verifikasi.waktu)}
                    </p>
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <form onSubmit={submit} noValidate className="grid content-start gap-6">
                    <section className="grid gap-4 rounded-14 border border-border p-5">
                        <div className="grid gap-2">
                            <Label htmlFor="stand_baru">
                                Stand yang benar
                                <span className="text-pln-red" aria-hidden>
                                    *
                                </span>
                            </Label>
                            <StandInput id="stand_baru" autoFocus placeholder="0,0" value={data.stand_baru} onChange={(v) => setData('stand_baru', v)} aria-invalid={!!err.stand_baru} />
                            <div className="flex flex-wrap gap-x-6 gap-y-2 text-[13px] text-muted-ink">
                                <span className="inline-flex items-center gap-2">
                                    Stand lalu {pencatatan.stand_lalu !== null ? <MeterDigits value={pencatatan.stand_lalu} decimals={1} size="sm" /> : '–'}
                                </span>
                                <span className="inline-flex items-center gap-2">
                                    Tercatat {pencatatan.stand_kini !== null ? <MeterDigits value={pencatatan.stand_kini} decimals={1} size="sm" /> : '–'}
                                </span>
                            </div>
                            <InputError message={err.stand_baru} />
                        </div>

                        <div className="grid overflow-hidden rounded-10 border border-border text-sm sm:grid-cols-2">
                            <Bandingan judul="Sekarang" kwh={invoice.pemakaian_kwh} total={invoice.total} />
                            <Bandingan judul="Setelah koreksi" kwh={baru?.kwh ?? null} total={baru?.total ?? null} sorot />
                        </div>
                        {dibawahLalu ? (
                            <Notice tone="danger" title="Stand lebih kecil dari stand lalu">
                                Stand yang benar tidak boleh di bawah stand bulan sebelumnya.
                            </Notice>
                        ) : (
                            selisih !== null && (
                                <Notice tone={selisih <= 0 ? 'success' : 'waiting'} title={`Tagihan ${selisih <= 0 ? 'turun' : 'naik'} ${rupiah(Math.abs(selisih))}`}>
                                    Dihitung dengan tarif yang tersalin di rekening: Rp{formatNumber(invoice.harga_per_kwh, 2)}/kWh, beban {rupiah(invoice.biaya_beban)}, PPJ{' '}
                                    {formatNumber(invoice.ppj_persen, 1)}%.
                                </Notice>
                            )
                        )}
                    </section>

                    <section className="grid gap-5 rounded-14 border border-border p-5">
                        <OptionCards
                            name="penyebab"
                            label="Penyebab"
                            value={data.penyebab}
                            onChange={(v) => setData('penyebab', v)}
                            options={penyebabOptions.map((o) => ({ value: o.value, label: o.label, hint: HINT[o.value] }))}
                        />
                        <InputError message={err.penyebab} />
                        <FormField id="alasan" label="Penjelasan" required error={err.alasan}>
                            <Textarea
                                id="alasan"
                                rows={3}
                                maxLength={1000}
                                placeholder="Contoh: digit terakhir terbaca 8, seharusnya 6. Sudah dicek ulang ke rumah pelanggan."
                                value={data.alasan}
                                onChange={(e) => setData('alasan', e.target.value)}
                                aria-invalid={!!err.alasan}
                            />
                        </FormField>
                    </section>

                    <section className="rounded-14 border border-border p-5">
                        <PhotoCapture
                            id="foto"
                            label="Foto meter terbaru"
                            nilai={data.foto}
                            fotoTersimpan={koreksi?.foto ? { url: koreksi.foto.url, diambil_at: koreksi.foto.diambil_at } : null}
                            onChange={(foto) => setData('foto', foto)}
                            posisi={geo.posisi}
                            statusLokasi={geo.status}
                            onUlangiLokasi={geo.ulangi}
                            ambangJarak={100}
                            error={err.foto}
                            petunjuk="Belum ada foto. Foto angka meter saat ini dengan jelas sebagai bukti stand yang benar."
                        />
                    </section>

                    <div className="sticky bottom-3 z-10 flex flex-col-reverse gap-2 rounded-14 border border-border bg-white/95 p-3 shadow-lg shadow-ink/5 backdrop-blur sm:flex-row sm:justify-end">
                        <Button type="button" variant="secondary" asChild>
                            <Link href={route('koreksi-rekening.index')}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing || dibawahLalu}>
                            {processing ? <LoaderCircle className="animate-spin" /> : <Send />}
                            {ubah ? 'Kirim perbaikan' : 'Kirim koreksi'}
                        </Button>
                    </div>
                </form>

                <aside className="grid content-start gap-4">
                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Rekening</h2>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="Pelanggan" nilai={pelanggan.nama} />
                            <Baris label="ID pelanggan" nilai={<span className="font-mono">{pelanggan.idpel}</span>} />
                            <Baris label="Tarif" nilai={pelanggan.tarif} />
                            <Baris label="Nomor" nilai={<span className="font-mono text-[13px]">{invoice.nomor}</span>} />
                            <Baris label="Status" nilai={invoice.status_label} />
                        </dl>
                    </div>
                    {pencatatan.foto && (
                        <div className="rounded-14 border border-border p-5">
                            <h2 className="text-section-title">Foto saat dicatat</h2>
                            <a href={pencatatan.foto.url} target="_blank" rel="noreferrer" className="mt-3 block overflow-hidden rounded-10 bg-ink">
                                <img src={pencatatan.foto.thumb} alt="Foto meter saat pencatatan" className="aspect-[4/3] w-full object-contain" />
                            </a>
                            <p className="mt-2 text-xs text-muted-ink">Bandingkan angka di foto ini dengan stand yang benar.</p>
                        </div>
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}

function Bandingan({ judul, kwh, total, sorot }: { judul: string; kwh: number | null; total: number | null; sorot?: boolean }) {
    return (
        <div className={cn('p-4', sorot ? 'bg-pln-blue-light' : 'bg-table-head')}>
            <p className="text-[13px] text-muted-ink">{judul}</p>
            <p className="mt-1 font-mono text-lg font-semibold">{kwh !== null ? `${formatNumber(kwh, 1)} kWh` : '–'}</p>
            <p className="font-mono text-sm">{rupiah(total)}</p>
        </div>
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
