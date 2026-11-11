import { usePage } from '@inertiajs/react';
import { Trophy } from 'lucide-react';

import EmptyState from '@/Components/shared/EmptyState';
import PageHeader from '@/Components/shared/PageHeader';
import StatCard from '@/Components/shared/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import type { PageProps } from '@/types';

type Props = PageProps<{
    ringkasan: { jumlah_biller: number };
    periode: { kode: string; nama: string } | null;
}>;

export default function DashboardIndex() {
    const { auth, ringkasan, periode } = usePage<Props>().props;
    const semuaUnit = auth.user.role === 'super_admin';
    const unit = semuaUnit ? 'Semua unit' : (auth.user.unit?.nama ?? 'Unit belum diatur');

    return (
        <AppLayout title="Dashboard kinerja">
            <PageHeader
                title="Dashboard kinerja"
                description={`${unit} · ${periode ? `periode ${periode.nama} berjalan` : 'belum ada periode berjalan'}`}
            />

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Biller aktif"
                    value={formatNumber(ringkasan.jumlah_biller)}
                    caption={semuaUnit ? 'Di semua unit' : `Di ${unit}`}
                />
                <StatCard label="Pelanggan tercatat" value="–" caption="Tampil setelah periode dibuka" />
                <StatCard label="Akurasi tim" value="–" caption="Dihitung dari catatan yang disetujui" />
                <StatCard label="Menunggu verifikasi" value="–" caption="Antrean catatan dari biller" />
            </div>

            <EmptyState icon={Trophy} title="Peringkat biller belum tersedia" className="mt-6">
                Skor dan peringkat dihitung dari catatan meter yang sudah disetujui. Data akan tampil di sini
                setelah biller mulai mencatat meter dan koordinator memverifikasinya.
            </EmptyState>
        </AppLayout>
    );
}
