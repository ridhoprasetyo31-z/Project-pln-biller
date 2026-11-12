<?php

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Enums\StatusTusbug;
use App\Models\Invoice;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\Tarif;
use App\Models\Tusbug;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CatatanDikembalikan;
use App\Notifications\TugasTusbugBaru;
use App\Services\VerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    Tarif::factory()->create(['golongan' => 'R1']);
    Tarif::factory()->create(['golongan' => 'B1']);
    $this->unit = Unit::factory()->create(['kode' => '18110']);
    $this->koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => $this->unit->id]);
    $this->biller = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->rute = Rute::factory()->create(['unit_id' => $this->unit->id, 'biller_id' => $this->biller->id]);
    $this->pelanggan = Pelanggan::factory()->create([
        'rute_id' => $this->rute->id, 'urutan_rute' => 5, 'daya_va' => 900, 'nomor_meter' => '45000000001',
        'latitude' => 0.465, 'longitude' => 101.379,
    ]);
});

/** Buat n invoice menunggak untuk pelanggan (catatan disetujui tiap periode). */
function tunggakan(object $t, Pelanggan $p, int $lembar): void
{
    foreach (range(1, $lembar) as $i) {
        $kode = '2026'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $periode = Periode::where('kode', $kode)->first() ?? Periode::factory()->create(['kode' => $kode, 'status' => 'ditutup']);
        $tugas = Penugasan::firstOrCreate(
            ['periode_id' => $periode->id, 'rute_id' => $p->rute_id],
            ['biller_id' => $t->biller->id, 'target_pelanggan' => 1],
        );
        $c = PencatatanMeter::create([
            'periode_id' => $periode->id, 'pelanggan_id' => $p->id, 'biller_id' => $t->biller->id, 'penugasan_id' => $tugas->id,
            'jenis' => 'pascabayar', 'stand_lalu' => 100 * $i, 'stand_kini' => 100 * $i + 100, 'pemakaian_kwh' => 100,
            'kondisi_meter' => 'normal', 'status' => StatusCatatan::Disetujui,
        ]);
        Invoice::create([
            'nomor' => "INV/{$periode->kode}/{$p->idpel}", 'pelanggan_id' => $p->id, 'periode_id' => $periode->id, 'pencatatan_meter_id' => $c->id,
            'tarif_id' => Tarif::first()->id, 'pemakaian_kwh' => 100, 'harga_per_kwh' => 1444.70, 'biaya_beban' => 0, 'pajak' => 4334,
            'total' => 148804, 'jatuh_tempo' => now()->subMonth()->toDateString(), 'status' => StatusInvoice::Menunggak,
        ]);
    }
}

function dataPdil(array $ubah = []): array
{
    return array_merge([
        'nama' => 'Warung Bu Sari', 'alamat' => 'Jl. Purwodadi No. 62', 'kelurahan' => 'Tuah Karya',
        'jenis_pelanggan' => 'pascabayar', 'golongan_tarif' => 'B1', 'daya_va' => 2200, 'nomor_meter' => '45119990001',
        'letak_meter' => 'Teras', 'latitude' => 0.4651, 'longitude' => 101.3791,
        'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'), 'foto_meter' => UploadedFile::fake()->image('meter.jpg'),
    ], $ubah);
}

// ---------------------------------------------------------------- PDIL

test('PDIL pelanggan baru yang disetujui masuk ke data pelanggan', function () {
    $this->actingAs($this->biller)->post(route('pdil.store'), dataPdil(['jenis' => 'pelanggan_baru', 'rute_id' => $this->rute->id]))
        ->assertSessionHasNoErrors();

    $pdil = Pdil::sole();
    expect($pdil->status)->toBe(StatusCatatan::MenungguVerifikasi)->and($pdil->lampiran()->count())->toBe(2);

    $this->actingAs($this->koordinator)->post(route('verifikasi.setujui', ['tipe' => 'pdil', 'id' => $pdil->id]))->assertSessionHasNoErrors();

    $baru = Pelanggan::where('nomor_meter', '45119990001')->sole();
    expect($baru->nama)->toBe('Warung Bu Sari')
        ->and($baru->rute_id)->toBe($this->rute->id)
        ->and($baru->urutan_rute)->toBe(6)
        ->and($baru->golongan_tarif)->toBe('B1')
        ->and($baru->status)->toBe(StatusPelanggan::Aktif)
        ->and($baru->idpel)->toMatch('/^181109\d{6}$/')
        ->and($pdil->fresh()->pelanggan_id)->toBe($baru->id);
});

test('PDIL perubahan data menyimpan nilai lama dan memperbarui pelanggan saat disetujui', function () {
    $lama = $this->pelanggan->only(['nama', 'alamat', 'kelurahan', 'golongan_tarif', 'nomor_meter', 'letak_meter', 'latitude', 'longitude']);
    $this->actingAs($this->biller)->post(route('pdil.store'), dataPdil($lama + [
        'jenis' => 'perubahan_data', 'pelanggan_id' => $this->pelanggan->id, 'jenis_pelanggan' => 'pascabayar', 'daya_va' => 1300,
    ]))->assertSessionHasNoErrors();

    $pdil = Pdil::sole();
    expect($pdil->data_lama['daya_va'])->toBe(900)->and($pdil->daya_va)->toBe(1300);

    app(VerificationService::class)->setujui($pdil, $this->koordinator);
    expect($this->pelanggan->fresh()->daya_va)->toBe(1300);
});

test('PDIL perubahan tanpa perubahan atau dengan nomor meter milik pelanggan lain ditolak', function () {
    $sama = $this->pelanggan->only(['nama', 'alamat', 'kelurahan', 'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude']);
    $this->actingAs($this->biller)->post(route('pdil.store'), dataPdil($sama + [
        'jenis' => 'perubahan_data', 'pelanggan_id' => $this->pelanggan->id, 'jenis_pelanggan' => 'pascabayar',
    ]))->assertSessionHasErrors('nama');

    $this->actingAs($this->biller)->post(route('pdil.store'), dataPdil(['jenis' => 'pelanggan_baru', 'rute_id' => $this->rute->id, 'nomor_meter' => '45000000001']))
        ->assertSessionHasErrors('nomor_meter');
});

test('PDIL yang ditolak mengirim notifikasi ke biller', function () {
    Notification::fake();
    $this->actingAs($this->biller)->post(route('pdil.store'), dataPdil(['jenis' => 'pelanggan_baru', 'rute_id' => $this->rute->id]));

    $this->actingAs($this->koordinator)->post(route('verifikasi.tolak', ['tipe' => 'pdil', 'id' => Pdil::sole()->id]), ['alasan' => 'data_tidak_lengkap'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->biller, CatatanDikembalikan::class, fn ($n) => $n->jenisLabel === 'PDIL');
    expect(Pdil::sole()->status)->toBe(StatusCatatan::Revisi);
});

// ---------------------------------------------------------------- TUSBUG

test('saran TUSBUG hanya pelanggan dengan tunggakan minimal 2 lembar', function () {
    tunggakan($this, $this->pelanggan, 2);
    $satuLembar = Pelanggan::factory()->create(['rute_id' => $this->rute->id]);
    tunggakan($this, $satuLembar, 1);

    $this->actingAs($this->koordinator)->get('/tusbug')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tusbug/Index')->has('saran', 1)->where('saran.0.pelanggan_id', $this->pelanggan->id)->where('saran.0.lembar', 2));
});

test('menugaskan TUSBUG mengirim notifikasi ke biller dan mengeluarkan pelanggan dari saran', function () {
    Notification::fake();
    tunggakan($this, $this->pelanggan, 3);

    $this->actingAs($this->koordinator)->post(route('tusbug.store'), [
        'pelanggan_ids' => [$this->pelanggan->id],
        'tanggal_rencana' => now()->addDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    $t = Tusbug::sole();
    expect($t->status)->toBe(StatusTusbug::Ditugaskan)
        ->and($t->biller_id)->toBe($this->biller->id)
        ->and($t->jumlah_lembar)->toBe(3)
        ->and($t->invoice()->count())->toBe(3)
        ->and($t->nomor_surat)->toStartWith('TSB/18110/');

    Notification::assertSentTo($this->biller, TugasTusbugBaru::class);
    $this->actingAs($this->koordinator)->get('/tusbug')->assertInertia(fn (Assert $page) => $page->has('saran', 0)->has('berjalan', 1));
});

test('TUSBUG diputus yang disetujui membuat pelanggan putus sementara, lalu aktif lagi setelah lunas', function () {
    tunggakan($this, $this->pelanggan, 2);
    $this->actingAs($this->koordinator)->post(route('tusbug.store'), ['pelanggan_ids' => [$this->pelanggan->id], 'tanggal_rencana' => now()->toDateString()]);
    $t = Tusbug::sole();

    $this->actingAs($this->biller)->post(route('tusbug.laksanakan', $t), [
        'hasil' => 'diputus', 'latitude' => 0.46505, 'longitude' => 101.379,
        'foto_sebelum' => UploadedFile::fake()->image('a.jpg'), 'foto_sesudah' => UploadedFile::fake()->image('b.jpg'),
    ])->assertSessionHasNoErrors();
    expect($t->fresh()->status)->toBe(StatusTusbug::MenungguVerifikasi);

    $this->actingAs($this->koordinator)->post(route('verifikasi.setujui', ['tipe' => 'tusbug', 'id' => $t->id]))->assertSessionHasNoErrors();
    expect($this->pelanggan->fresh()->status)->toBe(StatusPelanggan::PutusSementara);

    // Pelanggan melunasi semua tunggakan di loket → kembali aktif.
    foreach (Invoice::where('pelanggan_id', $this->pelanggan->id)->get() as $inv) {
        $this->actingAs($this->koordinator)->post(route('penagihan.lunas', $inv), ['dibayar_at' => now()->toDateString()])->assertSessionHasNoErrors();
    }
    expect($this->pelanggan->fresh()->status)->toBe(StatusPelanggan::Aktif);
});

test('TUSBUG bayar di tempat yang disetujui melunasi rekening tunggakan', function () {
    tunggakan($this, $this->pelanggan, 2);
    $this->actingAs($this->koordinator)->post(route('tusbug.store'), ['pelanggan_ids' => [$this->pelanggan->id], 'tanggal_rencana' => now()->toDateString()]);
    $t = Tusbug::sole();

    $this->actingAs($this->biller)->post(route('tusbug.laksanakan', $t), [
        'hasil' => 'bayar_di_tempat', 'latitude' => 0.465, 'longitude' => 101.379,
        'foto_sebelum' => UploadedFile::fake()->image('a.jpg'), 'foto_sesudah' => UploadedFile::fake()->image('b.jpg'),
    ]);
    app(VerificationService::class)->setujui($t->fresh(), $this->koordinator);

    expect(Invoice::where('pelanggan_id', $this->pelanggan->id)->where('status', StatusInvoice::Lunas)->count())->toBe(2)
        ->and($this->pelanggan->fresh()->status)->toBe(StatusPelanggan::Aktif);
});

test('pelaksanaan TUSBUG wajib foto sebelum dan sesudah, dan hanya oleh biller yang ditugaskan', function () {
    tunggakan($this, $this->pelanggan, 2);
    $this->actingAs($this->koordinator)->post(route('tusbug.store'), ['pelanggan_ids' => [$this->pelanggan->id], 'tanggal_rencana' => now()->toDateString()]);
    $t = Tusbug::sole();

    $this->actingAs($this->biller)->post(route('tusbug.laksanakan', $t), ['hasil' => 'diputus'])->assertSessionHasErrors(['foto_sebelum', 'foto_sesudah']);

    $lain = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->actingAs($lain)->post(route('tusbug.laksanakan', $t), ['hasil' => 'diputus'])->assertForbidden();
    $this->actingAs($lain)->get(route('tusbug.show', $t))->assertForbidden();
});

// ---------------------------------------------------------------- Notifikasi & menu

test('notifikasi tampil di lonceng, bisa dibuka, dan badge tugas TUSBUG muncul', function () {
    tunggakan($this, $this->pelanggan, 2);
    $this->actingAs($this->koordinator)->post(route('tusbug.store'), ['pelanggan_ids' => [$this->pelanggan->id], 'tanggal_rencana' => now()->toDateString()]);

    $this->actingAs($this->biller)->get('/tugas')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.belum_dibaca', 1)
            ->where('notifikasi.daftar.0.judul', 'Tugas TUSBUG baru')
            ->where('navBadges.tusbug', 1));

    $id = $this->biller->notifications()->first()->id;
    $this->actingAs($this->biller)->post(route('notifikasi.buka', $id))->assertRedirect(route('tusbug.index'));
    expect($this->biller->fresh()->unreadNotifications()->count())->toBe(0);
});

test('koordinator membuka halaman data lapangan; biller tidak', function (string $url, string $komponen) {
    // Ada periode berjalan dengan satu catatan terkirim, agar query progres ikut teruji.
    $periode = Periode::factory()->berjalan()->create(['kode' => '202610']);
    $tugas = Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 1]);
    PencatatanMeter::create([
        'periode_id' => $periode->id, 'pelanggan_id' => $this->pelanggan->id, 'biller_id' => $this->biller->id, 'penugasan_id' => $tugas->id,
        'jenis' => 'pascabayar', 'stand_lalu' => 10, 'stand_kini' => 20, 'pemakaian_kwh' => 10, 'kondisi_meter' => 'normal',
        'status' => StatusCatatan::MenungguVerifikasi, 'dikirim_at' => now(),
    ]);

    $this->actingAs($this->koordinator)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($komponen));
    $this->actingAs($this->biller)->get($url)->assertForbidden();
})->with([
    ['/data/pencatatan', 'DataLapangan/Pencatatan'],
    ['/data/pelanggan', 'DataLapangan/Pelanggan'],
    ['/data/rute', 'DataLapangan/Rute'],
    ['/data/biller', 'DataLapangan/Biller'],
]);

test('halaman PDIL dan TUSBUG tampil untuk biller dan koordinator', function () {
    $this->actingAs($this->biller)->get('/pdil')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Pdil/Index')->where('bisaAjukan', true));
    $this->actingAs($this->biller)->get(route('pdil.create'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Pdil/Form')->where('jenis', 'pelanggan_baru'));
    $this->actingAs($this->biller)->get(route('pdil.create', ['idpel' => $this->pelanggan->idpel]))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('jenis', 'perubahan_data')->where('pelanggan.id', $this->pelanggan->id));
    $this->actingAs($this->koordinator)->get('/pdil')->assertOk();
    $this->actingAs($this->biller)->get('/tusbug')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tusbug/Index')->where('koordinator', false));
});
