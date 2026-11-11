import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, RotateCcw } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import FormField, { InputError } from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface Bobot {
    akurasi: number;
    penyelesaian: number;
    tepat_waktu: number;
    bukti_lengkap: number;
}

interface Nilai {
    bobot_kpi: Bobot;
    ambang_lonjakan_persen: number;
    ambang_jarak_m: number;
    ambang_pembinaan: number;
    ambang_dipantau: number;
    min_lembar_tusbug: number;
    ppj_persen: number;
}

type Props = PageProps<{
    pengaturan: Nilai;
    bawaan: Nilai;
    riwayat: { id: number; kunci: string | null; nilai: unknown; oleh: string | null; waktu: string }[];
}>;

const KOMPONEN: { key: keyof Bobot; label: string; rumus: string }[] = [
    { key: 'akurasi', label: 'Akurasi', rumus: 'Disetujui tanpa revisi dan tanpa koreksi salah catat ÷ terverifikasi' },
    { key: 'penyelesaian', label: 'Penyelesaian', rumus: 'Pelanggan tercatat ÷ target penugasan' },
    { key: 'tepat_waktu', label: 'Tepat waktu', rumus: 'Dikirim sebelum batas pencatatan ÷ terkirim' },
    { key: 'bukti_lengkap', label: 'Bukti lengkap', rumus: 'Ada foto dan jarak GPS dalam ambang ÷ terkirim' },
];

const LABEL_KUNCI: Record<string, string> = {
    bobot_kpi: 'Bobot skor',
    ambang_lonjakan_persen: 'Ambang lonjakan',
    ambang_jarak_m: 'Ambang jarak',
    ambang_pembinaan: 'Ambang pembinaan',
    ambang_dipantau: 'Ambang dipantau',
    min_lembar_tusbug: 'Minimal lembar TUSBUG',
    ppj_persen: 'PPJ',
};

const keForm = (n: Nilai) => ({
    bobot_kpi: {
        akurasi: String(n.bobot_kpi.akurasi),
        penyelesaian: String(n.bobot_kpi.penyelesaian),
        tepat_waktu: String(n.bobot_kpi.tepat_waktu),
        bukti_lengkap: String(n.bobot_kpi.bukti_lengkap),
    },
    ambang_lonjakan_persen: String(n.ambang_lonjakan_persen),
    ambang_jarak_m: String(n.ambang_jarak_m),
    ambang_pembinaan: String(n.ambang_pembinaan),
    ambang_dipantau: String(n.ambang_dipantau),
    min_lembar_tusbug: String(n.min_lembar_tusbug),
    ppj_persen: String(n.ppj_persen),
});

function Section({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <section className="rounded-14 border border-border">
            <header className="border-b border-line-row px-5 py-4">
                <h2 className="text-section-title">{title}</h2>
                <p className="mt-0.5 text-[13px] text-muted-ink">{description}</p>
            </header>
            <div className="grid gap-5 p-5">{children}</div>
        </section>
    );
}

function Satuan({ children, satuan }: { children: ReactNode; satuan: string }) {
    return (
        <div className="relative">
            {children}
            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[13px] text-muted-ink">{satuan}</span>
        </div>
    );
}

export default function KpiEdit() {
    const { pengaturan, bawaan, riwayat } = usePage<Props>().props;
    const { data, setData, put, processing, errors, isDirty } = useForm(keForm(pengaturan));
    const err = errors as Record<string, string>;

    const total = KOMPONEN.reduce((s, k) => s + (Number(data.bobot_kpi[k.key]) || 0), 0);
    const rumus = KOMPONEN.map((k) => `${formatNumber((Number(data.bobot_kpi[k.key]) || 0) / 100, 2)}·${k.label}`).join(' + ');

    const setBobot = (key: keyof Bobot, value: string) => setData('bobot_kpi', { ...data.bobot_kpi, [key]: value });

    const angka = (
        key: Exclude<keyof Nilai, 'bobot_kpi'>,
        label: string,
        satuan: string,
        hint: string,
        step = '1',
    ) => (
        <FormField id={key} label={label} required error={err[key]} hint={`${hint} Bawaan: ${formatNumber(bawaan[key])}${satuan === '%' ? '%' : ` ${satuan}`}.`}>
            <Satuan satuan={satuan}>
                <Input
                    id={key}
                    type="number"
                    step={step}
                    inputMode="decimal"
                    className="pr-16 font-mono"
                    value={data[key]}
                    onChange={(e) => setData(key, e.target.value)}
                    aria-invalid={!!err[key]}
                />
            </Satuan>
        </FormField>
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('pengaturan.kpi.update'), { preserveScroll: true });
    };

    return (
        <AppLayout title="Bobot dan ambang KPI">
            <PageHeader
                title="Bobot dan ambang KPI"
                description="Dipakai untuk validasi otomatis saat biller mengirim catatan dan untuk menghitung skor kinerja."
            />

            <form onSubmit={submit} noValidate className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid gap-6">
                    <Section title="Bobot skor" description="Total keempat bobot harus 100%.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            {KOMPONEN.map((k) => (
                                <FormField
                                    key={k.key}
                                    id={`bobot_${k.key}`}
                                    label={k.label}
                                    required
                                    error={err[`bobot_kpi.${k.key}`]}
                                    hint={k.rumus}
                                >
                                    <Satuan satuan="%">
                                        <Input
                                            id={`bobot_${k.key}`}
                                            type="number"
                                            min={0}
                                            max={100}
                                            inputMode="numeric"
                                            className="pr-10 font-mono"
                                            value={data.bobot_kpi[k.key]}
                                            onChange={(e) => setBobot(k.key, e.target.value)}
                                        />
                                    </Satuan>
                                </FormField>
                            ))}
                        </div>
                        <div
                            className={cn(
                                'flex flex-wrap items-center justify-between gap-2 rounded-10 px-4 py-3 text-sm',
                                total === 100 ? 'bg-success-tint text-success-ink' : 'bg-error-tint text-error-deep',
                            )}
                        >
                            <span className="font-mono text-[13px]">Skor = {rumus}</span>
                            <span className="font-semibold">Total {formatNumber(total)}%</span>
                        </div>
                        <InputError message={err.bobot_kpi} />
                    </Section>

                    <Section title="Validasi otomatis" description="Catatan yang melewati ambang ditandai sistem dan masuk antrean verifikasi.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            {angka('ambang_lonjakan_persen', 'Ambang lonjakan pemakaian', '%', 'Pemakaian di atas persentase ini dari rata-rata 3 bulan dianggap lonjakan.')}
                            {angka('ambang_jarak_m', 'Ambang jarak lokasi', 'meter', 'Jarak GPS biller ke titik pelanggan di atas nilai ini dianggap lokasi jauh.')}
                        </div>
                    </Section>

                    <Section title="Status kinerja" description="Label di tabel peringkat untuk biller dengan skor rendah.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            {angka('ambang_pembinaan', 'Perlu pembinaan bila skor di bawah', 'poin', 'Biller diberi label Perlu pembinaan.')}
                            {angka('ambang_dipantau', 'Dipantau bila skor di bawah', 'poin', 'Biller diberi label Dipantau.')}
                        </div>
                    </Section>

                    <Section title="Penagihan" description="Dipakai mulai modul invoice dan TUSBUG.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            {angka('min_lembar_tusbug', 'Minimal lembar tunggakan TUSBUG', 'lembar', 'Pelanggan dengan tunggakan sebanyak ini disarankan untuk TUSBUG.')}
                            {angka('ppj_persen', 'Pajak Penerangan Jalan', '%', 'Persentase dari tagihan listrik.', '0.1')}
                        </div>
                    </Section>
                </div>

                <aside className="grid gap-4 self-start lg:sticky lg:top-6">
                    <div className="flex flex-col gap-2">
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            Simpan pengaturan
                        </Button>
                        <Button type="button" variant="secondary" onClick={() => setData(keForm(bawaan))}>
                            <RotateCcw />
                            Isi dengan nilai bawaan
                        </Button>
                    </div>

                    <div className="rounded-14 border border-border p-5">
                        <h2 className="text-section-title">Riwayat perubahan</h2>
                        {riwayat.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-ink">Belum ada perubahan sejak nilai bawaan.</p>
                        ) : (
                            <ul className="mt-3 divide-y divide-line-row">
                                {riwayat.map((r) => (
                                    <li key={r.id} className="py-2.5 text-sm">
                                        <p className="font-medium">{LABEL_KUNCI[r.kunci ?? ''] ?? r.kunci ?? 'Pengaturan'}</p>
                                        <p className="font-mono text-xs break-all text-muted-ink">
                                            {typeof r.nilai === 'object' ? Object.values(r.nilai as object).join(' / ') : String(r.nilai ?? '–')}
                                        </p>
                                        <p className="text-xs text-muted-ink">
                                            {r.oleh ?? 'Sistem'} · {formatRelatif(r.waktu)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </aside>
            </form>
        </AppLayout>
    );
}
