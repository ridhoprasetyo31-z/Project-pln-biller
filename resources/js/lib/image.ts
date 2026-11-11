/**
 * Kecilkan foto di browser sebelum diunggah (hemat kuota data biller di lapangan).
 * Sisi terpanjang maksimal `maxSisi` piksel, disimpan sebagai JPEG.
 */
export async function kompresFoto(file: File, maxSisi = 1600, kualitas = 0.8): Promise<File> {
    if (!file.type.startsWith('image/')) return file;

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' }).catch(() => null);
    if (!bitmap) return file;

    const skala = Math.min(1, maxSisi / Math.max(bitmap.width, bitmap.height));
    const w = Math.round(bitmap.width * skala);
    const h = Math.round(bitmap.height * skala);

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) return file;
    ctx.drawImage(bitmap, 0, 0, w, h);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', kualitas));
    if (!blob || blob.size >= file.size) return file;

    const nama = file.name.replace(/\.[^.]+$/, '') || 'foto';
    return new File([blob], `${nama}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

export function formatUkuran(byte: number): string {
    if (byte < 1024 * 1024) return `${Math.round(byte / 1024)} KB`;
    return `${(byte / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}
