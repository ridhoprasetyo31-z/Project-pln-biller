<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatatMeterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Pengaturan;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RevisiController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\TugasController;
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
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');

    // Koordinator & super admin
    Route::middleware('role:koordinator_admin,super_admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });

    // Koordinator
    Route::middleware('role:koordinator_admin')->group(function () {
        Route::get('/verifikasi', [VerifikasiController::class, 'index'])->name('verifikasi.index');
        Route::post('/verifikasi/{tipe}/{id}/setujui', [VerifikasiController::class, 'setujui'])->whereNumber('id')->name('verifikasi.setujui');
        Route::post('/verifikasi/{tipe}/{id}/tolak', [VerifikasiController::class, 'tolak'])->whereNumber('id')->name('verifikasi.tolak');
    });

    // Biller
    Route::middleware('role:biller')->group(function () {
        Route::get('/tugas', [TugasController::class, 'index'])->name('tugas.index');
        Route::get('/revisi', [RevisiController::class, 'index'])->name('revisi.index');
        Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');

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
