<?php

use App\Enums\Role;
use App\Models\Pelanggan;
use App\Models\Pengaturan;
use App\Models\Rute;
use App\Models\Tarif;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->role(Role::SuperAdmin)->create();
});

test('semua halaman pengaturan tampil untuk super admin', function (string $url, string $komponen) {
    $this->actingAs($this->admin)->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($komponen));
})->with([
    ['/pengaturan/users', 'Pengaturan/Users/Index'],
    ['/pengaturan/units', 'Pengaturan/Units/Index'],
    ['/pengaturan/rute', 'Pengaturan/Rute/Index'],
    ['/pengaturan/pelanggan', 'Pengaturan/Pelanggan/Index'],
    ['/pengaturan/pelanggan/create', 'Pengaturan/Pelanggan/Form'],
    ['/pengaturan/tarif', 'Pengaturan/Tarif/Index'],
    ['/pengaturan/periode', 'Pengaturan/Periode/Index'],
    ['/pengaturan/kpi', 'Pengaturan/Kpi/Edit'],
]);

test('koordinator dan biller tidak bisa membuka pengaturan', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get('/pengaturan/users')->assertForbidden();
    $this->actingAs($user)->post('/pengaturan/periode', [])->assertForbidden();
})->with([Role::KoordinatorAdmin, Role::Biller]);

test('super admin membuat pengguna baru yang bisa langsung masuk', function () {
    $unit = Unit::factory()->create();

    $this->actingAs($this->admin)->post('/pengaturan/users', [
        'name' => 'Tono Biller',
        'nip' => '199901012024011099',
        'email' => 'tono@biller.test',
        'phone' => '081299990000',
        'role' => 'biller',
        'unit_id' => $unit->id,
        'password' => 'rahasia123',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    auth()->logout();
    $this->post('/login', ['login' => '199901012024011099', 'password' => 'rahasia123'])->assertRedirect('/tugas');
});

test('biller wajib punya unit dan NIP harus 18 digit', function () {
    $this->actingAs($this->admin)->post('/pengaturan/users', [
        'name' => 'Tanpa Unit',
        'nip' => '123',
        'role' => 'biller',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors(['nip', 'unit_id']);
});

test('super admin tidak bisa menonaktifkan atau menghapus akunnya sendiri', function () {
    $this->actingAs($this->admin)->put("/pengaturan/users/{$this->admin->id}", [
        'name' => $this->admin->name,
        'nip' => $this->admin->nip,
        'role' => 'super_admin',
        'is_active' => false,
    ])->assertSessionHasErrors('is_active');

    $this->actingAs($this->admin)->delete("/pengaturan/users/{$this->admin->id}")->assertForbidden();
});

test('perubahan data master tercatat di log audit', function () {
    $this->actingAs($this->admin)->post('/pengaturan/units', [
        'kode' => '18999',
        'nama' => 'UP3 Uji',
        'jenis' => 'up3',
    ])->assertSessionHasNoErrors();

    $log = Activity::where('log_name', 'master')->latest('id')->first();
    expect($log->event)->toBe('created')
        ->and($log->causer_id)->toBe($this->admin->id);
});

test('unit yang masih dipakai tidak bisa dihapus', function () {
    $unit = Unit::factory()->create();
    Rute::factory()->create(['unit_id' => $unit->id]);

    $this->actingAs($this->admin)->delete("/pengaturan/units/{$unit->id}")->assertSessionHas('error');
    expect(Unit::find($unit->id))->not->toBeNull();
});

test('bobot KPI harus berjumlah 100 dan tersimpan ke tabel pengaturan', function () {
    $nilai = [
        'bobot_kpi' => ['akurasi' => 50, 'penyelesaian' => 30, 'tepat_waktu' => 20, 'bukti_lengkap' => 10],
        'ambang_lonjakan_persen' => 150,
        'ambang_jarak_m' => 100,
        'ambang_pembinaan' => 80,
        'ambang_dipantau' => 85,
        'min_lembar_tusbug' => 2,
        'ppj_persen' => 3,
    ];

    $this->actingAs($this->admin)->put('/pengaturan/kpi', $nilai)->assertSessionHasErrors('bobot_kpi');

    $nilai['bobot_kpi']['akurasi'] = 40;
    $nilai['ambang_jarak_m'] = 75;
    $this->actingAs($this->admin)->put('/pengaturan/kpi', $nilai)->assertSessionHasNoErrors();

    expect(Pengaturan::ambil('ambang_jarak_m'))->toBe(75)
        ->and(Pengaturan::where('kunci', 'ambang_jarak_m')->value('diubah_oleh'))->toBe($this->admin->id);
});

function berkasExcel(array $rows): UploadedFile
{
    $export = new class($rows) implements FromArray
    {
        public function __construct(private array $rows) {}

        public function array(): array
        {
            return $this->rows;
        }
    };

    return UploadedFile::fake()->createWithContent('pelanggan.xlsx', Excel::raw($export, Maatwebsite\Excel\Excel::XLSX));
}

test('impor Excel menambah dan memperbarui pelanggan', function () {
    Tarif::factory()->create();
    $unit = Unit::factory()->create();
    $rute = Rute::factory()->create(['unit_id' => $unit->id, 'kode' => 'PNM-09']);
    Pelanggan::factory()->create(['idpel' => '181109000001', 'rute_id' => $rute->id, 'nama' => 'Nama Lama']);

    $header = ['idpel', 'nama', 'alamat', 'kelurahan', 'kode_rute', 'urutan_rute', 'jenis', 'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude', 'status'];
    $file = berkasExcel([
        $header,
        ['181109000001', 'Nama Baru', 'Jl. A No. 1', 'Tuah Karya', 'pnm-09', 1, 'pascabayar', 'R1', 1300, '45999000001', '', 0.465, 101.379, ''],
        ['181109000002', 'Pelanggan Baru', 'Jl. A No. 3', 'Tuah Karya', 'PNM-09', 2, 'prabayar', 'R1', 900, '45999000002', 'Teras', '', '', 'aktif'],
    ]);

    $this->actingAs($this->admin)
        ->post('/pengaturan/pelanggan/impor', ['unit_id' => $unit->id, 'file' => $file])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('impor', fn ($h) => $h['dibuat'] === 1 && $h['diperbarui'] === 1 && $h['errors'] === []);

    expect(Pelanggan::where('idpel', '181109000001')->value('nama'))->toBe('Nama Baru')
        ->and(Pelanggan::where('idpel', '181109000002')->value('jenis')->value)->toBe('prabayar');
});

test('impor dibatalkan seluruhnya bila ada satu baris salah', function () {
    Tarif::factory()->create();
    $unit = Unit::factory()->create();
    Rute::factory()->create(['unit_id' => $unit->id, 'kode' => 'PNM-09']);

    $header = ['idpel', 'nama', 'alamat', 'kelurahan', 'kode_rute', 'urutan_rute', 'jenis', 'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude', 'status'];
    $file = berkasExcel([
        $header,
        ['181109000011', 'Benar', 'Jl. A', 'Tuah Karya', 'PNM-09', 1, 'pascabayar', 'R1', 1300, '45999000011', '', '', '', 'aktif'],
        ['181109000012', 'Salah Rute', 'Jl. B', 'Tuah Karya', 'XXX-01', 2, 'pascabayar', 'R1', 1300, '45999000012', '', '', '', 'aktif'],
    ]);

    $this->actingAs($this->admin)
        ->post('/pengaturan/pelanggan/impor', ['unit_id' => $unit->id, 'file' => $file])
        ->assertSessionHas('impor', fn ($h) => count($h['errors']) === 1 && str_contains($h['errors'][0], 'Baris 3'));

    expect(Pelanggan::count())->toBe(0);
});

test('template impor bisa diunduh', function () {
    $this->actingAs($this->admin)->get('/pengaturan/pelanggan/template')
        ->assertOk()
        ->assertDownload('template-impor-pelanggan.xlsx');
});
