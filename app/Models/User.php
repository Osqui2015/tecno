<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /** Roles disponibles en el sistema. */
    public const ROLE_COMPRADOR = 'comprador';
    public const ROLE_ADMIN     = 'admin';

    /** Roles válidos (útil para validación). */
    public const ROLES = [
        self::ROLE_COMPRADOR,
        self::ROLE_ADMIN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'lastname',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'city',
        'zip_code',
        'country',
        'document_number',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'role'                   => 'string',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ============================================================
    //  Helpers de rol
    // ============================================================

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isComprador(): bool
    {
        return $this->role === self::ROLE_COMPRADOR;
    }

    /**
     * Nombre completo para mostrar (útil en checkout / admin).
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . ($this->lastname ?? ''));
    }

    /**
     * Dirección completa en una línea (para snapshot del pedido).
     */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address,
            $this->city,
            $this->zip_code,
            $this->country,
        ])->filter()->implode(', ');
    }

    /**
     * ¿Tiene los datos mínimos para hacer checkout?
     */
    public function hasCompleteProfile(): bool
    {
        return filled($this->name)
            && filled($this->lastname)
            && filled($this->phone)
            && filled($this->address)
            && filled($this->city);
    }

    /**
     * ¿Tiene 2FA confirmado?
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && $this->two_factor_secret !== null;
    }

    // ============================================================
    //  Relaciones
    // ============================================================

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
