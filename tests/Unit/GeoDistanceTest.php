<?php

use App\Services\GeoDistance;

test('jarak titik yang sama adalah nol', function () {
    expect(GeoDistance::meter(0.465, 101.379, 0.465, 101.379))->toBe(0.0);
});

test('jarak 0,001 derajat lintang sekitar 111 meter', function () {
    expect(GeoDistance::meter(0.465, 101.379, 0.466, 101.379))->toEqualWithDelta(111.2, 0.5);
});

test('jarak Pekanbaru ke Jakarta sekitar 950 km', function () {
    // Tugu Zapin Pekanbaru → Monas Jakarta.
    $km = GeoDistance::meter(0.5071, 101.4478, -6.1754, 106.8272) / 1000;
    expect($km)->toEqualWithDelta(951, 10);
});

test('titik tidak lengkap menghasilkan null', function () {
    expect(GeoDistance::meter(null, 101.379, 0.465, 101.379))->toBeNull()
        ->and(GeoDistance::meter(0.465, 101.379, 0.465, null))->toBeNull();
});
