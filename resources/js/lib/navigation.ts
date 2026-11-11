import type { Role } from '@/types';
import {
    CalendarRange,
    ClipboardCheck,
    FileChartColumn,
    FilePenLine,
    Gauge,
    History,
    LayoutDashboard,
    ListTodo,
    type LucideIcon,
    MapPinned,
    Receipt,
    RotateCcw,
    Route,
    Building2,
    SlidersHorizontal,
    TrendingUp,
    Unplug,
    UserPlus,
    UserRound,
    Users,
    Zap,
} from 'lucide-react';

export interface NavItem {
    /** Kunci unik, juga dipakai untuk navBadges. */
    id: string;
    label: string;
    /** Nama route Ziggy. Bila route belum ada, item tampil nonaktif. */
    route: string;
    /** Prefix URL untuk menandai item aktif (default: URL route). */
    match?: string;
    icon: LucideIcon;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

const PROFIL: NavItem = { id: 'profil', label: 'Profil saya', route: 'profil.edit', icon: UserRound };

const LAYANAN: NavItem[] = [
    { id: 'penagihan', label: 'Penagihan', route: 'penagihan.index', icon: Receipt },
    { id: 'koreksi', label: 'Koreksi rekening', route: 'koreksi-rekening.index', icon: FilePenLine },
    { id: 'pdil', label: 'PDIL', route: 'pdil.index', icon: UserPlus },
    { id: 'tusbug', label: 'TUSBUG', route: 'tusbug.index', icon: Unplug },
];

export const NAVIGATION: Record<Role, NavGroup[]> = {
    biller: [
        {
            label: 'Pekerjaan saya',
            items: [
                { id: 'tugas', label: 'Tugas hari ini', route: 'tugas.index', icon: ListTodo },
                { id: 'revisi', label: 'Perlu revisi', route: 'revisi.index', icon: RotateCcw },
                { id: 'riwayat', label: 'Riwayat catatan', route: 'riwayat.index', icon: History },
                { id: 'kinerja', label: 'Kinerja saya', route: 'kinerja.saya', icon: TrendingUp },
            ],
        },
        { label: 'Layanan pelanggan', items: LAYANAN },
        { label: 'Akun', items: [PROFIL] },
    ],
    koordinator_admin: [
        {
            label: 'Pantau',
            items: [
                { id: 'dashboard', label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
                { id: 'verifikasi', label: 'Verifikasi', route: 'verifikasi.index', icon: ClipboardCheck },
                { id: 'laporan', label: 'Laporan', route: 'laporan.index', icon: FileChartColumn },
            ],
        },
        {
            label: 'Data lapangan',
            items: [
                { id: 'pencatatan', label: 'Pencatatan', route: 'pencatatan.index', icon: Gauge },
                { id: 'pelanggan', label: 'Pelanggan', route: 'pelanggan.index', icon: Zap },
                { id: 'rute', label: 'Rute', route: 'rute.index', icon: Route },
                { id: 'biller', label: 'Biller', route: 'biller.index', icon: Users },
            ],
        },
        { label: 'Layanan pelanggan', items: LAYANAN },
        { label: 'Pengaturan', items: [PROFIL] },
    ],
    super_admin: [
        {
            label: 'Pantau',
            items: [
                { id: 'dashboard', label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
                { id: 'laporan', label: 'Laporan', route: 'laporan.index', icon: FileChartColumn },
            ],
        },
        {
            label: 'Data master',
            items: [
                { id: 'pengguna', label: 'Pengguna', route: 'pengaturan.users.index', icon: Users },
                { id: 'unit', label: 'Unit', route: 'pengaturan.units.index', icon: Building2 },
                { id: 'rute', label: 'Rute', route: 'pengaturan.rute.index', icon: MapPinned },
                { id: 'pelanggan', label: 'Pelanggan', route: 'pengaturan.pelanggan.index', icon: Zap },
                { id: 'tarif', label: 'Tarif', route: 'pengaturan.tarif.index', icon: Receipt },
                { id: 'periode', label: 'Periode', route: 'pengaturan.periode.index', icon: CalendarRange },
            ],
        },
        {
            label: 'Pengaturan',
            items: [
                { id: 'kpi', label: 'Bobot dan ambang KPI', route: 'pengaturan.kpi.edit', icon: SlidersHorizontal },
                PROFIL,
            ],
        },
    ],
};
