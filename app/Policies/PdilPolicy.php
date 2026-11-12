<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Models\Rute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PdilPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Biller, Role::KoordinatorAdmin], true);
    }

    public function view(User $user, Pdil $pdil): bool
    {
        return match ($user->role) {
            Role::Biller => $pdil->biller_id === $user->id,
            Role::KoordinatorAdmin => Rute::whereKey($pdil->rute_id)->where('unit_id', $user->unit_id)->exists(),
            default => false,
        };
    }

    /** Biller mengusulkan pelanggan baru di rutenya, atau perubahan data pelanggan di rutenya. */
    public function create(User $user, ?Pelanggan $pelanggan = null, ?int $ruteId = null): bool
    {
        if ($user->role !== Role::Biller) {
            return false;
        }

        if ($pelanggan) {
            return DB::query()->fromSub(Invoice::pelangganBiller($user), 'p')->where('id', $pelanggan->id)->exists();
        }

        return $ruteId === null || in_array($ruteId, self::ruteBiller($user), true);
    }

    public function update(User $user, Pdil $pdil): bool
    {
        return $user->role === Role::Biller && $pdil->biller_id === $user->id && $pdil->bisaDiubahBiller();
    }

    public function verifikasi(User $user, Pdil $pdil): bool
    {
        return $user->role === Role::KoordinatorAdmin && $this->view($user, $pdil);
    }

    /** Rute yang dipegang biller atau ditugaskan kepadanya pada periode berjalan. @return list<int> */
    public static function ruteBiller(User $user): array
    {
        return DB::table('rute')->where('biller_id', $user->id)->pluck('id')
            ->merge(DB::table('penugasan')
                ->join('periode', 'periode.id', '=', 'penugasan.periode_id')
                ->where('periode.status', 'berjalan')
                ->where('penugasan.biller_id', $user->id)
                ->pluck('penugasan.rute_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
