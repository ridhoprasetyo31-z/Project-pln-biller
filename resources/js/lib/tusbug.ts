import { statusCatatan, type StatusCatatan, type StatusMeta } from '@/lib/status';

/** Status TUSBUG = status catatan + "ditugaskan" (belum dilaksanakan). */
export function statusTusbug(status: string, anomali?: string | null): StatusMeta {
    if (status === 'ditugaskan') return { label: 'Ditugaskan, belum dilaksanakan', tone: 'info' };
    return statusCatatan(status as StatusCatatan, anomali);
}
