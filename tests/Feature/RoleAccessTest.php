<?php

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\Unit;
use App\Models\User;
use App\Services\KpiCalculator;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('biller tidak boleh membuka dashboard', function () {
    $biller = User::factory()->role(Role::Biller)->create();

    $this->actingAs($biller)->get('/dashboard')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
});

test('koordinator dan super admin tidak boleh membuka tugas biller', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get('/tugas')->assertForbidden();
})->with([Role::KoordinatorAdmin, Role::SuperAdmin]);

test('dashboard koordinator hanya menampilkan biller di unitnya', function () {
    $unit = Unit::factory()->create();
    $lain = Unit::factory()->create();
    $koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => $unit->id]);
    $periode = Periode::factory()->create(['status' => StatusPeriode::Ditutup]);

    foreach ([$unit, $unit, $lain] as $u) {
        $biller = User::factory()->role(Role::Biller)->create(['unit_id' => $u->id]);
        $rute = Rute::factory()->create(['unit_id' => $u->id, 'biller_id' => $biller->id]);
        Penugasan::create(['periode_id' => $periode->id, 'rute_id' => $rute->id, 'biller_id' => $biller->id, 'target_pelanggan' => 5]);
    }
    app(KpiCalculator::class)->hitung($periode, final: true);

    $this->actingAs($koordinator)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('peringkat', 2)
            ->where('unitOptions', null)
            ->where('auth.user.role', 'koordinator_admin'));
});

test('dashboard tanpa skor menampilkan keadaan kosong', function () {
    $koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => Unit::factory()->create()->id]);

    $this->actingAs($koordinator)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index')->where('periode', null));
});

test('tugas tampil untuk biller', function () {
    $biller = User::factory()->role(Role::Biller)->create();

    $this->actingAs($biller)->get('/tugas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tugas/Index'));
});

test('halaman yang tidak ada memakai halaman galat aplikasi', function () {
    $this->get('/tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});
