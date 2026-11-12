<?php

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusPeriode;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActionInboxService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    $this->unit = Unit::factory()->create();
    $this->koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => $this->unit->id]);
    $this->biller = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->rute = Rute::factory()->create(['unit_id' => $this->unit->id, 'biller_id' => $this->biller->id]);

    $this->p1 = Pelanggan::factory()->create(['rute_id' => $this->rute->id, 'urutan_rute' => 1, 'latitude' => 0.4650000, 'longitude' => 101.3790000]);
    $this->p2 = Pelanggan::factory()->create(['rute_id' => $this->rute->id, 'urutan_rute' => 2]);

    // Riwayat 3 bulan disetujui untuk p1: stand 1000 → 1100 → 1200 → 1300 (rata-rata 100 kWh).
    $stand = 1000.0;
    foreach (['202607', '202608', '202609'] as $kode) {
        $lalu = Periode::factory()->create(['kode' => $kode, 'status' => StatusPeriode::Ditutup]);
        $tugas = Penugasan::create(['periode_id' => $lalu->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 2]);
        PencatatanMeter::create([
            'periode_id' => $lalu->id, 'pelanggan_id' => $this->p1->id, 'biller_id' => $this->biller->id, 'penugasan_id' => $tugas->id,
            'jenis' => 'pascabayar', 'stand_lalu' => $stand, 'stand_kini' => $stand + 100, 'pemakaian_kwh' => 100,
            'kondisi_meter' => 'normal', 'status' => StatusCatatan::Disetujui,
        ]);
        $stand += 100;
    }

    $this->periode = Periode::factory()->berjalan()->create([
        'kode' => '202610',
        'tanggal_mulai' => now()->subDays(5)->toDateString(),
        'tanggal_selesai' => now()->addDays(10)->toDateString(),
    ]);
    Penugasan::create(['periode_id' => $this->periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 2]);
});

function kirimCatatan($test, array $data = []): TestResponse
{
    return $test->actingAs($test->biller)->post(route('catat.pascabayar.store', $test->p1), array_merge([
        'aksi' => 'kirim',
        'stand_kini' => '1395,5',
        'kondisi_meter' => 'normal',
        'latitude' => 0.4650900,
        'longitude' => 101.3790000,
        'foto' => UploadedFile::fake()->image('meter.jpg', 1200, 900),
    ], $data));
}

test('halaman tugas menampilkan pelanggan di rute biller dengan stand lalu', function () {
    $this->actingAs($this->biller)->get('/tugas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tugas/Index')
            ->has('pelanggan', 2)
            ->where('pelanggan.0.stand_lalu', 1300)
            ->where('progres.terkirim', 0)
            ->where('progres.target', 2));
});

test('form catat meter menampilkan stand lalu dan rata-rata 3 bulan', function () {
    $this->actingAs($this->biller)->get(route('catat.pascabayar', $this->p1))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CatatMeter/Pascabayar')
            ->where('standLalu', 1300)
            ->where('rataTigaBulan', 100)
            ->where('bisaDiubah', true));
});

test('catatan wajar terkirim menunggu verifikasi lalu lanjut ke pelanggan berikutnya', function () {
    kirimCatatan($this)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catat.pascabayar', $this->p2));

    $catatan = PencatatanMeter::where('periode_id', $this->periode->id)->where('pelanggan_id', $this->p1->id)->sole();

    expect($catatan->status)->toBe(StatusCatatan::MenungguVerifikasi)
        ->and($catatan->stand_lalu)->toBe(1300.0)
        ->and($catatan->stand_kini)->toBe(1395.5)
        ->and($catatan->pemakaian_kwh)->toBe(95.5)
        ->and($catatan->jarak_m)->toBeLessThan(15)
        ->and($catatan->tepat_waktu)->toBeTrue()
        ->and($catatan->anomali()->count())->toBe(0)
        ->and($catatan->lampiran()->count())->toBe(1);

    Storage::disk('public')->assertExists($catatan->lampiran()->first()->path);
});

test('lonjakan pemakaian membuat catatan ditandai sistem', function () {
    kirimCatatan($this, ['stand_kini' => '1600'])->assertSessionHasNoErrors();

    $catatan = PencatatanMeter::where('periode_id', $this->periode->id)->where('pelanggan_id', $this->p1->id)->sole();
    expect($catatan->status)->toBe(StatusCatatan::Ditandai)
        ->and($catatan->anomali()->pluck('jenis')->map->value->all())->toBe(['lonjakan']);
});

test('alur lengkap: kirim, ditolak koordinator, direvisi biller, lalu disetujui', function () {
    kirimCatatan($this, ['stand_kini' => '1600']);
    $catatan = PencatatanMeter::where('periode_id', $this->periode->id)->where('pelanggan_id', $this->p1->id)->sole();
    $inbox = app(ActionInboxService::class);

    expect($inbox->badges($this->koordinator))->toBe(['verifikasi' => 1]);

    // Koordinator menolak dengan alasan.
    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.tolak', ['tipe' => 'pencatatan_meter', 'id' => $catatan->id]), [
            'alasan' => 'angka_tidak_sesuai',
            'catatan' => 'Digit terakhir 0, bukan 6.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $catatan->refresh();
    expect($catatan->status)->toBe(StatusCatatan::Revisi)
        ->and($catatan->jumlah_revisi)->toBe(1)
        ->and($catatan->verifikasi()->count())->toBe(1)
        ->and($inbox->badges($this->biller))->toBe(['revisi' => 1, 'tusbug' => 0])
        ->and($inbox->badges($this->koordinator))->toBe(['verifikasi' => 0]);

    // Biller melihat alasan di form dan mengirim ulang tanpa foto baru (foto lama dipakai).
    $this->actingAs($this->biller)->get(route('catat.pascabayar', $this->p1))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bisaDiubah', true)
            ->where('catatan.verifikasi.alasan', 'angka_tidak_sesuai'));

    kirimCatatan($this, ['stand_kini' => '1400', 'foto' => null])->assertSessionHasNoErrors();

    $catatan->refresh();
    expect($catatan->status)->toBe(StatusCatatan::MenungguVerifikasi)
        ->and($catatan->anomali()->count())->toBe(0)
        ->and($catatan->jumlah_revisi)->toBe(1);

    // Koordinator menyetujui.
    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.setujui', ['tipe' => 'pencatatan_meter', 'id' => $catatan->id]))
        ->assertSessionHasNoErrors();

    $catatan->refresh();
    expect($catatan->status)->toBe(StatusCatatan::Disetujui)
        ->and($catatan->disetujui_at)->not->toBeNull()
        ->and($catatan->verifikasi()->count())->toBe(2)
        ->and($inbox->badges($this->biller))->toBe(['revisi' => 0, 'tusbug' => 0]);

    // Catatan yang disetujui tidak bisa diubah lagi oleh biller.
    kirimCatatan($this, ['stand_kini' => '1500'])->assertSessionHasErrors('catatan');
});

test('kirim tanpa foto ditolak, simpan draf tanpa foto boleh', function () {
    kirimCatatan($this, ['foto' => null])->assertSessionHasErrors('foto');

    kirimCatatan($this, ['foto' => null, 'aksi' => 'draf'])->assertSessionHasNoErrors();
    expect(PencatatanMeter::where('periode_id', $this->periode->id)->value('status'))->toBe(StatusCatatan::Draft);
});

test('stand kini wajib saat kirim kecuali meter rusak dengan keterangan', function () {
    kirimCatatan($this, ['stand_kini' => ''])->assertSessionHasErrors('stand_kini');
    kirimCatatan($this, ['stand_kini' => '', 'kondisi_meter' => 'meter_rusak'])->assertSessionHasErrors('keterangan');
    kirimCatatan($this, ['stand_kini' => '', 'kondisi_meter' => 'meter_rusak', 'keterangan' => 'Layar meter pecah'])
        ->assertSessionHasNoErrors();
});

test('biller tidak bisa mencatat pelanggan di luar rute penugasannya', function () {
    $lain = Pelanggan::factory()->create();

    $this->actingAs($this->biller)->get(route('catat.pascabayar', $lain))->assertForbidden();
    $this->actingAs($this->biller)->post(route('catat.pascabayar.store', $lain), ['aksi' => 'draf', 'kondisi_meter' => 'normal'])->assertForbidden();
});

test('koordinator unit lain tidak bisa memverifikasi', function () {
    kirimCatatan($this);
    $catatan = PencatatanMeter::where('periode_id', $this->periode->id)->sole();
    $lain = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => Unit::factory()->create()->id]);

    $this->actingAs($lain)->post(route('verifikasi.setujui', ['tipe' => 'pencatatan_meter', 'id' => $catatan->id]))->assertForbidden();
    $this->actingAs($this->biller)->get('/verifikasi')->assertForbidden();
});

test('menolak wajib memilih alasan', function () {
    kirimCatatan($this);
    $catatan = PencatatanMeter::where('periode_id', $this->periode->id)->sole();

    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.tolak', ['tipe' => 'pencatatan_meter', 'id' => $catatan->id]), [])
        ->assertSessionHasErrors('alasan');

    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.tolak', ['tipe' => 'pencatatan_meter', 'id' => $catatan->id]), ['alasan' => 'lainnya'])
        ->assertSessionHasErrors('catatan');
});

test('halaman verifikasi menampilkan antrean dan detail catatan pertama', function () {
    kirimCatatan($this, ['stand_kini' => '1600']);

    $this->actingAs($this->koordinator)->get('/verifikasi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Verifikasi/Index')
            ->where('tab', 'ditandai')
            ->where('jumlah.ditandai', 1)
            ->has('antrean.data', 1)
            ->where('detail.pemakaian_kwh', 300)
            ->where('detail.anomali.0.jenis', 'lonjakan')
            ->where('detail.bisaDiputuskan', true)
            ->where('navBadges.verifikasi', 1));
});
