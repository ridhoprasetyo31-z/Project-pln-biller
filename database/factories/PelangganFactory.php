<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\Rute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idpel' => fake()->unique()->numerify('18##########'),
            'rute_id' => Rute::factory(),
            'urutan_rute' => fake()->unique()->numberBetween(1, 9999),
            'nama' => fake()->name(),
            'alamat' => fake()->streetAddress(),
            'kelurahan' => fake()->citySuffix(),
            'jenis' => 'pascabayar',
            'golongan_tarif' => 'R1',
            'daya_va' => 1300,
            'nomor_meter' => fake()->unique()->numerify('45#########'),
            'letak_meter' => 'Teras depan',
            'latitude' => 0.4650000,
            'longitude' => 101.3790000,
            'status' => 'aktif',
        ];
    }
}
