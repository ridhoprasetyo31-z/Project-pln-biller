import { Link, usePage } from '@inertiajs/react';
import { CircleCheck, PencilLine } from 'lucide-react';

import EmptyState from '@/Components/shared/EmptyState';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';
import type { VerifikasiInfo } from '@/types/catat';

interface Item {
    kunci: string;
    jenis_label: string;
    pelanggan: { idpel: string; nama: string; alamat: string };
    konteks: string;
    ringkasan: string;
    jumlah_revisi: number;
    verifikasi: VerifikasiInfo | null;
    url: string;
}

export default function RevisiIndex() {
    const { catatan } = usePage<PageProps<{ catatan: Item[] }>>().props;

    return (
        <AppLayout title="Perlu revisi">
            <PageHeader
                title="Perlu revisi"
                description="Catatan meter, kunjungan penagihan, dan koreksi rekening yang dikembalikan koordinator. Perbaiki lalu kirim ulang."
            />

            {catatan.length === 0 ? (
                <EmptyState icon={CircleCheck} title="Tidak ada catatan yang perlu direvisi">
                    Catatan yang ditolak koordinator akan muncul di sini beserta alasannya.
                </EmptyState>
            ) : (
                <ul className="grid gap-3">
                    {catatan.map((c) => (
                        <li key={c.kunci} className="flex flex-col gap-4 rounded-14 border border-error-line bg-error-row p-5 sm:flex-row sm:items-center">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-pln-blue">{c.jenis_label}</p>
                                <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <p className="font-semibold">{c.pelanggan.nama}</p>
                                    <p className="font-mono text-xs text-muted-ink">
                                        {c.pelanggan.idpel} · {c.konteks}
                                    </p>
                                </div>
                                <p className="text-xs text-muted-ink">{c.pelanggan.alamat}</p>
                                <p className="mt-2 text-sm text-error-deep">
                                    <span className="font-semibold">{c.verifikasi?.alasan_label ?? 'Perlu diperbaiki'}</span>
                                    {c.verifikasi?.catatan ? `: ${c.verifikasi.catatan}` : ''}
                                </p>
                                <p className="mt-1 text-xs text-muted-ink">
                                    {c.ringkasan} · dikembalikan {c.verifikasi?.oleh ? `oleh ${c.verifikasi.oleh} ` : ''}
                                    {c.verifikasi ? formatTanggalWaktu(c.verifikasi.waktu) : ''} · revisi ke-{c.jumlah_revisi}
                                </p>
                            </div>
                            <Button variant="reject" asChild className="self-start sm:self-center">
                                <Link href={c.url}>
                                    <PencilLine />
                                    Perbaiki
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
