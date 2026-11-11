<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case Biller = 'biller';
    case KoordinatorAdmin = 'koordinator_admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Biller => 'Biller',
            self::KoordinatorAdmin => 'Koordinator admin',
            self::SuperAdmin => 'Super admin',
        };
    }

    /** Nama route tujuan setelah login. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Biller => 'tugas.index',
            default => 'dashboard',
        };
    }
}
