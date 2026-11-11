<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Models\PencatatanMeter;
use App\Models\User;

/**
 * Jumlah pekerjaan yang menunggu tindakan pengguna, untuk badge merah di menu.
 * Kunci hasil = id item navigasi (resources/js/lib/navigation.ts).
 */
class ActionInboxService
{
    /** @return array<string, int> */
    public function badges(User $user): array
    {
        return match ($user->role) {
            Role::Biller => [
                'revisi' => PencatatanMeter::query()
                    ->where('biller_id', $user->id)
                    ->where('status', StatusCatatan::Revisi)
                    ->periodeTerbuka()
                    ->count(),
            ],
            Role::KoordinatorAdmin => [
                'verifikasi' => PencatatanMeter::query()
                    ->diUnit($user->unit_id)
                    ->menungguVerifikasi()
                    ->periodeTerbuka()
                    ->count(),
            ],
            default => [],
        };
    }
}
