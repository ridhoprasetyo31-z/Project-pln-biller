import { Link, usePage } from '@inertiajs/react';
import { MapPin, Pencil } from 'lucide-react';

import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import TabelPerbandingan, { type BarisPerbandingan } from '@/Components/shared/TabelPerbandingan';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import { urlPeta } from '@/lib/geo';
import { statusCatatan, type StatusCatatan } from '@/lib/status';
import type { PageProps } from '@/types';
import type { AnomaliItem, FotoInfo, VerifikasiInfo } from '@/types/catat';

type Props = PageProps<{
    pdil: {
        id: number;
        jenis: 'pelanggan_baru' | 'perubahan_data';
        jenis_label: string;
        status: StatusCatatan;
        nama: string;
        idpel: string | null;
        pelanggan_id: number | null;
        rute: string;
        biller: string;
        keterangan: string | null;
        latitude: number | null;
        longitude: number | null;
        waktu: string;
        jumlah_revisi: number;
        perbandingan: BarisPerbandingan[];
        anomali: AnomaliItem[];
        foto_rumah: FotoInfo | null;
        foto_meter: FotoInfo | null;
        riwayat: VerifikasiInfo[];
    };
    bisaDiubah: boolean;
}>;

export default function PdilShow() {
    const { pdil: p, bisaDiubah } = usePage<Props>().props;

    return (
        <AppLayout title="PDIL" breadcrumbs={[{ label: 'Layanan pelanggan' }, { label: 'PDIL', href: route('pdil.index') }, { label: p.nama }]}>
            <PageHeader
                title={p.nama}
                description={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        <span>{p.jenis_label}</span>·<span className="font-mono">{p.idpel ?? 'ID dibuat saat disetujui'}</span>·<span>{p.rute}</span>
                        <StatusChip {...statusCatatan(p.status, p.anomali[0]?.jenis)} />
                    </span>
                }
                actions={
                    bisaDiubah && (
                        <Button asChild>
                            <Link href={route('pdil.edit', p.id)}>
                                <Pencil />
                                Perbaiki PDIL
                            </Link>
                        </Button>
                    )
                }
            />

            {p.status === 'disetujui' && (
                <Notice tone="success" className="mb-5" title="Sudah masuk ke data pelanggan">
                    {p.jenis === 'pelanggan_baru' ? `Pelanggan baru terdaftar dengan ID ${p.idpel}.` : 'Data pelanggan sudah diperbarui sesuai usulan.'}
                </Notice>
            )}
            {p.anomali.map((a, i) => (
                <Notice key={i} tone="danger" className="mb-3" title={`Ditandai sistem: ${a.label.toLowerCase()}`}>
                    {a.keterangan}
                </Notice>
            ))}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid content-start gap-6">
                    <TabelPerbandingan baris={p.perbandingan} />
                    {p.keterangan && (
                        <section className="rounded-14 border border-border p-5 text-sm">
                            <h2 className="mb-1 text-sm font-semibold">Keterangan biller</h2>
                            <p>{p.keterangan}</p>
                        </section>
                    )}
                    {p.riwayat.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-section-title">Riwayat verifikasi</h2>
                            <ul className="grid gap-1.5 text-[13px] text-muted-ink">
                                {p.riwayat.map((v, i) => (
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
                    {[
                        ['Foto rumah', p.foto_rumah],
                        ['Foto meter', p.foto_meter],
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
                    <div className="rounded-14 border border-border p-4 text-sm">
                        <p className="text-muted-ink">
                            Diajukan {p.biller} · {formatTanggalWaktu(p.waktu)}
                        </p>
                        {p.latitude !== null && p.longitude !== null && (
                            <a href={urlPeta(p.latitude, p.longitude)} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-pln-blue hover:underline">
                                <MapPin className="size-3.5" />
                                Lihat titik lokasi
                            </a>
                        )}
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
