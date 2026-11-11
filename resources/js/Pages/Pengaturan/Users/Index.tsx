import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import PasswordInput from '@/Components/shared/PasswordInput';
import RowActions from '@/Components/shared/RowActions';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import UserAvatar from '@/Components/shared/UserAvatar';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif } from '@/lib/format';
import type { Option, PageProps, Paginated, Role, UnitOption } from '@/types';

interface UserRow {
    id: number;
    nip: string;
    name: string;
    email: string | null;
    phone: string | null;
    role: Role;
    role_label: string;
    unit_id: number | null;
    unit: string | null;
    is_active: boolean;
    foto_url: string | null;
    last_login_at: string | null;
    rute_count: number;
}

type Props = PageProps<{
    users: Paginated<UserRow>;
    filters: { q?: string; role?: string; unit_id?: string; status?: string };
    options: { roles: Option[]; units: UnitOption[] };
}>;

export default function UsersIndex() {
    const { users, filters, options, auth } = usePage<Props>().props;
    const f = useFilters({
        q: filters.q ?? '',
        role: filters.role ?? '',
        unit_id: filters.unit_id ?? '',
        status: filters.status ?? '',
    });
    const [form, setForm] = useState<{ open: boolean; user: UserRow | null }>({ open: false, user: null });
    const hapus = useConfirmAction<UserRow>();

    const columns: ColumnDef<UserRow>[] = [
        {
            header: 'Pengguna',
            cell: ({ row: { original: u } }) => (
                <div className="flex items-center gap-3">
                    <UserAvatar name={u.name} src={u.foto_url} size={36} />
                    <div className="min-w-0">
                        <p className="font-medium text-ink">{u.name}</p>
                        <p className="font-mono text-xs text-muted-ink">{u.nip}</p>
                    </div>
                </div>
            ),
        },
        { header: 'Peran', cell: ({ row }) => row.original.role_label },
        {
            header: 'Unit',
            cell: ({ row: { original: u } }) => (
                <div>
                    <p>{u.unit ?? 'Semua unit'}</p>
                    {u.role === 'biller' && (
                        <p className="text-xs text-muted-ink">
                            {u.rute_count > 0 ? `Memegang ${u.rute_count} rute` : 'Belum memegang rute'}
                        </p>
                    )}
                </div>
            ),
        },
        {
            header: 'Kontak',
            cell: ({ row: { original: u } }) => (
                <div className="text-[13px]">
                    <p className="text-ink">{u.email ?? '–'}</p>
                    <p className="font-mono text-xs text-muted-ink">{u.phone ?? ''}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row }) =>
                row.original.is_active ? (
                    <StatusChip label="Aktif" tone="success" />
                ) : (
                    <StatusChip label="Nonaktif" tone="neutral" />
                ),
        },
        {
            header: 'Terakhir masuk',
            cell: ({ row }) => (
                <span className="text-[13px] text-muted-ink">
                    {row.original.last_login_at ? formatRelatif(row.original.last_login_at) : 'Belum pernah'}
                </span>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: u } }) => (
                <RowActions
                    label={u.name}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, user: u }) },
                        {
                            label: 'Hapus',
                            icon: Trash2,
                            danger: true,
                            hidden: u.id === auth.user.id,
                            onSelect: () => hapus.ask(u),
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Pengguna">
            <PageHeader
                title="Pengguna"
                description={`Akun biller, koordinator, dan super admin. ${formatNumber(users.total)} akun ditemukan.`}
                actions={
                    <Button onClick={() => setForm({ open: true, user: null })}>
                        <Plus />
                        Tambah pengguna
                    </Button>
                }
            />

            <Toolbar>
                <SegmentedTabs
                    label="Filter peran"
                    value={f.values.role}
                    onChange={(v) => f.set('role', v)}
                    items={[{ value: '', label: 'Semua' }, ...options.roles.map((r) => ({ value: r.value, label: r.label }))]}
                />
                <SearchInput
                    className="md:w-72"
                    value={f.values.q}
                    onChange={(v) => f.set('q', v, { debounce: true })}
                    placeholder="Cari nama, NIP, atau email"
                />
                <NativeSelect
                    className="md:w-48"
                    aria-label="Filter unit"
                    value={f.values.unit_id}
                    onChange={(e) => f.set('unit_id', e.target.value)}
                >
                    <option value="">Semua unit</option>
                    {options.units.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.nama}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    className="md:w-40"
                    aria-label="Filter status"
                    value={f.values.status}
                    onChange={(e) => f.set('status', e.target.value)}
                >
                    <option value="">Semua status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </NativeSelect>
            </Toolbar>

            <DataTable
                columns={columns}
                data={users}
                getRowId={(u) => String(u.id)}
                empty="Tidak ada pengguna yang cocok dengan filter."
            />

            <UserFormDialog
                key={form.user?.id ?? 'baru'}
                open={form.open}
                user={form.user}
                options={options}
                onClose={() => setForm((s) => ({ ...s, open: false }))}
            />

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus pengguna?"
                description={
                    <>
                        Akun <strong className="text-ink">{hapus.target?.name}</strong> tidak bisa dipakai masuk lagi.
                        Riwayat catatan dan skornya tetap tersimpan.
                    </>
                }
                confirmLabel="Hapus pengguna"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.users.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function UserFormDialog({
    open,
    user,
    options,
    onClose,
}: {
    open: boolean;
    user: UserRow | null;
    options: Props['options'];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: user?.name ?? '',
        nip: user?.nip ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role: (user?.role ?? 'biller') as Role,
        unit_id: user?.unit_id ? String(user.unit_id) : '',
        password: '',
        is_active: user?.is_active ?? true,
    });

    const close = () => {
        onClose();
        clearErrors();
        if (!user) reset();
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: close };
        if (user) put(route('pengaturan.users.update', user.id), opts);
        else post(route('pengaturan.users.store'), opts);
    };

    const butuhUnit = data.role !== 'super_admin';

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => !v && close()}
            title={user ? `Ubah ${user.name}` : 'Tambah pengguna'}
            description={user ? 'Perbarui data akun.' : 'Akun baru bisa langsung dipakai masuk dengan NIP dan kata sandi awal.'}
            submitLabel={user ? 'Simpan perubahan' : 'Tambah pengguna'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="name" label="Nama lengkap" required error={errors.name}>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} aria-invalid={!!errors.name} />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="nip" label="NIP" required error={errors.nip} hint="18 digit angka.">
                    <Input
                        id="nip"
                        inputMode="numeric"
                        maxLength={18}
                        className="font-mono"
                        value={data.nip}
                        onChange={(e) => setData('nip', e.target.value.replace(/\D/g, ''))}
                        aria-invalid={!!errors.nip}
                    />
                </FormField>
                <FormField id="phone" label="Nomor HP" error={errors.phone}>
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        className="font-mono"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        aria-invalid={!!errors.phone}
                    />
                </FormField>
            </div>
            <FormField id="email" label="Email" error={errors.email}>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    aria-invalid={!!errors.email}
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="role" label="Peran" required error={errors.role}>
                    <NativeSelect
                        id="role"
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value as Role)}
                        aria-invalid={!!errors.role}
                    >
                        {options.roles.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="unit_id"
                    label="Unit"
                    required={butuhUnit}
                    error={errors.unit_id}
                    hint={butuhUnit ? undefined : 'Super admin mengelola semua unit.'}
                >
                    <NativeSelect
                        id="unit_id"
                        value={data.unit_id}
                        onChange={(e) => setData('unit_id', e.target.value)}
                        aria-invalid={!!errors.unit_id}
                    >
                        <option value="">{butuhUnit ? 'Pilih unit' : 'Tanpa unit'}</option>
                        {options.units.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            </div>
            <FormField
                id="password"
                label={user ? 'Kata sandi baru' : 'Kata sandi awal'}
                required={!user}
                error={errors.password}
                hint={user ? 'Kosongkan bila tidak ingin mengganti. Minimal 8 karakter.' : 'Minimal 8 karakter. Pengguna bisa menggantinya di halaman Profil.'}
            >
                <PasswordInput
                    id="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    aria-invalid={!!errors.password}
                />
            </FormField>
            <div className="grid gap-1.5">
                <div className="flex items-center gap-2.5">
                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                    <Label htmlFor="is_active" className="text-sm font-normal">
                        Akun aktif dan boleh masuk
                    </Label>
                </div>
                {errors.is_active && <p className="text-[13px] text-error-ink">{errors.is_active}</p>}
            </div>
        </FormDialog>
    );
}
