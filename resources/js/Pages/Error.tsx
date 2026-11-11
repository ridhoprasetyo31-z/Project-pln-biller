import { Head, Link, usePage } from '@inertiajs/react';

import MeterDigits from '@/Components/shared/MeterDigits';
import { Button } from '@/Components/ui/button';
import type { SharedProps } from '@/types';

const PESAN: Record<number, { title: string; description: string }> = {
    403: { title: 'Akses ditolak', description: 'Anda tidak memiliki akses ke halaman ini.' },
    404: { title: 'Halaman tidak ditemukan', description: 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.' },
    500: { title: 'Terjadi kesalahan', description: 'Server mengalami kendala. Coba lagi beberapa saat lagi.' },
    503: { title: 'Sedang pemeliharaan', description: 'Aplikasi sedang diperbarui. Coba lagi beberapa saat lagi.' },
};

export default function ErrorPage({ status, message }: { status: number; message?: string | null }) {
    const { auth } = usePage<SharedProps>().props;
    const pesan = PESAN[status] ?? PESAN[500];

    return (
        <>
            <Head title={pesan.title} />
            <div className="flex min-h-dvh items-center justify-center bg-canvas p-3">
                <div className="w-full max-w-md rounded-2xl bg-white p-8 text-center ring-1 ring-border">
                    <MeterDigits value={status} minDigits={3} size="md" aria-label={`Kode ${status}`} />
                    <h1 className="mt-6 text-page-title text-ink">{pesan.title}</h1>
                    <p className="mt-2 text-sm text-muted-ink">{message || pesan.description}</p>
                    <Button asChild className="mt-6">
                        <Link href={auth.user ? route('home') : route('login')}>
                            {auth.user ? 'Kembali ke beranda' : 'Ke halaman masuk'}
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
