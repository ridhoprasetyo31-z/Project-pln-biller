<?php

namespace App\Enums\Concerns;

/**
 * Bantuan bersama untuk enum ber-label.
 *
 * @mixin \BackedEnum
 */
trait HasOptions
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Pilihan untuk <select> di frontend.
     *
     * @param  list<self>|null  $cases
     * @return list<array{value: string, label: string}>
     */
    public static function options(?array $cases = null): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            $cases ?? self::cases(),
        );
    }
}
