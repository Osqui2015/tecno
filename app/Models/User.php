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
    public const ROLE_SUPER_ADMIN     = 'super-admin';
    public const ROLE_ADMIN           = 'admin';
    public const ROLE_ADMIN_PEDIDOS   = 'admin-pedidos';
    public const ROLE_ADMIN_PRODUCTOS = 'admin-productos';
    public const ROLE_COMPRADOR       = 'comprador';

    /** Roles válidos (útil para validación). */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_ADMIN_PEDIDOS,
        self::ROLE_ADMIN_PRODUCTOS,
        self::ROLE_COMPRADOR,
    ];

    /**
     * Accessors que se incluyen automáticamente al serializar a JSON/array.
     */
    protected $appends = ['role', 'full_name', 'full_address', 'has_complete_profile'];

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
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ============================================================
    //  Helpers de rol (ahora vía Spatie)
    // ============================================================

    /**
     * Devuelve el "role" principal del usuario (para compatibilidad con frontend
     * que espera un string simple). Si tiene varios, devuelve el más alto.
     * Si no tiene ninguno, devuelve 'guest'.
     */
    public function getRoleAttribute(): string
    {
        $role = $this->roles->sortByDesc(fn ($r) => match ($r->name) {
            'super-admin'    => 100,
            'admin'          => 90,
            'admin-pedidos'  => 80,
            'admin-productos'=> 80,
            'comprador'      => 10,
            default          => 0,
        })->first();

        if ($role) {
            return $role->name;
        }

        // Fallback a columna users.role para admins legados sin rol Spatie sincronizado
        $rawRole = $this->getRawOriginal('role');
        if ($rawRole) {
            return $rawRole;
        }

        return 'guest';
    }

    public function isAdmin(): bool
    {
        if ($this->hasRole(['super-admin', 'admin', 'admin-pedidos', 'admin-productos'])) {
            return true;
        }

        $rawRole = $this->getRawOriginal('role');
        return in_array($rawRole, ['super-admin', 'admin', 'admin-pedidos', 'admin-productos'], true);
    }

    public function isComprador(): bool
    {
        return $this->hasRole(self::ROLE_COMPRADOR);
    }

    /**
     * Accessor que Eloquent mapea desde hasCompleteProfile().
     */
    public function getHasCompleteProfileAttribute(): bool
    {
        return $this->hasCompleteProfile();
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
