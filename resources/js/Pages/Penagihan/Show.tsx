import { Link, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, Download, FilePenLine, Footprints, Navigation, Pencil } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import MeterDigits from '@/Components/shared/MeterDigits';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { urlRute } from '@/lib/geo';
import { statusCatatan, type StatusCatatan } from '@/lib/status';
import { rupiah, STATUS_INVOICE, type StatusInvoice } from '@/lib/tagihan';
import type { PageProps } from '@/types';
import type { FotoInfo, VerifikasiInfo } from '@/types/catat';

type Props = PageProps<{
    invoice: {
        id: number;
        nomor: string;
        periode: string;
        periode_kode: string;
        status: StatusInvoice;
        status_label: string;
        jatuh_tempo: string;
        dibayar_at: string | null;
        pemakaian_kwh: number;
        harga_per_kwh: number;
        biaya_pemakaian: number;
        biaya_beban: number;
        pajak: number;
        ppj_persen: number;
        total: number;
        golongan: string | null;
    };
    pelanggan: { idpel: string; nama: string; alamat: string; tarif: string; nomor_meter: string; rute: string | null; latitude: number | null; longitude: number | null; status: string };
    pencatatan: { stand_lalu: number | null; stand_kini: number | null; biller: string | null; disetujui_at: string | null; foto: FotoInfo | null } | null;
    kunjungan: {
        id: number;
        biller: string;
        hasil_label: string;
        tanggal_janji: string | null;
        nominal_dibayar: number | null;
        keterangan: string | null;
        jarak_m: number | null;
        waktu: string | null;
        status: StatusCatatan;
        foto: FotoInfo | null;
        verifikasi: VerifikasiInfo | null;
        bisa_diubah: boolean;
    }[];
    koreksi: { id: number; pengaju: string; penyebab_label: string; stand_lama: number; stand_baru: number; tagihan_lama: number; tagihan_baru: number; status: StatusCatatan; waktu: string }[];
    can: { kunjungan: boolean; koreksi: boolean; lunas: boolean };
}>;

export default function PenagihanShow() {
    const { invoice, pelanggan, pencatatan, kunjungan, koreksi, can } = usePage<Props>().props;
    const [lunasOpen, setLunasOpen] = useState(false);

    return (
        <AppLayout
            title={invoice.nomor}
            breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'Penagihan', href: route('penagihan.index') }, { label: invoice.periode_kode }]}
        >
            <PageHeader
                title={pelanggan.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span className="font-mono">{invoice.nomor}</span>·<span>{invoice.periode}</span>
                        <StatusChip {...STATUS_INVOICE[invoice.status]} />
                    </span>
                }
                actions={
                    <>
                        <Button variant="secondary" asChild>
                            <a href={route('penagihan.pdf', invoice.id)}>
                                <Download />
                                Unduh PDF
                            </a>
                        </Button>
                        {can.koreksi && (
                            <Button variant="secondary" asChild>
                                <Link href={route('koreksi-rekening.create', { invoice: invoice.id })}>
                                    <FilePenLine />
                                    Ajukan koreksi
                                </Link>
                            </Button>
                        )}
                        {can.kunjungan && (
                            <Button asChild>
                                <Link href={route('kunjungan.create', invoice.id)}>
                                    <Footprints />
                                    Catat kunjungan
                                </Link>
                            </Button>
                        )}
                        {can.lunas && (
                            <Button onClick={() => setLunasOpen(true)}>
                                <CircleCheck />
                                Tandai lunas
                            </Button>
                        )}
                    </>
                }
            />

            {invoice.status === 'lunas' && invoice.dibayar_at && (
                <Notice tone="success" className="mb-5" title="Rekening sudah lunas">
                    Dibayar {formatTanggalWaktu(invoice.dibayar_at)}.
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid content-start gap-6">
                    <section className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Rincian tagihan</h2>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris
                                label={`Biaya pemakaian (${formatNumber(invoice.pemakaian_kwh, 1)} kWh × Rp${formatNumber(invoice.harga_per_kwh, 2)})`}
                                nilai={<span className="font-mono">{rupiah(invoice.biaya_pemakaian)}</span>}
                            />
                            <Baris label="Biaya beban" nilai={<span className="font-mono">{rupiah(invoice.biaya_beban)}</span>} />
                            <Baris
                                label={`Pajak Penerangan Jalan (${formatNumber(invoice.ppj_persen, 1)}%)`}
                                nilai={<span className="font-mono">{rupiah(invoice.pajak)}</span>}
                            />
                        </dl>
                        <div className="mt-2 flex items-baseline justify-between border-t-2 border-ink pt-3">
                            <span className="font-semibold">Total tagihan</span>
                            <span className="font-mono text-2xl font-semibold">{rupiah(invoice.total)}</span>
                        </div>
                        <p className="mt-2 text-[13px] text-muted-ink">Jatuh tempo {formatTanggal(invoice.jatuh_tempo)}</p>
                    </section>

                    {pencatatan && (
                        <section className="rounded-14 border border-border p-5">
                            <h2 className="text-section-title">Dasar tagihan</h2>
                            <p className="text-[13px] text-muted-ink">
                                Catatan meter oleh {pencatatan.biller ?? '–'}, disetujui {formatTanggalWaktu(pencatatan.disetujui_at)}.
                            </p>
                            <div className="mt-4 grid gap-5 sm:grid-cols-[1fr_200px]">
                                <div className="grid gap-3">
                                    <div>
                                        <p className="mb-1 text-[13px] text-muted-ink">Stand lalu</p>
                                        {pencatatan.stand_lalu !== null ? <MeterDigits value={pencatatan.stand_lalu} decimals={1} size="sm" /> : '–'}
                                    </div>
                                    <div>
                                        <p className="mb-1 text-[13px] text-muted-ink">Stand kini</p>
                                        {pencatatan.stand_kini !== null ? <MeterDigits value={pencatatan.stand_kini} decimals={1} size="sm" /> : '–'}
                                    </div>
                                </div>
                                {pencatatan.foto && (
                                    <a href={pencatatan.foto.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-10 bg-ink">
                                        <img src={pencatatan.foto.thumb} alt="Foto meter" className="aspect-[4/3] w-full object-cover" />
                                    </a>
                                )}
                            </div>
                        </section>
                    )}

                    <section>
                        <h2 className="text-section-title">Riwayat kunjungan</h2>
                        {kunjungan.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-ink">Belum ada kunjungan penagihan untuk rekening ini.</p>
                        ) : (
                            <ul className="mt-3 grid gap-3">
                                {kunjungan.map((k) => (
                                    <li key={k.id} className="flex gap-4 rounded-14 border border-border p-4">
                                        {k.foto ? (
                                            <img src={k.foto.thumb} alt="Foto bukti" className="size-16 shrink-0 rounded-10 bg-ink object-cover" />
                                        ) : (
                                            <span className="size-16 shrink-0 rounded-10 bg-table-head" />
                                        )}
                                        <div className="min-w-0 flex-1 text-sm">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">{k.hasil_label}</p>
                                                <StatusChip className="py-0.5 text-[11px]" {...statusCatatan(k.status)} />
                                            </div>
                                            <p className="text-xs text-muted-ink">
                                                {k.biller} · {formatTanggalWaktu(k.waktu)}
                                                {k.jarak_m !== null ? ` · ${formatNumber(k.jarak_m)} m dari pelanggan` : ' · tanpa GPS'}
                                            </p>
                                            {k.tanggal_janji && <p className="mt-1">Janji bayar {formatTanggal(k.tanggal_janji)}</p>}
                                            {k.nominal_dibayar !== null && <p className="mt-1">Dibayar {rupiah(k.nominal_dibayar)}</p>}
                                            {k.keterangan && <p className="mt-1 text-muted-ink">“{k.keterangan}”</p>}
                                            {k.status === 'revisi' && k.verifikasi && (
                                                <p className="mt-1 text-error-ink">
                                                    Dikembalikan: {k.verifikasi.alasan_label}
                                                    {k.verifikasi.catatan ? `, ${k.verifikasi.catatan}` : ''}
                                                </p>
                                            )}
                                        </div>
                                        {k.bisa_diubah && (
                                            <Button variant="reject" size="sm" asChild className="self-start">
                                                <Link href={route('kunjungan.edit', k.id)}>
                                                    <Pencil />
                                                    Perbaiki
                                                </Link>
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {koreksi.length > 0 && (
                        <section>
                            <h2 className="text-section-title">Koreksi rekening</h2>
                            <ul className="mt-3 grid gap-2">
                                {koreksi.map((k) => (
                                    <li key={k.id}>
                                        <Link href={route('koreksi-rekening.show', k.id)} className="flex flex-wrap items-center gap-3 rounded-14 border border-border p-4 text-sm hover:bg-table-head">
                                            <span className="flex-1">
                                                <span className="font-medium">{k.penyebab_label}</span>
                                                <span className="block text-xs text-muted-ink">
                                                    {k.pengaju} · {formatTanggalWaktu(k.waktu)}
                                                </span>
                                            </span>
                                            <span className="font-mono text-[13px]">
                                                {rupiah(k.tagihan_lama)} → {rupiah(k.tagihan_baru)}
                                            </span>
                                            <StatusChip {...statusCatatan(k.status)} />
                                        </Link>
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
                            {pelanggan.latitude !== null && pelanggan.longitude !== null && (
                                <a href={urlRute(pelanggan.latitude, pelanggan.longitude)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-[13px] font-medium text-pln-blue hover:underline">
                                    <Navigation className="size-3.5" />
                                    Petunjuk arah
                                </a>
                            )}
                        </div>
                        <dl className="mt-3 divide-y divide-line-row text-sm">
                            <Baris label="ID pelanggan" nilai={<span className="font-mono">{pelanggan.idpel}</span>} />
                            <Baris label="Tarif" nilai={pelanggan.tarif} />
                            <Baris label="Nomor meter" nilai={<span className="font-mono">{pelanggan.nomor_meter}</span>} />
                            <Baris label="Rute" nilai={pelanggan.rute ?? '–'} />
                            <Baris label="Alamat" nilai={pelanggan.alamat} />
                            <Baris label="Status sambungan" nilai={pelanggan.status} />
                        </dl>
                    </div>
                </aside>
            </div>

            {can.lunas && <LunasDialog open={lunasOpen} onOpenChange={setLunasOpen} invoiceId={invoice.id} total={invoice.total} />}
        </AppLayout>
    );
}

function LunasDialog({ open, onOpenChange, invoiceId, total }: { open: boolean; onOpenChange: (v: boolean) => void; invoiceId: number; total: number }) {
    const { data, setData, post, processing, errors } = useForm({ dibayar_at: new Date().toISOString().slice(0, 10) });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Tandai rekening lunas"
            description={`Pembayaran ${rupiah(total)} di loket, bank, atau aplikasi. Kunjungan biller dengan hasil lunas akan melunasi rekening otomatis setelah disetujui.`}
            submitLabel="Tandai lunas"
            processing={processing}
            onSubmit={() => post(route('penagihan.lunas', invoiceId), { preserveScroll: true, onSuccess: () => onOpenChange(false) })}
        >
            <FormField id="dibayar_at" label="Tanggal bayar" required error={errors.dibayar_at}>
                <Input id="dibayar_at" type="date" className="sm:w-52" value={data.dibayar_at} onChange={(e) => setData('dibayar_at', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}

function Baris({ label, nilai }: { label: string; nilai: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-2.5">
            <dt className="text-muted-ink">{label}</dt>
            <dd className="text-right break-words">{nilai}</dd>
        </div>
    );
}
