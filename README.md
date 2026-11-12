# Sistem Kinerja Biller

Website internal untuk mencatat pekerjaan lapangan biller, memverifikasinya, lalu menghitung skor dan peringkat biller per periode. Acuan: `PRD-ringkas.md` dan `DESIGN-ringkas.md`.

Stack: Laravel 12, Inertia 2 + React 19 + TypeScript, Tailwind v4, shadcn/ui (Radix), MySQL 8.

## Menjalankan di lokal

```bash
composer install
npm install
cp .env.example .env        # sesuaikan DB_*, default database: biller_pln
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build               # atau `npm run dev` saat mengembangkan
php artisan serve
```

Buka http://127.0.0.1:8000.

## Akun demo (kata sandi: `password`)

| Peran | NIP | Email | Unit |
|---|---|---|---|
| Super admin | 198001012005011001 | admin@biller.test | UP3 Pekanbaru |
| Koordinator admin | 198503152010012002 | koordinator@biller.test | ULP Panam |
| Koordinator admin | 198709102011011004 | koordinator.rumbai@biller.test | ULP Rumbai |
| Biller (PNM-01) | 199207202018011003 | biller@biller.test | ULP Panam |

Biller lain: dewi, rudi, siti (ULP Panam) serta fajar, yuni, agus, maya (ULP Rumbai), semuanya `@biller.test`. Siti dan Maya sengaja belum memegang rute.

Data demo: 6 rute × 120 pelanggan (dengan koordinat), tarif R1/B1/S2, periode 202604–202609 (ditutup, berisi riwayat catatan yang sudah disetujui) dan 202610 (berjalan) beserta penugasannya. Di periode berjalan sudah ada ±370 catatan: sebagian disetujui, sebagian menunggu, beberapa ditandai sistem, dan 2 catatan Andi dikembalikan untuk revisi. Batas periode berjalan dibuat minimal 14 hari dari tanggal seeding.

Foto lampiran data demo adalah gambar contoh (bukan foto meter asli) di `storage/app/public/demo`.

PHP butuh ekstensi `zip` aktif (untuk impor/ekspor Excel).

## Skor kinerja

Skor sementara periode berjalan dihitung otomatis tiap malam pukul 01.00 WIB, dan skor final dihitung saat periode ditutup. Jadwal butuh scheduler Laravel aktif:

```bash
php artisan schedule:work          # saat pengembangan
php artisan kinerja:hitung         # hitung manual periode berjalan
php artisan kinerja:hitung 202609 --final
```

Invoice belum bayar yang lewat jatuh tempo berubah menjadi menunggak tiap hari pukul 00.30 WIB (job `TandaiInvoiceMenunggak`, juga lewat scheduler).

Koordinator dan super admin juga bisa menekan "Hitung ulang" di dashboard. Definisi angka mentah dan rumusnya ada di `app/Services/KpiCalculator.php`.

## Tes

Tes memakai database MySQL `biller_pln_test` (lihat `phpunit.xml`).

```bash
./vendor/bin/pest
npm run typecheck
```

## Tahap pengerjaan (PRD bagian 9)

- [x] 1. Fondasi: install, token, font, AppLayout, AuthLayout, auth, profil
- [x] 2. Data master: migrasi semua tabel, seeder demo, halaman Pengaturan
- [x] 3. Tugas, Catat pascabayar & prabayar, AnomalyDetector, Verifikasi
- [x] 4. KpiCalculator, job, tutup periode, Dashboard, Kinerja saya, ekspor
- [x] 5. Invoice, PDF, penagihan, koreksi rekening
- [x] 6. PDIL, TUSBUG, notifikasi lonceng, data lapangan koordinator

## Menu per peran

| Peran | Menu |
|---|---|
| Biller | Tugas hari ini, Perlu revisi, Riwayat catatan, Kinerja saya, Penagihan, Koreksi rekening, PDIL, TUSBUG, Profil |
| Koordinator admin | Dashboard, Verifikasi, Laporan, Pencatatan, Pelanggan, Rute, Biller (hanya baca, unitnya), Penagihan, Koreksi rekening, PDIL, TUSBUG, Profil |
| Super admin | Dashboard, Laporan, Pengguna, Unit, Rute, Pelanggan, Tarif, Periode, Bobot dan ambang KPI, Profil |

Semua route dilindungi middleware peran dan policy per model (`app/Policies`).

Menu di sidebar diambil dari `resources/js/lib/navigation.ts`. Item yang route-nya belum dibuat otomatis disembunyikan, jadi menu bertambah sendiri tiap tahap selesai.
