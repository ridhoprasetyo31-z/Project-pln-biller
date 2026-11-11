import type { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}

/** Baris judul halaman: h1 + deskripsi di kiri, aksi di kanan (wrap di HP). */
export default function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div className="min-w-0">
                <h1 className="text-page-title text-ink">{title}</h1>
                {description && <p className="mt-1 text-sm text-muted-ink">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
