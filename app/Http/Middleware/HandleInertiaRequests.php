<?php

namespace App\Http\Middleware;

use App\Services\ActionInboxService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('unit');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nip' => $user->nip,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'foto_url' => $user->fotoProfilUrl(),
                    'unit' => $user->unit ? [
                        'id' => $user->unit->id,
                        'kode' => $user->unit->kode,
                        'nama' => $user->unit->nama,
                    ] : null,
                ] : null,
            ],
            // Jumlah antrean per menu (diisi ActionInboxService mulai Tahap 3).
            'navBadges' => fn () => $user ? (object) app(ActionInboxService::class)->badges($user) : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Ringkasan hasil impor Excel pelanggan.
                'impor' => fn () => $request->session()->get('impor'),
            ],
        ];
    }
}
