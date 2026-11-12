<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Biller, Role::KoordinatorAdmin], true);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && Invoice::query()->whereKey($invoice->id)->terlihatOleh($user)->exists();
    }

    /** Koordinator menandai lunas (pembayaran di loket/bank). */
    public function tandaiLunas(User $user, Invoice $invoice): bool
    {
        return $user->role === Role::KoordinatorAdmin && $invoice->bisaDitagih() && $this->view($user, $invoice);
    }

    /** Koordinator menyusulkan invoice yang belum terbit. */
    public function buatPeriode(User $user): bool
    {
        return $user->role === Role::KoordinatorAdmin;
    }
}
