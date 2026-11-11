<?php

/*
 * Nilai bawaan pengaturan KPI. Nilai yang disimpan di tabel `pengaturan`
 * (diubah super admin) selalu menang atas nilai di sini.
 */
return [
    // Bobot komponen skor (persen, total 100).
    'bobot_kpi' => [
        'akurasi' => 40,
        'penyelesaian' => 30,
        'tepat_waktu' => 20,
        'bukti_lengkap' => 10,
    ],

    // Pemakaian > X% dari rata-rata 3 bulan dianggap lonjakan.
    'ambang_lonjakan_persen' => 150,

    // Jarak GPS ke titik pelanggan lebih dari X meter dianggap lokasi jauh.
    'ambang_jarak_m' => 100,

    // Skor di bawah nilai ini: "Perlu pembinaan".
    'ambang_pembinaan' => 80,

    // Skor di bawah nilai ini (dan di atas ambang pembinaan): "Dipantau".
    'ambang_dipantau' => 85,

    // Minimal lembar tunggakan untuk disarankan TUSBUG.
    'min_lembar_tusbug' => 2,

    // Pajak Penerangan Jalan, persen dari tagihan listrik.
    'ppj_persen' => 3,
];
