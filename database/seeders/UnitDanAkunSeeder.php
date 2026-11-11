<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 1 UP3, 2 ULP, 1 super admin, 2 koordinator, 8 biller.
 * Kata sandi semua akun demo: "password".
 */
class UnitDanAkunSeeder extends Seeder
{
    public function run(): void
    {
        $up3 = Unit::updateOrCreate(['kode' => '18100'], [
            'nama' => 'UP3 Pekanbaru',
            'jenis' => 'up3',
            'alamat' => 'Jl. Setia Budi No. 96, Pekanbaru',
        ]);

        $panam = Unit::updateOrCreate(['kode' => '18110'], [
            'nama' => 'ULP Panam',
            'jenis' => 'ulp',
            'parent_id' => $up3->id,
            'alamat' => 'Jl. HR Soebrantas, Tampan, Pekanbaru',
        ]);

        $rumbai = Unit::updateOrCreate(['kode' => '18120'], [
            'nama' => 'ULP Rumbai',
            'jenis' => 'ulp',
            'parent_id' => $up3->id,
            'alamat' => 'Jl. Yos Sudarso, Rumbai, Pekanbaru',
        ]);

        $akun = [
            ['198001012005011001', 'Budi Santoso', 'admin@biller.test', Role::SuperAdmin, $up3],
            ['198503152010012002', 'Rina Wulandari', 'koordinator@biller.test', Role::KoordinatorAdmin, $panam],
            ['198709102011011004', 'Hendra Gunawan', 'koordinator.rumbai@biller.test', Role::KoordinatorAdmin, $rumbai],
            // Biller ULP Panam
            ['199207202018011003', 'Andi Saputra', 'biller@biller.test', Role::Biller, $panam],
            ['199403112019012005', 'Dewi Lestari', 'dewi@biller.test', Role::Biller, $panam],
            ['199105052017011006', 'Rudi Hartono', 'rudi@biller.test', Role::Biller, $panam],
            ['199512242020012007', 'Siti Aminah', 'siti@biller.test', Role::Biller, $panam],
            // Biller ULP Rumbai
            ['199308172018011008', 'Fajar Nugroho', 'fajar@biller.test', Role::Biller, $rumbai],
            ['199602022021012009', 'Yuni Kartika', 'yuni@biller.test', Role::Biller, $rumbai],
            ['199011302016011010', 'Agus Salim', 'agus@biller.test', Role::Biller, $rumbai],
            ['199707142022012011', 'Maya Sari', 'maya@biller.test', Role::Biller, $rumbai],
        ];

        foreach ($akun as $i => [$nip, $name, $email, $role, $unit]) {
            User::updateOrCreate(['nip' => $nip], [
                'name' => $name,
                'email' => $email,
                'phone' => sprintf('0812%08d', $i + 1),
                'role' => $role,
                'unit_id' => $unit->id,
                'password' => 'password',
                'is_active' => true,
            ]);
        }
    }
}
