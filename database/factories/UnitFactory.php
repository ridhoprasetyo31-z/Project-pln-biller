<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->numerify('#####'),
            'nama' => 'ULP '.fake()->city(),
            'jenis' => 'ulp',
        ];
    }
}
