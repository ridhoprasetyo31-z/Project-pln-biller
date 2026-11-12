import { router, usePage } from '@inertiajs/react';
import { Clock, MapPinOff, RotateCcw, TrendingUp } from 'lucide-react';
import type { ReactNode } from 'react';

import EmptyState from '@/Components/shared/EmptyState';
import MeterDigits from '@/Components/shared/MeterDigits';
import PageHeader from '@/Components/shared/PageHeader';
import { ProgressBar } from '@/Components/shared/StatCard';
import StatusChip from '@/Components/shared/StatusChip';
import Tren from '@/Components/shared/Tren';
import TrendBars, { type TrendPoint } from '@/Components/shared/TrendBars';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatPercent, formatTanggalWaktu } from '@/lib/format';
import { KOMPONEN, rumusSkor, STATUS_KINERJA, type BarisPeringkat, type Bobot } from '@/lib/kinerja';
import type { Option, PageProps } from '@/types';

interface Pengurang {
    id: number;
    nama: string;
    idpel: string;
    jumlah_revisi: number;
    jarak_m: number | null;
    dikirim_at: string | null;
}

type Props = PageProps<{
    periodeOptions: Option[];
    periode: { kode: string; nama: string; final: boolean; sebelum: string | null } | null;
    skor?: BarisPeringkat | null;
    jumlahBiller?: number;
    rataUnit?: number;
    tren?: TrendPoint[];
    trenUnit?: TrendPoint[];
    bobot?: Bobot;
    ambang?: { pembinaan: number; dipantau: number; jarak_m: number };
    pengurang?: { revisi: Pengurang[]; terlambat: Pengurang[]; jauh: Pengurang[] };
}>;

const RINCIAN: Record<(typeof KOMPONEN)[number]['key'], (s: BarisPeringkat) => string> = {
    akurasi: (s) => `${formatNumber(s.disetujui_tanpa_revisi)} dari ${formatNumber(s.terverifikasi)} catatan terverifikasi disetujui tanpa revisi`,
    penyelesaian: (s) => `${formatNumber(s.tercatat)} dari target ${formatNumber(s.target)} pelanggan sudah disetujui`,
    ketepatan_waktu: (s) => `${formatNumber(s.jumlah_tepat_waktu)} dari ${formatNumber(s.tercatat)} dikirim sebelum batas`,
    bukti_lengkap: (s) => `${formatNumber(s.jumlah_bukti_lengkap)} dari ${formatNumber(s.tercatat)} punya foto dan lokasi dalam ambang`,
};

export default function KinerjaSaya() {
    const { periodeOptions, periode, skor, jumlahBiller, rataUnit, tren, bobot, ambang, pengurang } = usePage<Props>().props;

    return (
        <AppLayout title="Kinerja saya">
            <PageHeader
                title="Kinerja saya"
                description={periode ? `${periode.nama} · ${periode.final ? 'skor final' : 'skor sementara, periode masih berjalan'}` : 'Skor kinerja Anda per periode'}
                actions={
                    periodeOptions.length > 0 && (
                        <NativeSelect
                            className="w-56"
                            aria-label="Periode"
                            value={periode?.kode ?? ''}
                            onChange={(e) => router.get(route('kinerja.saya'), { periode: e.target.value }, { preserveScroll: true })}
                        >
                            {periodeOptions.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {p.label}
                                </option>
                            ))}
                        </NativeSelect>
                    )
                }
            />

            {!periode || !skor ? (
                <EmptyState icon={TrendingUp} title="Skor Anda belum tersedia">
                    Skor dihitung otomatis tiap malam dan saat periode ditutup, dari catatan meter yang sudah disetujui koordinator.
                </EmptyState>
            ) : (
                <>
                    <div className="mb-6 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                        <section className="rounded-14 border border-border p-5">
                            <p className="text-[13px] text-muted-ink">Skor {periode.nama}</p>
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <MeterDigits value={skor.skor} decimals={1} size="lg" className="hidden sm:inline-flex" />
                                <MeterDigits value={skor.skor} decimals={1} size="md" className="sm:hidden" />
                                {skor.status && <StatusChip {...STATUS_KINERJA[skor.status]} />}
                            </div>
                            <dl className="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-[13px] text-muted-ink">Peringkat di unit</dt>
                                    <dd className="text-lg font-semibold">
                                        {skor.peringkat} <span className="text-sm font-normal text-muted-ink">dari {jumlahBiller}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[13px] text-muted-ink">Rata-rata unit</dt>
                                    <dd className="font-mono text-lg font-semibold">{formatNumber(rataUnit ?? 0, 1)}</dd>
                                </div>
                                <div>
                                    <dt className="text-[13px] text-muted-ink">Dibanding {periode.sebelum?.split(' ')[0] ?? 'bulan lalu'}</dt>
                                    <dd className="text-lg">
                                        <Tren nilai={skor.tren} />
                                    </dd>
                                </div>
                            </dl>
                            {ambang && periode.final && (
                                <p className="mt-4 text-xs text-muted-ink">
                                    Di bawah {formatNumber(ambang.pembinaan)} diberi label Perlu pembinaan, di bawah {formatNumber(ambang.dipantau)} Dipantau.
                                </p>
                            )}
                        </section>

                        <section className="rounded-14 border border-border p-5">
                            <h2 className="text-section-title">Skor saya 6 periode</h2>
                            <p className="mb-3 text-[13px] text-muted-ink">Arahkan kursor ke batang untuk melihat nilainya.</p>
                            <TrendBars data={tren ?? []} sorot={periode.kode} tinggi={190} />
                        </section>
                    </div>

                    <section className="mb-6">
                        <h2 className="text-section-title">Komponen skor</h2>
                        <p className="mb-3 font-mono text-xs text-muted-ink">{bobot && rumusSkor(bobot)}</p>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {KOMPONEN.map((k) => {
                                const nilai = skor[k.key];
                                const b = bobot?.[k.bobot] ?? 0;
                                return (
                                    <div key={k.key} className="rounded-14 border border-border p-4">
                                        <div className="flex items-baseline justify-between">
                                            <p className="text-[13px] text-muted-ink">{k.label}</p>
                                            <p className="text-xs text-muted-ink">bobot {b}%</p>
                                        </div>
                                        <p className="mt-2 text-[28px] leading-none font-semibold">{formatPercent(nilai)}</p>
                                        <ProgressBar className="mt-3" value={nilai} />
                                        <p className="mt-2 text-xs text-muted-ink">{RINCIAN[k.key](skor)}</p>
                                        <p className="mt-1 text-xs text-ink">Menyumbang {formatNumber((nilai * b) / 100, 2)} poin</p>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {pengurang && (
                        <section>
                            <h2 className="text-section-title">Yang mengurangi skor</h2>
                            <p className="mb-3 text-[13px] text-muted-ink">Catatan periode ini yang membuat komponen di atas tidak 100%.</p>
                            <div className="grid gap-3 lg:grid-cols-3">
                                <DaftarPengurang
                                    ikon={<RotateCcw className="size-4" />}
                                    judul="Pernah dikembalikan"
                                    kosong="Tidak ada catatan yang direvisi."
                                    items={pengurang.revisi}
                                    ket={(c) => `Direvisi ${c.jumlah_revisi}×`}
                                />
                                <DaftarPengurang
                                    ikon={<Clock className="size-4" />}
                                    judul="Dikirim lewat batas"
                                    kosong="Semua catatan dikirim tepat waktu."
                                    items={pengurang.terlambat}
                                    ket={(c) => formatTanggalWaktu(c.dikirim_at)}
                                />
                                <DaftarPengurang
                                    ikon={<MapPinOff className="size-4" />}
                                    judul={`Lokasi lebih dari ${formatNumber(ambang?.jarak_m ?? 100)} m`}
                                    kosong="Semua catatan dalam ambang jarak."
                                    items={pengurang.jauh}
                                    ket={(c) => (c.jarak_m === null ? 'Tanpa GPS' : `${formatNumber(c.jarak_m)} m dari titik pelanggan`)}
                                />
                            </div>
                        </section>
                    )}
                </>
            )}
        </AppLayout>
    );
}

function DaftarPengurang({
    ikon,
    judul,
    kosong,
    items,
    ket,
}: {
    ikon: ReactNode;
    judul: string;
    kosong: string;
    items: Pengurang[];
    ket: (c: Pengurang) => string;
}) {
    return (
        <div className="rounded-14 border border-border p-4">
            <p className="flex items-center gap-2 text-sm font-semibold">
                <span className={items.length ? 'text-pln-red' : 'text-success-ink'}>{ikon}</span>
                {judul}
                <span className="ml-auto text-xs font-normal text-muted-ink">{items.length >= 10 ? '10+' : items.length}</span>
            </p>
            {items.length === 0 ? (
                <p className="mt-3 text-[13px] text-muted-ink">{kosong}</p>
            ) : (
                <ul className="mt-2 divide-y divide-line-row">
                    {items.map((c) => (
                        <li key={c.id} className="py-2">
                            <p className="truncate text-[13px] font-medium">{c.nama}</p>
                            <p className="text-xs text-muted-ink">
                                <span className="font-mono">{c.idpel}</span> · {ket(c)}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
