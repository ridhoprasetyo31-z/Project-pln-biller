<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('unit');

        return Inertia::render('Profile/Edit', [
            'profil' => [
                'nip' => $user->nip,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_label' => $user->role->label(),
                'unit' => $user->unit?->nama,
                'foto_url' => $user->fotoProfilUrl(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request, ImageStore $images): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'phone']));

        if ($request->hasFile('foto')) {
            $images->delete($user->foto_profil_path);
            $user->foto_profil_path = $images->storeSquare($request->file('foto'), 'foto-profil');
        } elseif ($request->boolean('hapus_foto')) {
            $images->delete($user->foto_profil_path);
            $user->foto_profil_path = null;
        }

        $user->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}
