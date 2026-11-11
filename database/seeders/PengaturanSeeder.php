<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

/** Salin nilai bawaan config/kinerja.php ke tabel pengaturan. */
class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Pengaturan::KUNCI as $kunci) {
            Pengaturan::firstOrCreate(['kunci' => $kunci], ['nilai' => config("kinerja.{$kunci}")]);
        }
    }
}
