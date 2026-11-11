import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PasswordInput from '@/Components/shared/PasswordInput';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Login({ status }: { status?: string | null }) {
    const [showHelp, setShowHelp] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('login.store'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title="Masuk">
            <img src="/images/logo-pln.png" alt="PLN" className="h-12 w-auto self-start" />

            <h2 className="mt-8 text-page-title text-ink">Masuk ke akun Anda</h2>
            <p className="mt-1 text-sm text-muted-ink">Gunakan NIP atau email yang terdaftar di unit Anda.</p>

            {status && (
                <Notice tone="success" className="mt-6">
                    {status}
                </Notice>
            )}

            <form onSubmit={submit} className="mt-8 grid gap-5" noValidate>
                <FormField id="login" label="NIP atau email" required error={errors.login}>
                    <Input
                        id="login"
                        name="login"
                        autoComplete="username"
                        autoFocus
                        inputMode="email"
                        placeholder="Contoh: 199207202018011003"
                        value={data.login}
                        onChange={(e) => setData('login', e.target.value)}
                        aria-invalid={!!errors.login}
                        aria-describedby={errors.login ? 'login-error' : undefined}
                        required
                    />
                </FormField>

                <FormField
                    id="password"
                    label="Kata sandi"
                    required
                    error={errors.password}
                    aside={
                        <button
                            type="button"
                            onClick={() => setShowHelp((v) => !v)}
                            aria-expanded={showHelp}
                            className="text-[13px] font-medium text-pln-blue hover:underline"
                        >
                            Lupa kata sandi?
                        </button>
                    }
                >
                    <PasswordInput
                        id="password"
                        name="password"
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        aria-invalid={!!errors.password}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        required
                    />
                </FormField>

                {showHelp && (
                    <Notice tone="info" title="Atur ulang kata sandi lewat admin unit">
                        Hubungi koordinator atau admin unit Anda dan sebutkan NIP. Admin akan membuatkan kata sandi
                        sementara yang bisa Anda ganti di halaman Profil.
                    </Notice>
                )}

                <div className="flex items-center gap-2.5">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onCheckedChange={(v) => setData('remember', v === true)}
                    />
                    <Label htmlFor="remember" className="text-sm font-normal">
                        Tetap masuk di perangkat ini
                    </Label>
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Masuk
                </Button>
            </form>

            <p className="mt-6 text-center text-[13px] text-muted-ink">Akun dibuat oleh admin unit.</p>
        </AuthLayout>
    );
}
