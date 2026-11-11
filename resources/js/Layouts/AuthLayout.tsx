import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import MeterDigits from '@/Components/shared/MeterDigits';
import { Toaster } from '@/Components/ui/sonner';
import { useFlashToast } from '@/hooks/useFlashToast';

/**
 * Halaman masuk: kartu putih maks 1040 px di atas canvas, dua kolom.
 * Kiri panel biru dengan penghitung meter, kanan formulir.
 */
export default function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
    useFlashToast();

    return (
        <>
            <Head title={title} />
            <div className="flex min-h-dvh items-center justify-center bg-canvas p-3 sm:p-6">
                <div className="grid w-full max-w-[1040px] overflow-hidden rounded-2xl bg-white ring-1 ring-border lg:grid-cols-[1fr_1fr]">
                    <section
                        aria-label="Tentang aplikasi"
                        className="flex flex-col justify-between gap-10 bg-pln-blue p-7 text-white sm:p-10 lg:min-h-[600px] lg:p-12"
                    >
                        <p className="text-sm font-medium text-white/85">Sistem Kinerja Biller</p>

                        <div>
                            <div className="mb-8 lg:mb-10">
                                <MeterDigits
                                    value={18912.4}
                                    decimals={1}
                                    size="lg"
                                    className="hidden sm:inline-flex"
                                    aria-label="Contoh angka stand meter 18.912,4"
                                />
                                <MeterDigits
                                    value={18912.4}
                                    decimals={1}
                                    size="md"
                                    className="sm:hidden"
                                    aria-label="Contoh angka stand meter 18.912,4"
                                />
                            </div>
                            <h1 className="max-w-[420px] text-[24px] leading-tight font-semibold sm:text-[30px]">
                                Setiap angka meter tercatat benar, setiap biller dinilai adil.
                            </h1>
                            <span aria-hidden className="mt-6 block h-1.5 w-[72px] rounded-full bg-pln-yellow" />
                        </div>

                        <p className="hidden text-[13px] leading-relaxed text-white/75 sm:block">
                            Pencatatan meter, verifikasi, dan skor kinerja biller dalam satu tempat.
                        </p>
                    </section>

                    <section className="flex flex-col justify-center p-7 sm:p-10 lg:p-14">{children}</section>
                </div>
            </div>
            <Toaster />
        </>
    );
}
