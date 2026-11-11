<?php

namespace App\Services;

use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use Carbon\CarbonInterface;

/**
 * Data yang diperiksa AnomalyDetector, terpisah dari model agar mudah diuji.
 */
final class DataPemeriksaan
{
    public function __construct(
        public JenisPelanggan $jenis,
        public KondisiMeter $kondisi,
        public ?float $standLalu,
        public ?float $standKini,
        public ?float $rataTigaBulan,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $jarakM,
        public bool $adaFoto,
        public CarbonInterface $dikirimAt,
        public CarbonInterface $batasPencatatan,
    ) {}

    public function pemakaian(): ?float
    {
        return ($this->standKini !== null && $this->standLalu !== null)
            ? round($this->standKini - $this->standLalu, 1)
            : null;
    }
}
