export type Role = 'biller' | 'koordinator_admin' | 'super_admin';

export interface Unit {
    id: number;
    kode: string;
    nama: string;
}

export interface AuthUser {
    id: number;
    nip: string;
    name: string;
    email: string | null;
    phone: string | null;
    role: Role;
    role_label: string;
    foto_url: string | null;
    unit: Unit | null;
}

/** Jumlah antrean per menu, kunci = id item navigasi. */
export type NavBadges = Partial<Record<string, number>>;

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    navBadges: NavBadges | null;
    flash: { success: string | null; error: string | null; impor: ImporHasil | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** Props halaman yang membutuhkan pengguna sudah masuk. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T &
    SharedProps & { auth: { user: AuthUser } };

/** Hasil paginate() Laravel yang dikirim lewat Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface Option<V extends string | number = string> {
    value: V;
    label: string;
}

export interface UnitOption {
    id: number;
    kode: string;
    nama: string;
    jenis: 'up3' | 'ulp';
}

export interface RuteOption {
    id: number;
    kode: string;
    nama: string;
    kelurahan: string;
    unit_id: number;
}

export interface BillerOption {
    id: number;
    name: string;
    nip: string;
    unit_id: number | null;
}

export interface ImporHasil {
    total: number;
    dibuat: number;
    diperbarui: number;
    errors: string[];
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}
