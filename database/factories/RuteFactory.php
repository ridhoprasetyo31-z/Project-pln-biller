<?php

namespace Database\Factories;

use App\Models\Rute;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rute>
 */
class RuteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'kode' => 'R-'.fake()->unique()->numerify('###'),
            'nama' => 'Rute '.fake()->streetName(),
            'kelurahan' => fake()->citySuffix().' '.fake()->lastName(),
            'biller_id' => null,
            'is_active' => true,
        ];
    }
}
