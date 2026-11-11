<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\AuditMaster;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditMaster, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'nip',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'foto_profil_path',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Rute yang dipegang biller ini. */
    public function rute(): HasMany
    {
        return $this->hasMany(Rute::class, 'biller_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class, 'biller_id');
    }

    public function scopeBiller(Builder $query): void
    {
        $query->where('role', Role::Biller);
    }

    public function scopeAktif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if (($role instanceof Role ? $role : Role::tryFrom($role)) === $this->role) {
                return true;
            }
        }

        return false;
    }

    public function fotoProfilUrl(): ?string
    {
        return $this->foto_profil_path
            ? Storage::disk('public')->url($this->foto_profil_path)
            : null;
    }
}
