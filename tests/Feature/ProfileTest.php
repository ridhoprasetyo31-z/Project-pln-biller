<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('halaman profil tampil untuk semua peran', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profil')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profil.nip', $user->nip));
});

test('nama dan nomor HP bisa diperbarui', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profil')
        ->patch('/profil', ['name' => 'Andi Saputra', 'phone' => '0812-3456-7890'])
        ->assertRedirect('/profil')
        ->assertSessionHas('success');

    expect($user->fresh())
        ->name->toBe('Andi Saputra')
        ->phone->toBe('081234567890');
});

test('foto profil diunggah, dikecilkan, dan diberi nama UUID', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profil', [
        'name' => $user->name,
        'foto' => UploadedFile::fake()->image('wajah.png', 1200, 900),
    ])->assertSessionHasNoErrors();

    $path = $user->fresh()->foto_profil_path;
    expect($path)->toMatch('#^foto-profil/[0-9a-f\-]{36}\.jpg$#');
    Storage::disk('public')->assertExists($path);

    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect([$w, $h])->toBe([512, 512]);
});

test('foto lebih dari 5 MB ditolak', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profil', [
        'name' => $user->name,
        'foto' => UploadedFile::fake()->image('besar.jpg')->size(6000),
    ])->assertSessionHasErrors('foto');
});

test('nomor HP tidak valid ditolak', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profil', ['name' => $user->name, 'phone' => '12345'])
        ->assertSessionHasErrors('phone');
});

test('kata sandi bisa diubah dengan kata sandi lama yang benar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'password',
        'password' => 'sandibaru123',
        'password_confirmation' => 'sandibaru123',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('sandibaru123', $user->fresh()->password))->toBeTrue();
});

test('ubah kata sandi gagal bila kata sandi lama salah atau baru terlalu pendek', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profil/password', [
        'current_password' => 'keliru',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])->assertSessionHasErrors(['current_password', 'password']);
});

test('profil dan ubah kata sandi hanya untuk pengguna yang sudah masuk', function () {
    $this->get('/profil')->assertRedirect('/login');
    $this->put('/profil/password', [])->assertRedirect('/login');
});
