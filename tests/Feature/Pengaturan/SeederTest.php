<?php

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Models\Pelanggan;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\Tarif;
use App\Models\Unit;
use App\Models\User;

test('seeder demo membuat data sesuai spesifikasi tahap 2', function () {
    $this->seed();

    expect(Unit::where('jenis', 'up3')->count())->toBe(1)
        ->and(Unit::where('jenis', 'ulp')->count())->toBe(2)
        ->and(Rute::count())->toBe(6)
        ->and(User::where('role', Role::Biller)->count())->toBe(8)
        ->and(User::where('role', Role::KoordinatorAdmin)->count())->toBe(2)
        ->and(User::where('role', Role::SuperAdmin)->count())->toBe(1)
        ->and(Pelanggan::count())->toBe(720)
        ->and(Pelanggan::whereNull('latitude')->count())->toBe(0)
        ->and(Tarif::pluck('golongan')->unique()->sort()->values()->all())->toBe(['B1', 'R1', 'S2']);

    foreach (Rute::withCount('pelanggan')->get() as $rute) {
        expect($rute->pelanggan_count)->toBe(120)
            ->and($rute->biller_id)->not->toBeNull();
    }

    expect(Periode::where('kode', '202609')->value('status'))->toBe(StatusPeriode::Ditutup)
        ->and(Periode::where('kode', '202610')->value('status'))->toBe(StatusPeriode::Berjalan)
        ->and(Penugasan::count())->toBe(42);

    // MySQL menyusun ulang urutan kunci JSON, jadi bandingkan tanpa urutan.
    expect(Pengaturan::ambil('bobot_kpi'))->toEqual(['akurasi' => 40, 'penyelesaian' => 30, 'tepat_waktu' => 20, 'bukti_lengkap' => 10])
        ->and(Pengaturan::ambil('ambang_lonjakan_persen'))->toBe(150)
        ->and(Pengaturan::ambil('ambang_jarak_m'))->toBe(100)
        ->and(Pengaturan::ambil('ambang_pembinaan'))->toBe(80)
        ->and(Pengaturan::ambil('min_lembar_tusbug'))->toBe(2);
});

test('seeder bisa dijalankan ulang tanpa duplikasi', function () {
    $this->seed();
    $this->seed();

    expect(User::count())->toBe(11)
        ->and(Pelanggan::count())->toBe(720)
        ->and(Penugasan::count())->toBe(42);
});
