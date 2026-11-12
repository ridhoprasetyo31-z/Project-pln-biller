<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatatMeterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataLapanganController;
use App\Http\Controllers\KinerjaSayaController;
use App\Http\Controllers\KoreksiRekeningController;
use App\Http\Controllers\KunjunganController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PdilController;
use App\Http\Controllers\PenagihanController;
use App\Http\Controllers\Pengaturan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RevisiController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\TugasController;
use App\Http\Controllers\TusbugController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route(request()->user()->role->homeRoute()))->name('home');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Semua peran
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/{id}/buka', [NotifikasiController::class, 'buka'])->name('notifikasi.buka');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');

    // Koordinator & super admin
    Route::middleware('role:koordinator_admin,super_admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/kinerja/hitung', [DashboardController::class, 'hitungUlang'])->name('kinerja.hitung');

        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/{kode}/excel', [LaporanController::class, 'excel'])->where('kode', '\d{6}')->name('laporan.excel');
        Route::get('/laporan/{kode}/pdf', [LaporanController::class, 'pdf'])->where('kode', '\d{6}')->name('laporan.pdf');
    });

    // Koordinator
    Route::middleware('role:koordinator_admin')->group(function () {
        Route::get('/verifikasi', [VerifikasiController::class, 'index'])->name('verifikasi.index');
        Route::post('/verifikasi/{tipe}/{id}/setujui', [VerifikasiController::class, 'setujui'])->whereNumber('id')->name('verifikasi.setujui');
        Route::post('/verifikasi/{tipe}/{id}/tolak', [VerifikasiController::class, 'tolak'])->whereNumber('id')->name('verifikasi.tolak');
    });

    // Penagihan & koreksi rekening: biller dan koordinator (otorisasi rinci di policy)
    Route::middleware('role:biller,koordinator_admin')->group(function () {
        Route::get('/penagihan', [PenagihanController::class, 'index'])->name('penagihan.index');
        Route::get('/penagihan/{invoice}', [PenagihanController::class, 'show'])->whereNumber('invoice')->name('penagihan.show');
        Route::get('/penagihan/{invoice}/pdf', [PenagihanController::class, 'pdf'])->whereNumber('invoice')->name('penagihan.pdf');

        Route::get('/koreksi-rekening', [KoreksiRekeningController::class, 'index'])->name('koreksi-rekening.index');
        Route::get('/koreksi-rekening/{koreksi}', [KoreksiRekeningController::class, 'show'])->whereNumber('koreksi')->name('koreksi-rekening.show');

        Route::get('/pdil', [PdilController::class, 'index'])->name('pdil.index');
        Route::get('/pdil/{pdil}', [PdilController::class, 'show'])->whereNumber('pdil')->name('pdil.show');

        Route::get('/tusbug', [TusbugController::class, 'index'])->name('tusbug.index');
        Route::get('/tusbug/{tusbug}', [TusbugController::class, 'show'])->whereNumber('tusbug')->name('tusbug.show');
    });

    Route::middleware('role:koordinator_admin')->group(function () {
        Route::post('/penagihan/{invoice}/lunas', [PenagihanController::class, 'lunas'])->whereNumber('invoice')->name('penagihan.lunas');
        Route::post('/penagihan/susulan', [PenagihanController::class, 'susulan'])->name('penagihan.susulan');

        Route::post('/tusbug', [TusbugController::class, 'store'])->name('tusbug.store');
        Route::delete('/tusbug/{tusbug}', [TusbugController::class, 'destroy'])->whereNumber('tusbug')->name('tusbug.destroy');

        // Data lapangan unit (hanya baca)
        Route::get('/data/pencatatan', [DataLapanganController::class, 'pencatatan'])->name('pencatatan.index');
        Route::get('/data/pelanggan', [DataLapanganController::class, 'pelanggan'])->name('pelanggan.index');
        Route::get('/data/rute', [DataLapanganController::class, 'rute'])->name('rute.index');
        Route::get('/data/biller', [DataLapanganController::class, 'biller'])->name('biller.index');
    });

    // Biller
    Route::middleware('role:biller')->group(function () {
        Route::get('/penagihan/{invoice}/kunjungan', [KunjunganController::class, 'create'])->whereNumber('invoice')->name('kunjungan.create');
        Route::post('/penagihan/{invoice}/kunjungan', [KunjunganController::class, 'store'])->whereNumber('invoice')->name('kunjungan.store');
        Route::get('/kunjungan/{kunjungan}/ubah', [KunjunganController::class, 'edit'])->name('kunjungan.edit');
        Route::put('/kunjungan/{kunjungan}', [KunjunganController::class, 'update'])->name('kunjungan.update');

        Route::get('/koreksi-rekening/ajukan', [KoreksiRekeningController::class, 'create'])->name('koreksi-rekening.create');
        Route::post('/koreksi-rekening', [KoreksiRekeningController::class, 'store'])->name('koreksi-rekening.store');
        Route::get('/koreksi-rekening/{koreksi}/ubah', [KoreksiRekeningController::class, 'edit'])->name('koreksi-rekening.edit');
        Route::put('/koreksi-rekening/{koreksi}', [KoreksiRekeningController::class, 'update'])->name('koreksi-rekening.update');

        Route::get('/pdil/ajukan', [PdilController::class, 'create'])->name('pdil.create');
        Route::post('/pdil', [PdilController::class, 'store'])->name('pdil.store');
        Route::get('/pdil/{pdil}/ubah', [PdilController::class, 'edit'])->name('pdil.edit');
        Route::put('/pdil/{pdil}', [PdilController::class, 'update'])->name('pdil.update');

        Route::post('/tusbug/{tusbug}/laksanakan', [TusbugController::class, 'laksanakan'])->name('tusbug.laksanakan');

        Route::get('/tugas', [TugasController::class, 'index'])->name('tugas.index');
        Route::get('/revisi', [RevisiController::class, 'index'])->name('revisi.index');
        Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
        Route::get('/kinerja-saya', KinerjaSayaController::class)->name('kinerja.saya');

        foreach (['pascabayar', 'prabayar'] as $jenis) {
            Route::get("/catat-meter/{pelanggan}/{$jenis}", [CatatMeterController::class, 'show'])
                ->defaults('jenis', $jenis)
                ->name("catat.{$jenis}");
            Route::post("/catat-meter/{pelanggan}/{$jenis}", [CatatMeterController::class, 'store'])
                ->name("catat.{$jenis}.store");
        }
    });

    // Super admin: data master & pengaturan KPI
    Route::middleware('role:super_admin')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::resource('users', Pengaturan\UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('units', Pengaturan\UnitController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('rute', Pengaturan\RuteController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('rute/{rute}/biller', [Pengaturan\RuteController::class, 'updateBiller'])->name('rute.biller');

        Route::get('pelanggan/template', [Pengaturan\PelangganController::class, 'template'])->name('pelanggan.template');
        Route::post('pelanggan/impor', [Pengaturan\PelangganController::class, 'impor'])->name('pelanggan.impor');
        Route::resource('pelanggan', Pengaturan\PelangganController::class)->except(['show']);

        Route::resource('tarif', Pengaturan\TarifController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('periode', Pengaturan\PeriodeController::class)->except(['create', 'edit']);
        Route::post('periode/{periode}/mulai', [Pengaturan\PeriodeController::class, 'mulai'])->name('periode.mulai');
        Route::post('periode/{periode}/tutup', [Pengaturan\PeriodeController::class, 'tutup'])->name('periode.tutup');
        Route::post('periode/{periode}/penugasan/sinkron', [Pengaturan\PenugasanController::class, 'sinkron'])->name('penugasan.sinkron');
        Route::post('periode/{periode}/penugasan', [Pengaturan\PenugasanController::class, 'store'])->name('penugasan.store');
        Route::put('penugasan/{penugasan}', [Pengaturan\PenugasanController::class, 'update'])->name('penugasan.update');
        Route::delete('penugasan/{penugasan}', [Pengaturan\PenugasanController::class, 'destroy'])->name('penugasan.destroy');

        Route::get('kpi', [Pengaturan\KpiController::class, 'edit'])->name('kpi.edit');
        Route::put('kpi', [Pengaturan\KpiController::class, 'update'])->name('kpi.update');
    });
});
