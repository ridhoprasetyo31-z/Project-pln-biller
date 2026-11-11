<?php

namespace App\Services;

/**
 * Jarak dua titik GPS memakai rumus haversine.
 */
class GeoDistance
{
    private const RADIUS_BUMI_M = 6_371_000;

    /** Jarak dalam meter, atau null bila salah satu titik tidak lengkap. */
    public static function meter(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::RADIUS_BUMI_M * asin(min(1, sqrt($a)));
    }
}
