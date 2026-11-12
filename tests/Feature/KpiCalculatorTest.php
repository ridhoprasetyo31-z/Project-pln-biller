<?php

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusPeriode;
use App\Models\KoreksiRekening;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\SkorKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Services\KpiCalculator;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Data contoh yang bisa dihitung dengan tangan.
 *
 * Biller A (target 10):
 *   6 disetujui sempurna (salah satunya kena koreksi "salah catat biller"),
 *   1 disetujui setelah 1× revisi, terlambat, lokasi 150 m,
 *   1 masih status revisi (sudah diverifikasi, belum disetujui),
 *   1 menunggu verifikasi (belum masuk hitungan).
 *     tercatat 7, terverifikasi 8, tanpa revisi 5, tepat waktu 6, bukti lengkap 6
 *     Akurasi 5/8 = 62,5 · Penyelesaian 7/10 = 70 · Tepat waktu 6/7 = 85,714 · Bukti 6/7 = 85,714
 *     Skor = 0,4·62,5 + 0,3·70 + 0,2·85,714 + 0,1·85,714 = 71,71
 *
 * Biller B (target 4): 4 disetujui sempurna → semua 100, skor 100.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->unit = Unit::factory()->create();
    $this->koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => $this->unit->id]);
    $this->periode = Periode::factory()->create(['kode' => '202609', 'status' => StatusPeriode::Ditutup]);
});

function siapkanBiller(object $t, int $target): array
{
    $biller = User::factory()->role(Role::Biller)->create(['unit_id' => $t->unit->id]);
    $rute = Rute::factory()->create(['unit_id' => $t->unit->id, 'biller_id' => $biller->id]);
    $tugas = Penugasan::create(['periode_id' => $t->periode->id, 'rute_id' => $rute->id, 'biller_id' => $biller->id, 'target_pelanggan' => $target]);

    return [$biller, $rute, $tugas];
}

function catatan(object $t, array $bt, array $atur = [], bool $foto = true): PencatatanMeter
{
    [$biller, $rute, $tugas] = $bt;
    $c = PencatatanMeter::create(array_merge([
        'periode_id' => $t->periode->id,
        'pelanggan_id' => Pelanggan::factory()->create(['rute_id' => $rute->id])->id,
        'biller_id' => $biller->id,
        'penugasan_id' => $tugas->id,
        'jenis' => 'pascabayar',
        'stand_lalu' => 100, 'stand_kini' => 200, 'pemakaian_kwh' => 100,
        'kondisi_meter' => 'normal',
        'latitude' => 0.465, 'longitude' => 101.379, 'jarak_m' => 20,
        'dikirim_at' => now(), 'tepat_waktu' => true,
        'status' => StatusCatatan::Disetujui, 'jumlah_revisi' => 0,
    ], $atur));

    if ($foto) {
        $c->lampiran()->create(['jenis' => 'meter', 'path' => 'demo/meter-1.jpg', 'ukuran_kb' => 50, 'diunggah_oleh' => $biller->id]);
    }

    return $c;
}

function dataContoh(object $t): array
{
    $a = siapkanBiller($t, 10);
    $sempurna = collect(range(1, 6))->map(fn () => catatan($t, $a));
    catatan($t, $a, ['jumlah_revisi' => 1, 'tepat_waktu' => false, 'jarak_m' => 150]);
    catatan($t, $a, ['status' => StatusCatatan::Revisi, 'jumlah_revisi' => 1]);
    catatan($t, $a, ['status' => StatusCatatan::MenungguVerifikasi]);

    KoreksiRekening::create([
        'pelanggan_id' => $sempurna[0]->pelanggan_id, 'periode_id' => $t->periode->id, 'pencatatan_meter_id' => $sempurna[0]->id,
        'diajukan_oleh' => $a[0]->id, 'stand_lama' => 200, 'stand_baru' => 190, 'kwh_lama' => 100, 'kwh_baru' => 90,
        'tagihan_lama' => 144470, 'tagihan_baru' => 130023, 'penyebab' => 'salah_catat_biller', 'alasan' => 'Salah ketik',
        'status' => StatusCatatan::Disetujui,
    ]);

    $b = siapkanBiller($t, 4);
    foreach (range(1, 4) as $_) {
        catatan($t, $b);
    }

    return [$a[0], $b[0]];
}

test('skor dihitung sesuai rumus PRD dengan angka mentah yang benar', function () {
    [$a, $b] = dataContoh($this);

    app(KpiCalculator::class)->hitung($this->periode, final: true);

    $skorA = SkorKinerja::where('biller_id', $a->id)->sole();
    expect($skorA->only(['target', 'tercatat', 'terverifikasi', 'disetujui_tanpa_revisi', 'jumlah_tepat_waktu', 'jumlah_bukti_lengkap']))
        ->toBe(['target' => 10, 'tercatat' => 7, 'terverifikasi' => 8, 'disetujui_tanpa_revisi' => 5, 'jumlah_tepat_waktu' => 6, 'jumlah_bukti_lengkap' => 6])
        ->and($skorA->akurasi)->toBe(62.5)
        ->and($skorA->penyelesaian)->toBe(70.0)
        ->and($skorA->ketepatan_waktu)->toBe(85.71)
        ->and($skorA->bukti_lengkap)->toBe(85.71)
        ->and($skorA->skor)->toBe(71.71)
        ->and($skorA->peringkat)->toBe(2)
        ->and($skorA->is_final)->toBeTrue();

    $skorB = SkorKinerja::where('biller_id', $b->id)->sole();
    expect($skorB->skor)->toBe(100.0)->and($skorB->peringkat)->toBe(1);
});

test('catatan tanpa foto atau terlalu jauh tidak dihitung bukti lengkap', function () {
    $a = siapkanBiller($this, 2);
    catatan($this, $a, [], foto: false);
    catatan($this, $a, ['jarak_m' => null, 'latitude' => null, 'longitude' => null]);

    app(KpiCalculator::class)->hitung($this->periode);

    expect(SkorKinerja::sole()->jumlah_bukti_lengkap)->toBe(0);
});

test('skor seri dimenangkan biller dengan akurasi lebih tinggi', function () {
    // C: akurasi 100, penyelesaian 50 → 40 + 15 + 20 + 10 = 85
    $c = siapkanBiller($this, 4);
    catatan($this, $c);
    catatan($this, $c);
    // D: akurasi 5/8 = 62,5, penyelesaian 100 → 25 + 30 + 20 + 10 = 85
    $d = siapkanBiller($this, 8);
    foreach (range(1, 8) as $i) {
        catatan($this, $d, ['jumlah_revisi' => $i <= 3 ? 1 : 0]);
    }

    app(KpiCalculator::class)->hitung($this->periode);

    expect(SkorKinerja::where('biller_id', $c[0]->id)->value('skor'))->toBe(85.0)
        ->and(SkorKinerja::where('biller_id', $d[0]->id)->value('skor'))->toBe(85.0)
        ->and(SkorKinerja::where('biller_id', $c[0]->id)->value('peringkat'))->toBe(1)
        ->and(SkorKinerja::where('biller_id', $d[0]->id)->value('peringkat'))->toBe(2);
});

test('bobot dari tabel pengaturan dipakai', function () {
    [$a] = dataContoh($this);
    Pengaturan::simpan('bobot_kpi', ['akurasi' => 25, 'penyelesaian' => 25, 'tepat_waktu' => 25, 'bukti_lengkap' => 25]);

    app(KpiCalculator::class)->hitung($this->periode);

    // (62,5 + 70 + 85,714 + 85,714) / 4 = 75,98
    expect(SkorKinerja::where('biller_id', $a->id)->value('skor'))->toBe(75.98);
});

test('menutup periode menghitung skor final', function () {
    $periode = Periode::factory()->berjalan()->create(['kode' => '202610']);
    $biller = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $rute = Rute::factory()->create(['unit_id' => $this->unit->id, 'biller_id' => $biller->id]);
    Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $rute->id, 'biller_id' => $biller->id, 'target_pelanggan' => 5]);
    $admin = User::factory()->role(Role::SuperAdmin)->create();

    $this->actingAs($admin)->post("/pengaturan/periode/{$periode->id}/tutup")->assertSessionHasNoErrors();

    $skor = SkorKinerja::where('periode_id', $periode->id)->sole();
    expect($skor->is_final)->toBeTrue()->and($skor->skor)->toBe(0.0);
});

test('dashboard koordinator menampilkan angka yang sama dengan hitungan manual', function () {
    [$a, $b] = dataContoh($this);
    app(KpiCalculator::class)->hitung($this->periode, final: true);

    $this->actingAs($this->koordinator)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('periode.kode', '202609')
            ->has('peringkat', 2)
            ->where('peringkat.0.biller_id', $b->id)
            ->where('peringkat.1.skor', 71.71)
            ->where('peringkat.1.akurasi', 62.5)
            ->where('peringkat.1.status', 'pembinaan')
            // Akurasi tim = (5 + 4) / (8 + 4) = 75%
            ->where('ringkasan.akurasi_tim', 75)
            ->where('ringkasan.target', 14)
            ->where('ringkasan.disetujui', 11));
});

test('biller melihat kinerjanya sendiri dan rinciannya', function () {
    [$a] = dataContoh($this);
    app(KpiCalculator::class)->hitung($this->periode, final: true);

    $this->actingAs($a)->get('/kinerja-saya')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Kinerja/Saya')
            ->where('skor.skor', 71.71)
            ->where('skor.peringkat', 2)
            ->where('jumlahBiller', 2)
            ->has('pengurang.revisi', 2)
            ->has('pengurang.terlambat', 1)
            ->has('pengurang.jauh', 1));
});

test('laporan peringkat bisa diunduh sebagai Excel dan PDF', function () {
    dataContoh($this);
    app(KpiCalculator::class)->hitung($this->periode, final: true);

    $this->actingAs($this->koordinator)->get('/laporan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Laporan/Index')->has('periode', 1));

    $this->actingAs($this->koordinator)->get('/laporan/202609/excel')
        ->assertOk()
        ->assertDownload();

    $pdf = $this->actingAs($this->koordinator)->get('/laporan/202609/pdf');
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf')
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

test('biller tidak bisa membuka dashboard atau laporan', function () {
    [$a] = dataContoh($this);

    $this->actingAs($a)->get('/laporan')->assertForbidden();
    $this->actingAs($a)->get('/laporan/202609/excel')->assertForbidden();
});
