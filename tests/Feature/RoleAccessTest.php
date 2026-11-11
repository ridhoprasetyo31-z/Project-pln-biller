<?php

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
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

test('dashboard tampil untuk koordinator dengan jumlah biller di unitnya saja', function () {
    $koordinator = User::factory()->role(Role::KoordinatorAdmin)->create(['unit_id' => null]);
    $unit = Unit::factory()->create();
    $lain = Unit::factory()->create();
    $koordinator->update(['unit_id' => $unit->id]);

    User::factory()->count(2)->role(Role::Biller)->create(['unit_id' => $unit->id]);
    User::factory()->role(Role::Biller)->create(['unit_id' => $lain->id]);

    $this->actingAs($koordinator)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('ringkasan.jumlah_biller', 2)
            ->where('auth.user.role', 'koordinator_admin'));
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
