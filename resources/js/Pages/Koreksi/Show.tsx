import { Link, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { statusCatatan, type StatusCatatan } from '@/lib/status';
import { rupiah } from '@/lib/tagihan';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { FotoInfo, VerifikasiInfo } from '@/types/catat';

type Props = PageProps<{
    koreksi: {
        id: number;
        status: StatusCatatan;
        pelanggan: { idpel: string; nama: string; alamat: string; tarif: string };
        periode: string;
        invoice: { id: number; nomor: string; status_label: string } | null;
        pengaju: string;
        pencatat: string | null;
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
        waktu: string;
        jumlah_revisi: number;
        foto: FotoInfo | null;
        foto_asli: FotoInfo | null;
        riwayat: VerifikasiInfo[];
    };
    bisaDiubah: boolean;
}>;

export default function KoreksiShow() {
    const { koreksi: k, bisaDiubah } = usePage<Props>().props;
    const selisih = k.tagihan_baru - k.tagihan_lama;

    return (
        <AppLayout title="Koreksi rekening" breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'Koreksi rekening', href: route('koreksi-rekening.index') }, { label: k.pelanggan.idpel }]}>
            <PageHeader
                title={k.pelanggan.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span className="font-mono">{k.pelanggan.idpel}</span>·<span>{k.periode}</span>
                        <StatusChip {...statusCatatan(k.status)} />
                    </span>
                }
                actions={
                    <>
                        {k.invoice && (
                            <Button variant="secondary" asChild>
                                <Link href={route('penagihan.show', k.invoice.id)}>Lihat rekening</Link>
                            </Button>
                        )}
                        {bisaDiubah && (
                            <Button asChild>
                                <Link href={route('koreksi-rekening.edit', k.id)}>
                                    <Pencil />
                                    Perbaiki koreksi
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            {k.status === 'disetujui' && (
                <Notice tone="success" className="mb-5" title="Koreksi sudah diterapkan">
                    Rekening {k.invoice?.nomor} dan stand meter sudah diperbarui.
                    {k.kurangi_akurasi && k.pencatat ? ` Akurasi ${k.pencatat} periode ini ikut dihitung ulang.` : ''}
                </Notice>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid content-start gap-6">
                    <section className="overflow-hidden rounded-14 border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-table-head text-xs text-muted-ink">
                                <tr>
                                    <th className="px-4 py-2.5 text-left font-medium" />
                                    <th className="px-4 py-2.5 text-right font-medium">Sebelum</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Sesudah</th>
                                </tr>
                            </thead>
                            <tbody className="font-mono">
                                <Baris label="Stand" lama={formatNumber(k.stand_lama, 1)} baru={formatNumber(k.stand_baru, 1)} />
                                <Baris label="Pemakaian" lama={`${formatNumber(k.kwh_lama, 1)} kWh`} baru={`${formatNumber(k.kwh_baru, 1)} kWh`} />
                                <Baris label="Tagihan" lama={rupiah(k.tagihan_lama)} baru={rupiah(k.tagihan_baru)} />
                            </tbody>
                        </table>
                        <p className={cn('border-t border-line-row px-4 py-3 text-sm', selisih <= 0 ? 'text-success-ink' : 'text-error-ink')}>
                            Tagihan {selisih <= 0 ? 'turun' : 'naik'} {rupiah(Math.abs(selisih))}. Stand lalu {k.stand_lalu !== null ? formatNumber(k.stand_lalu, 1) : '–'}.
                        </p>
                    </section>

                    <section className="rounded-14 border border-border p-5 text-sm">
                        <h2 className="text-section-title">{k.penyebab_label}</h2>
                        {k.kurangi_akurasi && <p className="mt-1 text-[13px] text-error-ink">Mengurangi akurasi {k.pencatat ?? 'biller pencatat'} bila disetujui.</p>}
                        <p className="mt-3">{k.alasan}</p>
                        <p className="mt-3 text-xs text-muted-ink">
                            Diajukan {k.pengaju} · {formatTanggalWaktu(k.waktu)}
                            {k.jumlah_revisi > 0 ? ` · direvisi ${k.jumlah_revisi}×` : ''}
                        </p>
                    </section>

                    {k.riwayat.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-section-title">Riwayat verifikasi</h2>
                            <ul className="grid gap-1.5 text-[13px]">
                                {k.riwayat.map((v, i) => (
                                    <li key={i} className="text-muted-ink">
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
                    {[
                        ['Foto bukti koreksi', k.foto],
                        ['Foto saat dicatat', k.foto_asli],
                    ].map(([judul, foto]) =>
                        foto ? (
                            <div key={judul as string} className="rounded-14 border border-border p-4">
                                <p className="mb-2 text-sm font-medium">{judul as string}</p>
                                <a href={(foto as FotoInfo).url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-10 bg-ink">
                                    <img src={(foto as FotoInfo).thumb} alt={judul as string} className="aspect-[4/3] w-full object-contain" />
                                </a>
                            </div>
                        ) : null,
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}

function Baris({ label, lama, baru }: { label: string; lama: string; baru: string }) {
    return (
        <tr className="border-t border-line-row">
            <td className="px-4 py-3 font-sans text-muted-ink">{label}</td>
            <td className="px-4 py-3 text-right">{lama}</td>
            <td className="px-4 py-3 text-right font-semibold">{baru}</td>
        </tr>
    );
}
