<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Rute;
use App\Models\Unit;
use App\Models\User;

/**
 * Daftar pilihan untuk <select> yang dipakai banyak halaman.
 */
class Opsi
{
    /** @return list<array{id: int, kode: string, nama: string, jenis: string}> */
    public static function unit(): array
    {
        return Unit::query()
            ->orderBy('jenis')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'jenis'])
            ->map(fn (Unit $u) => ['id' => $u->id, 'kode' => $u->kode, 'nama' => $u->nama, 'jenis' => $u->jenis->value])
            ->all();
    }

    /** @return list<array{id: int, kode: string, nama: string, kelurahan: string, unit_id: int}> */
    public static function rute(): array
    {
        return Rute::query()
            ->orderBy('unit_id')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'kelurahan', 'unit_id'])
            ->map->only(['id', 'kode', 'nama', 'kelurahan', 'unit_id'])
            ->all();
    }

    /** Biller aktif. @return list<array{id: int, name: string, nip: string, unit_id: int|null}> */
    public static function biller(): array
    {
        return User::query()
            ->where('role', Role::Biller)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'unit_id'])
            ->map->only(['id', 'name', 'nip', 'unit_id'])
            ->all();
    }
}
