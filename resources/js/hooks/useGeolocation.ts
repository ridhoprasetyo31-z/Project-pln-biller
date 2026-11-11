import { useCallback, useEffect, useRef, useState } from 'react';

export interface Posisi {
    lat: number;
    lng: number;
    akurasi: number;
    waktu: number;
}

export type StatusLokasi = 'mencari' | 'ok' | 'ditolak' | 'tidak_didukung' | 'galat';

/**
 * Pantau posisi GPS perangkat selama halaman terbuka (akurasi tinggi).
 */
export function useGeolocation() {
    const [posisi, setPosisi] = useState<Posisi | null>(null);
    const [status, setStatus] = useState<StatusLokasi>('mencari');
    const watchId = useRef<number | null>(null);

    const mulai = useCallback(() => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            setStatus('tidak_didukung');
            return;
        }
        if (watchId.current !== null) navigator.geolocation.clearWatch(watchId.current);
        setStatus((s) => (s === 'ok' ? s : 'mencari'));

        watchId.current = navigator.geolocation.watchPosition(
            (pos) => {
                setPosisi({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    akurasi: pos.coords.accuracy,
                    waktu: pos.timestamp,
                });
                setStatus('ok');
            },
            (err) => setStatus(err.code === err.PERMISSION_DENIED ? 'ditolak' : 'galat'),
            { enableHighAccuracy: true, maximumAge: 15_000, timeout: 20_000 },
        );
    }, []);

    useEffect(() => {
        mulai();
        return () => {
            if (watchId.current !== null) navigator.geolocation?.clearWatch(watchId.current);
        };
    }, [mulai]);

    return { posisi, status, ulangi: mulai };
}
