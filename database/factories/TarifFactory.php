<?php

namespace Database\Factories;

use App\Models\Tarif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarif>
 */
class TarifFactory extends Factory
{
    public function definition(): array
    {
        return [
            'golongan' => 'R1',
            'daya_min_va' => 450,
            'daya_max_va' => 5500,
            'harga_per_kwh' => 1444.70,
            'biaya_beban' => 0,
            'berlaku_mulai' => '2025-01-01',
            'berlaku_sampai' => null,
        ];
    }
}
