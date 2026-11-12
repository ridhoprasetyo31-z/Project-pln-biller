<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\KunjunganPenagihan;
use App\Models\User;

class KunjunganPenagihanPolicy
{
    /** Biller mencatat kunjungan untuk invoice pelanggan di rutenya yang masih bisa ditagih. */
    public function create(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::Biller
            && $invoice->bisaDitagih()
            && Invoice::query()->whereKey($invoice->id)->terlihatOleh($user)->exists();
    }

    /** Biller memperbaiki kunjungannya sendiri yang dikembalikan. */
    public function update(User $user, KunjunganPenagihan $kunjungan): bool
    {
        return $user->role === Role::Biller && $kunjungan->biller_id === $user->id && $kunjungan->bisaDiubahBiller();
    }

    public function verifikasi(User $user, KunjunganPenagihan $kunjungan): bool
    {
        return $user->role === Role::KoordinatorAdmin
            && KunjunganPenagihan::query()->whereKey($kunjungan->id)->diUnit($user->unit_id)->exists();
    }
}
