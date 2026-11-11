import type { StatusCatatan } from '@/lib/status';
import type { Option, PageProps } from '@/types';

export interface AnomaliItem {
    jenis: string;
    label: string;
    keterangan: string;
    nilai: number | null;
}

export interface FotoInfo {
    url: string;
    thumb: string;
    diambil_at: string | null;
    latitude: number | null;
    longitude: number | null;
    ukuran_kb: number;
}

export interface VerifikasiInfo {
    keputusan: 'disetujui' | 'ditolak';
    alasan: string | null;
    alasan_label: string | null;
    catatan: string | null;
    oleh: string | null;
    waktu: string;
}

export type CatatMeterProps = PageProps<{
    periode: { kode: string; nama: string; tanggal_selesai: string };
    pelanggan: {
        id: number;
        idpel: string;
        nama: string;
        alamat: string;
        kelurahan: string;
        tarif: string;
        nomor_meter: string;
        letak_meter: string | null;
        latitude: number | null;
        longitude: number | null;
        urutan: number;
        rute: string;
    };
    standLalu: number | null;
    rataTigaBulan: number | null;
    riwayat: { kode: string; label: string; nilai: number | null }[];
    catatan: {
        status: StatusCatatan;
        stand_kini: number | null;
        sisa_kwh: number | null;
        kondisi_meter: string;
        keterangan: string | null;
        latitude: number | null;
        longitude: number | null;
        jarak_m: number | null;
        dikirim_at: string | null;
        jumlah_revisi: number;
        foto: FotoInfo | null;
        anomali: AnomaliItem[];
        verifikasi: VerifikasiInfo | null;
    } | null;
    bisaDiubah: boolean;
    navigasi: {
        sebelumnya: { urutan: number; nama: string; url: string } | null;
        berikutnya: { urutan: number; nama: string; url: string } | null;
    };
    progres: { terkirim: number; target: number };
    kondisiOptions: Option[];
    ambang: { lonjakan_persen: number; jarak_m: number };
}>;
