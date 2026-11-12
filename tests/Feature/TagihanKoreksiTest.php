<?php

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPeriode;
use App\Jobs\TandaiInvoiceMenunggak;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\SkorKinerja;
use App\Models\Tarif;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceGenerator;
use App\Services\KpiCalculator;
use App\Services\VerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    Tarif::factory()->create(['golongan' => 'R1', 'daya_min_va' => 450, 'daya_max_va' => 5500, 'harga_per_kwh' => 1444.70, 'biaya_beban' => 0]);
    $this->unit = Unit::factory()->create();
    $this->koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => $this->unit->id]);
    $this->biller = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->rute = Rute::factory()->create(['unit_id' => $this->unit->id, 'biller_id' => $this->biller->id]);
    $this->pelanggan = Pelanggan::factory()->create(['rute_id' => $this->rute->id, 'latitude' => 0.465, 'longitude' => 101.379]);
    $this->periode = Periode::factory()->berjalan()->create([
        'kode' => '202610', 'tanggal_mulai' => now()->subDays(10)->toDateString(), 'tanggal_selesai' => now()->addDays(5)->toDateString(),
    ]);
    $this->tugas = Penugasan::create(['periode_id' => $this->periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 1]);
});

/** Catatan 1000 → 1150 (150 kWh) yang lalu disetujui koordinator. */
function catatanDisetujui(object $t): PencatatanMeter
{
    $c = PencatatanMeter::create([
        'periode_id' => $t->periode->id, 'pelanggan_id' => $t->pelanggan->id, 'biller_id' => $t->biller->id, 'penugasan_id' => $t->tugas->id,
        'jenis' => 'pascabayar', 'stand_lalu' => 1000, 'stand_kini' => 1150, 'pemakaian_kwh' => 150, 'kondisi_meter' => 'normal',
        'latitude' => 0.465, 'longitude' => 101.379, 'jarak_m' => 5, 'dikirim_at' => now(), 'tepat_waktu' => true,
        'status' => StatusCatatan::MenungguVerifikasi,
    ]);
    $c->lampiran()->create(['jenis' => 'meter', 'path' => 'demo/meter-1.jpg', 'ukuran_kb' => 50, 'diunggah_oleh' => $t->biller->id]);
    app(VerificationService::class)->setujui($c, $t->koordinator);

    return $c->refresh();
}

test('rincian tagihan: pemakaian × harga + beban + PPJ, dibulatkan ke rupiah', function () {
    // 150 × 1.444,70 = 216.705; PPJ 3% = 6.501,15 → 6.501; total 223.206
    expect(InvoiceGenerator::rincian(150, 1444.70, 0, 3))->toBe([
        'pemakaian_kwh' => 150.0, 'biaya_pemakaian' => 216705.0, 'biaya_beban' => 0.0,
        'subtotal' => 216705.0, 'pajak' => 6501.0, 'total' => 223206.0,
    ]);
    // Pemakaian negatif (meter diganti) tidak menghasilkan tagihan negatif.
    expect(InvoiceGenerator::rincian(-5, 1444.70, 20000, 3)['total'])->toBe(20600.0);
});

test('invoice terbit otomatis saat catatan pascabayar disetujui, tarif dan PPJ disalin', function () {
    $c = catatanDisetujui($this);

    $invoice = Invoice::sole();
    expect($invoice->nomor)->toBe("INV/202610/{$this->pelanggan->idpel}")
        ->and($invoice->pencatatan_meter_id)->toBe($c->id)
        ->and($invoice->harga_per_kwh)->toBe(1444.70)
        ->and($invoice->pemakaian_kwh)->toBe(150.0)
        ->and($invoice->pajak)->toBe(6501.0)
        ->and($invoice->total)->toBe(223206.0)
        ->and($invoice->status)->toBe(StatusInvoice::BelumBayar)
        ->and($invoice->jatuh_tempo->toDateString())->toBe($this->periode->tanggal_selesai->copy()->addDays(25)->toDateString());

    // Mengubah tarif tidak mengubah invoice yang sudah terbit.
    Tarif::query()->update(['harga_per_kwh' => 2000]);
    expect($invoice->fresh()->total)->toBe(223206.0);
});

test('catatan prabayar tidak menerbitkan invoice', function () {
    $pra = Pelanggan::factory()->create(['rute_id' => $this->rute->id, 'jenis' => 'prabayar']);
    $c = PencatatanMeter::create([
        'periode_id' => $this->periode->id, 'pelanggan_id' => $pra->id, 'biller_id' => $this->biller->id, 'penugasan_id' => $this->tugas->id,
        'jenis' => 'prabayar', 'sisa_kwh' => 40, 'kondisi_meter' => 'normal', 'status' => StatusCatatan::MenungguVerifikasi,
    ]);
    app(VerificationService::class)->setujui($c, $this->koordinator);

    expect(Invoice::count())->toBe(0);
});

test('invoice lewat jatuh tempo menjadi menunggak', function () {
    catatanDisetujui($this);
    Invoice::query()->update(['jatuh_tempo' => now()->subDay()->toDateString()]);

    expect((new TandaiInvoiceMenunggak)->handle())->toBe(1)
        ->and(Invoice::sole()->status)->toBe(StatusInvoice::Menunggak);
});

test('kunjungan lunas yang disetujui melunasi invoice', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->biller)->post(route('kunjungan.store', $invoice), [
        'hasil' => 'lunas', 'nominal_dibayar' => '223.206', 'latitude' => 0.46505, 'longitude' => 101.379,
        'foto' => UploadedFile::fake()->image('kuitansi.jpg'),
    ])->assertSessionHasNoErrors()->assertRedirect(route('penagihan.show', $invoice));

    $k = KunjunganPenagihan::sole();
    expect($k->status)->toBe(StatusCatatan::MenungguVerifikasi)->and($k->nominal_dibayar)->toBe(223206.0);

    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.setujui', ['tipe' => 'kunjungan_penagihan', 'id' => $k->id]))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($invoice->fresh()->dibayar_at)->not->toBeNull();
});

test('kunjungan jauh dari pelanggan ditandai dan wajib foto', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->biller)->post(route('kunjungan.store', $invoice), ['hasil' => 'tidak_bertemu'])
        ->assertSessionHasErrors('foto');

    $this->actingAs($this->biller)->post(route('kunjungan.store', $invoice), [
        'hasil' => 'tidak_bertemu', 'latitude' => 0.468, 'longitude' => 101.379, 'foto' => UploadedFile::fake()->image('rumah.jpg'),
    ])->assertSessionHasNoErrors();

    $k = KunjunganPenagihan::sole();
    expect($k->status)->toBe(StatusCatatan::Ditandai)
        ->and($k->anomali()->pluck('jenis')->map->value->all())->toBe(['lokasi_jauh']);
});

test('biller rute lain tidak bisa melihat atau menagih invoice', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();
    $lain = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);

    $this->actingAs($lain)->get(route('penagihan.show', $invoice))->assertForbidden();
    $this->actingAs($lain)->get(route('kunjungan.create', $invoice))->assertForbidden();
});

test('koordinator menandai lunas dan bisa mengunduh PDF rekening', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->koordinator)->post(route('penagihan.lunas', $invoice), ['dibayar_at' => now()->toDateString()])
        ->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);

    $pdf = $this->actingAs($this->koordinator)->get(route('penagihan.pdf', $invoice));
    $pdf->assertOk();
    expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

test('koreksi salah catat yang disetujui mengubah invoice, stand meter, dan menurunkan akurasi biller', function () {
    $c = catatanDisetujui($this);
    $invoice = Invoice::sole();
    $this->periode->update(['status' => StatusPeriode::Ditutup]);
    app(KpiCalculator::class)->hitung($this->periode, final: true);
    expect(SkorKinerja::sole()->akurasi)->toBe(100.0);

    // Stand yang benar 1138 (12 kWh lebih kecil).
    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), [
        'invoice_id' => $invoice->id,
        'stand_baru' => '1138',
        'penyebab' => 'salah_catat_biller',
        'alasan' => 'Digit terakhir terbaca 0, seharusnya 8.',
        'foto' => UploadedFile::fake()->image('meter.jpg'),
    ])->assertSessionHasNoErrors();

    $koreksi = KoreksiRekening::sole();
    // 138 × 1.444,70 = 199.369 (dibulatkan); PPJ 3% = 5.981; total 205.350
    expect($koreksi->status)->toBe(StatusCatatan::MenungguVerifikasi)
        ->and($koreksi->kwh_baru)->toBe(138.0)
        ->and($koreksi->tagihan_lama)->toBe(223206.0)
        ->and($koreksi->tagihan_baru)->toBe(205350.0);

    $this->actingAs($this->koordinator)
        ->post(route('verifikasi.setujui', ['tipe' => 'koreksi_rekening', 'id' => $koreksi->id]))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh())
        ->total->toBe(205350.0)
        ->pemakaian_kwh->toBe(138.0)
        ->and($c->fresh()->stand_kini)->toBe(1138.0)
        ->and($c->fresh()->pemakaian_kwh)->toBe(138.0);

    // Satu-satunya catatan biller kini terkena koreksi salah catat → akurasi 0%.
    $skor = SkorKinerja::sole();
    expect($skor->akurasi)->toBe(0.0)
        ->and($skor->disetujui_tanpa_revisi)->toBe(0)
        ->and($skor->is_final)->toBeTrue();
});

test('koreksi dengan penyebab lain tidak mengurangi akurasi', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), [
        'invoice_id' => $invoice->id, 'stand_baru' => '1140', 'penyebab' => 'komplain_pelanggan',
        'alasan' => 'Pelanggan komplain, dicek ulang.', 'foto' => UploadedFile::fake()->image('meter.jpg'),
    ])->assertSessionHasNoErrors();
    app(VerificationService::class)->setujui(KoreksiRekening::sole(), $this->koordinator);

    expect(Invoice::sole()->pemakaian_kwh)->toBe(140.0)
        ->and(SkorKinerja::sole()->akurasi)->toBe(100.0);
});

test('stand koreksi tidak boleh di bawah stand lalu atau sama dengan stand tercatat', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();
    $data = ['invoice_id' => $invoice->id, 'penyebab' => 'salah_catat_biller', 'alasan' => 'Salah baca digit meter.', 'foto' => UploadedFile::fake()->image('m.jpg')];

    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), $data + ['stand_baru' => '990'])->assertSessionHasErrors('stand_baru');
    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), $data + ['stand_baru' => '1150'])->assertSessionHasErrors('stand_baru');
});

test('koreksi yang ditolak bisa diperbaiki biller lalu disetujui', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), [
        'invoice_id' => $invoice->id, 'stand_baru' => '1145', 'penyebab' => 'salah_input_sistem',
        'alasan' => 'Angka di sistem berbeda dengan foto.', 'foto' => UploadedFile::fake()->image('m.jpg'),
    ]);
    $koreksi = KoreksiRekening::sole();

    $this->actingAs($this->koordinator)->post(route('verifikasi.tolak', ['tipe' => 'koreksi_rekening', 'id' => $koreksi->id]), ['alasan' => 'foto_buram'])
        ->assertSessionHasNoErrors();
    expect($koreksi->fresh()->status)->toBe(StatusCatatan::Revisi);

    // Biller memperbaiki tanpa mengunggah ulang foto (foto lama dipakai).
    $this->actingAs($this->biller)->put(route('koreksi-rekening.update', $koreksi), [
        'stand_baru' => '1144', 'penyebab' => 'salah_input_sistem', 'alasan' => 'Sudah difoto ulang, angka 1144.',
    ])->assertSessionHasNoErrors();

    expect($koreksi->fresh())->status->toBe(StatusCatatan::MenungguVerifikasi)->stand_baru->toBe(1144.0)->jumlah_revisi->toBe(1);
});

test('antrean verifikasi menggabungkan catat meter, penagihan, dan koreksi', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();
    $this->actingAs($this->biller)->post(route('kunjungan.store', $invoice), [
        'hasil' => 'janji_bayar', 'tanggal_janji' => now()->addDays(3)->toDateString(), 'latitude' => 0.465, 'longitude' => 101.379,
        'foto' => UploadedFile::fake()->image('rumah.jpg'),
    ]);
    $this->actingAs($this->biller)->post(route('koreksi-rekening.store'), [
        'invoice_id' => $invoice->id, 'stand_baru' => '1140', 'penyebab' => 'meter_rusak',
        'alasan' => 'Meter diganti petugas.', 'foto' => UploadedFile::fake()->image('m.jpg'),
    ]);

    $this->actingAs($this->koordinator)->get('/verifikasi?tab=normal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('jumlah.normal', 2)
            ->has('antrean.data', 2)
            ->where('jenisOptions.1.menunggu', 1)
            ->where('jenisOptions.2.menunggu', 1)
            ->where('navBadges.verifikasi', 2));

    $this->actingAs($this->koordinator)->get('/verifikasi?tab=normal&jenis=koreksi_rekening')
        ->assertInertia(fn (Assert $page) => $page
            ->has('antrean.data', 1)
            ->where('detail.tipe', 'koreksi_rekening')
            ->where('detail.kwh_baru', 140));
});

test('halaman penagihan, kunjungan, dan koreksi tampil', function () {
    catatanDisetujui($this);
    $invoice = Invoice::sole();

    $this->actingAs($this->biller)->get('/penagihan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Penagihan/Index')->has('invoice.data', 1));
    $this->actingAs($this->biller)->get(route('penagihan.show', $invoice))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Penagihan/Show')->where('can.kunjungan', true)->where('can.koreksi', true));
    $this->actingAs($this->biller)->get(route('kunjungan.create', $invoice))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Penagihan/Kunjungan'));
    $this->actingAs($this->biller)->get(route('koreksi-rekening.create', ['idpel' => $this->pelanggan->idpel]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Koreksi/Form')->where('invoice.id', $invoice->id));
    $this->actingAs($this->koordinator)->get('/koreksi-rekening')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Koreksi/Index'));
});
