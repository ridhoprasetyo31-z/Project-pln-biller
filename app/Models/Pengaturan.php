<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pengaturan KPI berbentuk kunci → nilai JSON.
 * Nilai di tabel menang atas nilai bawaan config/kinerja.php.
 */
class Pengaturan extends Model
{
    use LogsActivity;

    public const KUNCI = [
        'bobot_kpi',
        'ambang_lonjakan_persen',
        'ambang_jarak_m',
        'ambang_pembinaan',
        'ambang_dipantau',
        'min_lembar_tusbug',
        'ppj_persen',
    ];

    private const CACHE_KEY = 'pengaturan.semua';

    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('pengaturan')->logOnly(['kunci', 'nilai'])->logOnlyDirty();
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /** Ambil satu nilai pengaturan. */
    public static function ambil(string $kunci, mixed $default = null): mixed
    {
        return static::semua()[$kunci] ?? $default;
    }

    /** Semua pengaturan: bawaan config ditimpa nilai tersimpan. */
    public static function semua(): array
    {
        $tersimpan = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('nilai', 'kunci')->all());

        $hasil = [];
        foreach (self::KUNCI as $kunci) {
            $hasil[$kunci] = $tersimpan[$kunci] ?? config("kinerja.{$kunci}");
        }

        return $hasil;
    }

    public static function simpan(string $kunci, mixed $nilai, ?int $userId = null): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai, 'diubah_oleh' => $userId]);
    }
}
