import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Toaster } from '@/Components/ui/sonner';
import { useFlashToast } from '@/hooks/useFlashToast';
import type { BreadcrumbItem } from '@/types';
import Sidebar from './partials/Sidebar';
import Topbar from './partials/Topbar';

interface AppLayoutProps {
    title: string;
    /** Bila kosong, breadcrumb diambil dari menu yang aktif. */
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}

/**
 * Kerangka halaman: latar canvas padding 12, sidebar 232 px di kiri,
 * panel putih rounded-2xl ber-ring di kanan.
 */
export default function AppLayout({ title, breadcrumbs, children }: AppLayoutProps) {
    useFlashToast();

    return (
        <>
            <Head title={title} />
            <div className="min-h-dvh bg-canvas p-3 lg:flex lg:items-start lg:gap-3">
                <Sidebar />
                <main className="min-h-[calc(100dvh-24px)] min-w-0 flex-1 rounded-2xl bg-white ring-1 ring-border">
                    <Topbar breadcrumbs={breadcrumbs} />
                    <div className="px-4 py-5 sm:px-7 sm:py-6">{children}</div>
                </main>
            </div>
            <Toaster />
        </>
    );
}
