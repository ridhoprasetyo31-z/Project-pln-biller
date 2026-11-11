<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder demo tidak perlu masuk log audit.
        config(['activitylog.enabled' => false]);

        $this->call([
            UnitDanAkunSeeder::class,
            RuteDanPelangganSeeder::class,
            TarifSeeder::class,
            PengaturanSeeder::class,
            PeriodeSeeder::class,
            RiwayatPencatatanSeeder::class,
            AktivitasBerjalanSeeder::class,
        ]);

        config(['activitylog.enabled' => true]);
    }
}
