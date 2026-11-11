<?php

namespace Database\Factories;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periode>
 */
class PeriodeFactory extends Factory
{
    public function definition(): array
    {
        $bulan = now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(0, 60));

        return [
            'kode' => $bulan->format('Ym'),
            'tanggal_mulai' => $bulan->toDateString(),
            'tanggal_selesai' => $bulan->copy()->endOfMonth()->toDateString(),
            'status' => StatusPeriode::Draft,
        ];
    }

    public function berjalan(): static
    {
        return $this->state(fn () => ['status' => StatusPeriode::Berjalan]);
    }
}
