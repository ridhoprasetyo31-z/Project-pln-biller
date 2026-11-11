<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisUnit: string
{
    use HasOptions;

    case Up3 = 'up3';
    case Ulp = 'ulp';

    public function label(): string
    {
        return match ($this) {
            self::Up3 => 'UP3',
            self::Ulp => 'ULP',
        };
    }
}
