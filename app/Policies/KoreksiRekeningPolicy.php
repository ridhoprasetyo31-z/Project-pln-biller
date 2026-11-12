<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Models\User;

class KoreksiRekeningPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Biller, Role::KoordinatorAdmin], true);
    }

    public function view(User $user, KoreksiRekening $koreksi): bool
    {
        return match ($user->role) {
            Role::Biller => $koreksi->diajukan_oleh === $user->id,
            Role::KoordinatorAdmin => KoreksiRekening::query()->whereKey($koreksi->id)->diUnit($user->unit_id)->exists(),
            default => false,
        };
    }

    /** Biller mengajukan koreksi untuk invoice pelanggan di rutenya yang belum lunas. */
    public function create(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::Biller
            && in_array($invoice->status, [StatusInvoice::BelumBayar, StatusInvoice::Menunggak], true)
            && Invoice::query()->whereKey($invoice->id)->terlihatOleh($user)->exists();
    }

    public function update(User $user, KoreksiRekening $koreksi): bool
    {
        return $user->role === Role::Biller && $koreksi->diajukan_oleh === $user->id && $koreksi->bisaDiubahBiller();
    }

    public function verifikasi(User $user, KoreksiRekening $koreksi): bool
    {
        return $user->role === Role::KoordinatorAdmin
            && KoreksiRekening::query()->whereKey($koreksi->id)->diUnit($user->unit_id)->exists();
    }
}
