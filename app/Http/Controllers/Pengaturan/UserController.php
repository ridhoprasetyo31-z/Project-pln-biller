<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UserRequest;
use App\Models\User;
use App\Support\Opsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'unit_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        $users = User::query()
            ->with('unit:id,kode,nama')
            ->withCount('rute')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('nip', 'like', "{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['unit_id'] ?? null, fn ($q, $unit) => $q->where('unit_id', $unit))
            ->when(($filters['status'] ?? null) === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->orderByRaw("FIELD(role, 'super_admin', 'koordinator_admin', 'biller')")
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'nip' => $u->nip,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'unit_id' => $u->unit_id,
                'unit' => $u->unit?->nama,
                'is_active' => $u->is_active,
                'foto_url' => $u->fotoProfilUrl(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'rute_count' => $u->rute_count,
            ]);

        return Inertia::render('Pengaturan/Users/Index', [
            'users' => $users,
            'filters' => (object) $filters,
            'options' => [
                'roles' => Role::options(),
                'units' => Opsi::unit(),
            ],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return back()->with('success', "Pengguna {$user->name} ditambahkan.");
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', "Data {$user->name} diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $rute = $user->rute()->pluck('kode');
        if ($rute->isNotEmpty()) {
            return back()->with('error', "{$user->name} masih memegang rute {$rute->join(', ')}. Ganti biller rute tersebut lebih dulu.");
        }

        $user->delete();

        return back()->with('success', "Pengguna {$user->name} dihapus.");
    }
}
