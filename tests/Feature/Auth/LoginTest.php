<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('halaman masuk tampil', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/tugas')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});

test('tiap peran diarahkan ke halaman yang benar setelah login dengan NIP', function (Role $role, string $tujuan) {
    $user = User::factory()->role($role)->create();

    $this->post('/login', ['login' => $user->nip, 'password' => 'password'])
        ->assertRedirect($tujuan);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
})->with([
    'biller' => [Role::Biller, '/tugas'],
    'koordinator admin' => [Role::KoordinatorAdmin, '/dashboard'],
    'super admin' => [Role::SuperAdmin, '/dashboard'],
]);

test('login dengan email (tidak peka huruf besar) berhasil', function () {
    $user = User::factory()->role(Role::KoordinatorAdmin)->create(['email' => 'rina@biller.test']);

    $this->post('/login', ['login' => 'Rina@Biller.test', 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('kata sandi salah ditolak dengan pesan Indonesia', function () {
    $user = User::factory()->create();

    $this->post('/login', ['login' => $user->nip, 'password' => 'salah'])
        ->assertSessionHasErrors(['login' => 'NIP/email atau kata sandi tidak sesuai.']);

    $this->assertGuest();
});

test('akun nonaktif tidak bisa masuk', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['login' => $user->nip, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('login dibatasi 5 percobaan per menit', function () {
    $user = User::factory()->create();
    RateLimiter::clear(strtolower($user->nip).'|127.0.0.1');

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => $user->nip, 'password' => 'salah']);
    }

    $response = $this->post('/login', ['login' => $user->nip, 'password' => 'password']);

    $response->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('Terlalu banyak percobaan');
    $this->assertGuest();
});

test('pengguna yang sudah masuk diarahkan dari /login ke beranda perannya', function () {
    $biller = User::factory()->role(Role::Biller)->create();

    $this->actingAs($biller)->get('/login')->assertRedirect('/');
    $this->actingAs($biller)->get('/')->assertRedirect('/tugas');
});

test('logout mengakhiri sesi', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});
