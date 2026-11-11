<?php

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Models\Pelanggan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->role(Role::SuperAdmin)->create();
    $this->unit = Unit::factory()->create();
    $this->biller = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->rute = Rute::factory()->create(['unit_id' => $this->unit->id, 'biller_id' => $this->biller->id]);
    Pelanggan::factory()->count(3)->create(['rute_id' => $this->rute->id]);
    Pelanggan::factory()->create(['rute_id' => $this->rute->id, 'status' => 'putus_sementara']);
});

test('super admin membuat periode dan penugasan tersusun dari biller pemegang rute', function () {
    $this->actingAs($this->admin)
        ->post('/pengaturan/periode', ['bulan' => '2026-11', 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-25'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $periode = Periode::where('kode', '202611')->firstOrFail();
    expect($periode->status)->toBe(StatusPeriode::Draft);

    $penugasan = Penugasan::where('periode_id', $periode->id)->sole();
    expect($penugasan->biller_id)->toBe($this->biller->id)
        ->and($penugasan->target_pelanggan)->toBe(3); // hanya pelanggan aktif
});

test('kode periode tidak boleh ganda', function () {
    Periode::factory()->create(['kode' => '202611']);

    $this->actingAs($this->admin)
        ->post('/pengaturan/periode', ['bulan' => '2026-11', 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-30'])
        ->assertSessionHasErrors('bulan');
});

test('periode hanya bisa dimulai bila tidak ada periode lain yang berjalan', function () {
    Periode::factory()->berjalan()->create(['kode' => '202610']);
    $draf = Periode::factory()->create(['kode' => '202611']);
    Penugasan::create(['periode_id' => $draf->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 3]);

    $this->actingAs($this->admin)->post("/pengaturan/periode/{$draf->id}/mulai")->assertSessionHasErrors('periode');
    expect($draf->fresh()->status)->toBe(StatusPeriode::Draft);
});

test('menutup periode mencatat waktu dan penutupnya, lalu penugasan terkunci', function () {
    $periode = Periode::factory()->berjalan()->create();
    $penugasan = Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 3]);

    $this->actingAs($this->admin)->post("/pengaturan/periode/{$periode->id}/tutup")->assertSessionHasNoErrors();

    $periode->refresh();
    expect($periode->status)->toBe(StatusPeriode::Ditutup)
        ->and($periode->ditutup_oleh)->toBe($this->admin->id)
        ->and($periode->ditutup_at)->not->toBeNull();

    $lain = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);
    $this->actingAs($this->admin)
        ->put("/pengaturan/penugasan/{$penugasan->id}", ['biller_id' => $lain->id, 'target_pelanggan' => 3])
        ->assertForbidden();
});

test('penugasan hanya boleh ke biller aktif di unit yang sama dengan rute', function () {
    $periode = Periode::factory()->create();
    $penugasan = Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 3]);
    $billerUnitLain = User::factory()->role(Role::Biller)->create(['unit_id' => Unit::factory()->create()->id]);
    $billerSatuUnit = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);

    $this->actingAs($this->admin)
        ->put("/pengaturan/penugasan/{$penugasan->id}", ['biller_id' => $billerUnitLain->id, 'target_pelanggan' => 3])
        ->assertSessionHasErrors('biller_id');

    $this->actingAs($this->admin)
        ->put("/pengaturan/penugasan/{$penugasan->id}", ['biller_id' => $billerSatuUnit->id, 'target_pelanggan' => 5])
        ->assertSessionHasNoErrors();

    expect($penugasan->fresh())->biller_id->toBe($billerSatuUnit->id)->target_pelanggan->toBe(5);
});

test('rute bisa ditugaskan ke biller pemegang baru dari halaman rute', function () {
    $baru = User::factory()->role(Role::Biller)->create(['unit_id' => $this->unit->id]);

    $this->actingAs($this->admin)
        ->patch("/pengaturan/rute/{$this->rute->id}/biller", ['biller_id' => $baru->id])
        ->assertSessionHasNoErrors();

    expect($this->rute->fresh()->biller_id)->toBe($baru->id);
});

test('halaman detail periode tampil dengan daftar penugasan', function () {
    $periode = Periode::factory()->create();
    Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $this->rute->id, 'biller_id' => $this->biller->id, 'target_pelanggan' => 3]);

    $this->actingAs($this->admin)->get("/pengaturan/periode/{$periode->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pengaturan/Periode/Show')
            ->has('penugasan', 1)
            ->where('penugasan.0.pencatatan_count', 0)
            ->where('can.mulai', true));
});
