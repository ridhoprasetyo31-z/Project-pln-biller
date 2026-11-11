import { useForm, usePage } from '@inertiajs/react';
import { Camera, LoaderCircle, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import FormField, { InputError } from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import PasswordInput from '@/Components/shared/PasswordInput';
import UserAvatar from '@/Components/shared/UserAvatar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';

interface Profil {
    nip: string;
    name: string;
    email: string | null;
    phone: string | null;
    role_label: string;
    unit: string | null;
    foto_url: string | null;
    last_login_at: string | null;
}

const MAX_FOTO_BYTES = 5 * 1024 * 1024;

function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
    return (
        <section className="rounded-14 border border-border">
            <header className="border-b border-line-row px-5 py-4">
                <h2 className="text-section-title text-ink">{title}</h2>
                <p className="mt-0.5 text-[13px] text-muted-ink">{description}</p>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

function ProfilForm({ profil }: { profil: Profil }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const { data, setData, post, processing, errors, isDirty, reset, transform } = useForm<{
        name: string;
        phone: string;
        foto: File | null;
        hapus_foto: boolean;
    }>({
        name: profil.name,
        phone: profil.phone ?? '',
        foto: null,
        hapus_foto: false,
    });

    useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

    const pilihFoto = (file?: File) => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            toast.error('Foto harus berformat JPG, PNG, atau WEBP.');
            return;
        }
        if (file.size > MAX_FOTO_BYTES) {
            toast.error('Ukuran foto maksimal 5 MB.');
            return;
        }
        setPreview(URL.createObjectURL(file));
        setData((d) => ({ ...d, foto: file, hapus_foto: false }));
    };

    const hapusFoto = () => {
        setPreview(null);
        if (fileRef.current) fileRef.current.value = '';
        setData((d) => ({ ...d, foto: null, hapus_foto: true }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform((d) => ({ ...d, _method: 'patch' }));
        post(route('profil.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPreview(null);
                reset('foto', 'hapus_foto');
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    const fotoTampil = data.hapus_foto ? null : (preview ?? profil.foto_url);

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <div className="flex flex-wrap items-center gap-4">
                <UserAvatar name={data.name || profil.name} src={fotoTampil} size={72} />
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={fileRef}
                        id="foto"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(e) => pilihFoto(e.target.files?.[0])}
                    />
                    <Button type="button" variant="secondary" onClick={() => fileRef.current?.click()}>
                        <Camera />
                        {fotoTampil ? 'Ganti foto' : 'Unggah foto'}
                    </Button>
                    {fotoTampil && (
                        <Button type="button" variant="reject" onClick={hapusFoto}>
                            <Trash2 />
                            Hapus foto
                        </Button>
                    )}
                </div>
                <p className="w-full text-xs text-muted-ink">JPG, PNG, atau WEBP, maksimal 5 MB.</p>
                <InputError message={errors.foto} className="w-full" />
            </div>

            <FormField id="name" label="Nama lengkap" required error={errors.name}>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    autoComplete="name"
                    aria-invalid={!!errors.name}
                    maxLength={100}
                    required
                />
            </FormField>

            <FormField id="phone" label="Nomor HP" error={errors.phone} hint="Contoh: 081234567890">
                <Input
                    id="phone"
                    type="tel"
                    inputMode="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    autoComplete="tel"
                    aria-invalid={!!errors.phone}
                    className="font-mono"
                />
            </FormField>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Simpan profil
                </Button>
            </div>
        </form>
    );
}

function PasswordForm() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('profil.password'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (err) => {
                if (err.password) reset('password', 'password_confirmation');
                if (err.current_password) reset('current_password');
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <FormField id="current_password" label="Kata sandi lama" required error={errors.current_password}>
                <PasswordInput
                    id="current_password"
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    autoComplete="current-password"
                    aria-invalid={!!errors.current_password}
                    required
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    id="password"
                    label="Kata sandi baru"
                    required
                    error={errors.password}
                    hint="Minimal 8 karakter."
                >
                    <PasswordInput
                        id="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        aria-invalid={!!errors.password}
                        required
                    />
                </FormField>
                <FormField
                    id="password_confirmation"
                    label="Konfirmasi kata sandi baru"
                    required
                    error={errors.password_confirmation}
                >
                    <PasswordInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </FormField>
            </div>
            <div className="flex justify-end">
                <Button type="submit" variant="secondary" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Ubah kata sandi
                </Button>
            </div>
        </form>
    );
}

export default function ProfileEdit() {
    const { profil } = usePage<PageProps<{ profil: Profil }>>().props;

    const info: [string, React.ReactNode][] = [
        ['NIP', <span className="font-mono">{profil.nip}</span>],
        ['Peran', profil.role_label],
        ['Unit', profil.unit ?? 'Semua unit'],
        ['Email', profil.email ?? '–'],
        ['Terakhir masuk', formatTanggalWaktu(profil.last_login_at)],
    ];

    return (
        <AppLayout title="Profil saya" breadcrumbs={[{ label: 'Akun' }, { label: 'Profil saya' }]}>
            <PageHeader title="Profil saya" description="Perbarui data diri dan kata sandi akun Anda." />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid gap-6">
                    <Section title="Data diri" description="Nama dan nomor HP tampil untuk koordinator di unit Anda.">
                        <ProfilForm profil={profil} />
                    </Section>
                    <Section title="Ubah kata sandi" description="Masukkan kata sandi lama untuk membuat yang baru.">
                        <PasswordForm />
                    </Section>
                </div>

                <aside className="self-start rounded-14 border border-border p-5">
                    <h2 className="text-section-title text-ink">Informasi akun</h2>
                    <p className="mt-0.5 text-[13px] text-muted-ink">Diatur oleh admin unit.</p>
                    <dl className="mt-4 divide-y divide-line-row">
                        {info.map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-4 py-3 text-sm">
                                <dt className="text-muted-ink">{label}</dt>
                                <dd className="min-w-0 text-right break-words text-ink">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </aside>
            </div>
        </AppLayout>
    );
}
