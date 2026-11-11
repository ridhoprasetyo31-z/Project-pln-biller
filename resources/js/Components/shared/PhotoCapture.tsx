import { Camera, ImageOff, LoaderCircle, MapPin, MapPinOff, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import type { Posisi, StatusLokasi } from '@/hooks/useGeolocation';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { jarakMeter } from '@/lib/geo';
import { formatUkuran, kompresFoto } from '@/lib/image';
import { cn } from '@/lib/utils';

export interface FotoTerpilih {
    file: File;
    diambil_at: string;
    lat: number | null;
    lng: number | null;
}

interface PhotoCaptureProps {
    id: string;
    label: string;
    nilai: FotoTerpilih | null;
    /** Foto yang sudah tersimpan di server (draf/revisi). */
    fotoTersimpan?: { url: string; diambil_at: string | null } | null;
    onChange: (foto: FotoTerpilih) => void;
    posisi: Posisi | null;
    statusLokasi: StatusLokasi;
    onUlangiLokasi: () => void;
    titikPelanggan?: { lat: number | null; lng: number | null };
    ambangJarak: number;
    error?: string;
    disabled?: boolean;
}

const MAKS_BYTE = 5 * 1024 * 1024;

/**
 * Ambil foto dari kamera belakang, kecilkan di browser, dan tandai lokasi GPS
 * saat foto diambil. Menampilkan jarak ke titik pelanggan.
 */
export default function PhotoCapture({
    id,
    label,
    nilai,
    fotoTersimpan,
    onChange,
    posisi,
    statusLokasi,
    onUlangiLokasi,
    titikPelanggan,
    ambangJarak,
    error,
    disabled,
}: PhotoCaptureProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [memproses, setMemproses] = useState(false);

    useEffect(() => {
        if (!nilai) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(nilai.file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [nilai]);

    const pilih = async (file?: File) => {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            toast.error('Berkas harus berupa foto.');
            return;
        }
        setMemproses(true);
        try {
            const kecil = await kompresFoto(file);
            if (kecil.size > MAKS_BYTE) {
                toast.error('Foto lebih dari 5 MB. Ambil ulang dengan resolusi lebih kecil.');
                return;
            }
            onChange({
                file: kecil,
                diambil_at: new Date().toISOString(),
                lat: posisi?.lat ?? null,
                lng: posisi?.lng ?? null,
            });
        } finally {
            setMemproses(false);
            if (inputRef.current) inputRef.current.value = '';
        }
    };

    const gambar = preview ?? fotoTersimpan?.url ?? null;
    const lat = nilai ? nilai.lat : posisi?.lat ?? null;
    const lng = nilai ? nilai.lng : posisi?.lng ?? null;
    const jarak =
        lat !== null && lng !== null && titikPelanggan?.lat != null && titikPelanggan?.lng != null
            ? jarakMeter(lat, lng, titikPelanggan.lat, titikPelanggan.lng)
            : null;
    const terlaluJauh = jarak !== null && jarak > ambangJarak;

    return (
        <div className="grid gap-2">
            <p className="flex items-center gap-1 text-[13px] font-medium text-ink">
                {label}
                <span className="text-pln-red" aria-hidden>
                    *
                </span>
            </p>

            <div
                className={cn(
                    'relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-14 bg-ink',
                    error && 'ring-2 ring-pln-red',
                )}
            >
                {gambar ? (
                    <img src={gambar} alt={`Pratinjau ${label.toLowerCase()}`} className="h-full w-full object-contain" />
                ) : (
                    <div className="flex flex-col items-center gap-2 px-6 text-center text-sm text-white/70">
                        <ImageOff className="size-8" aria-hidden />
                        Belum ada foto. Foto angka meter dengan jelas, tanpa pantulan cahaya.
                    </div>
                )}
                {memproses && (
                    <div className="absolute inset-0 flex items-center justify-center bg-ink/60 text-sm text-white">
                        <LoaderCircle className="mr-2 size-4 animate-spin" />
                        Memproses foto…
                    </div>
                )}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <StatusLokasiBaris
                    status={statusLokasi}
                    posisi={posisi}
                    jarak={jarak}
                    terlaluJauh={terlaluJauh}
                    ambangJarak={ambangJarak}
                    onUlangi={onUlangiLokasi}
                />
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="sr-only"
                    disabled={disabled}
                    onChange={(e) => pilih(e.target.files?.[0])}
                />
                <Button type="button" variant="secondary" disabled={disabled || memproses} onClick={() => inputRef.current?.click()}>
                    <Camera />
                    {gambar ? 'Ganti foto' : 'Ambil foto'}
                </Button>
            </div>

            {nilai && (
                <p className="text-xs text-muted-ink">
                    Diambil {formatTanggalWaktu(nilai.diambil_at)} · {formatUkuran(nilai.file.size)}
                </p>
            )}
            {!nilai && fotoTersimpan?.diambil_at && (
                <p className="text-xs text-muted-ink">Foto tersimpan, diambil {formatTanggalWaktu(fotoTersimpan.diambil_at)}</p>
            )}
            {error && <p className="text-[13px] text-error-ink">{error}</p>}
        </div>
    );
}

function StatusLokasiBaris({
    status,
    posisi,
    jarak,
    terlaluJauh,
    ambangJarak,
    onUlangi,
}: {
    status: StatusLokasi;
    posisi: Posisi | null;
    jarak: number | null;
    terlaluJauh: boolean;
    ambangJarak: number;
    onUlangi: () => void;
}) {
    if (status === 'ditolak' || status === 'tidak_didukung' || status === 'galat') {
        return (
            <span className="inline-flex items-center gap-1.5 text-[13px] text-error-ink">
                <MapPinOff className="size-4" />
                {status === 'ditolak' ? 'Izin lokasi ditolak' : 'Lokasi tidak tersedia'}
                <button type="button" onClick={onUlangi} className="ml-1 inline-flex items-center gap-1 font-medium underline">
                    <RefreshCw className="size-3.5" />
                    Coba lagi
                </button>
            </span>
        );
    }
    if (status === 'mencari' || !posisi) {
        return (
            <span className="inline-flex items-center gap-1.5 text-[13px] text-muted-ink">
                <LoaderCircle className="size-4 animate-spin" />
                Mencari lokasi GPS…
            </span>
        );
    }
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-[13px]', terlaluJauh ? 'text-error-ink' : 'text-success-ink')}>
            <MapPin className="size-4" />
            {jarak !== null
                ? `${formatNumber(jarak)} m dari titik pelanggan${terlaluJauh ? `, lebih dari ${formatNumber(ambangJarak)} m` : ''}`
                : 'Lokasi terkunci'}
            <span className="text-muted-ink">· akurasi ±{formatNumber(posisi.akurasi)} m</span>
        </span>
    );
}
