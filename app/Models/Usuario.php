<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id_usuario
 * @property string $cedula
 * @property string $nombre
 * @property string $correo
 * @property bool $estado
 * @property Carbon|null $email_verified_at
 * @property string $password_hash
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cedula', 'nombre', 'correo', 'telefono', 'password_hash', 'estado', 'email_verified_at'])]
#[Hidden(['password_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Usuario extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function getEmailForVerification(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id', 'id_usuario', 'id')
            ->withPivot('assigned_at');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * @param  array<int, string>  $slugs
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /**
     * @return Collection<int, string>
     */
    public function roleSlugs(): Collection
    {
        return $this->roles->pluck('slug');
    }

    /**
     * @return HasMany<InscripcionTutoria, $this>
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionTutoria::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<AsignaturaTutoria, $this>
     */
    public function asignaturasTutoria(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_docente');
    }

    /**
     * @return BelongsToMany<Paralelo, $this>
     */
    public function paralelos(): BelongsToMany
    {
        return $this->belongsToMany(
            Paralelo::class,
            'usuario_paralelo',
            'fk_usuario',
            'fk_paralelo',
        )->withPivot('fecha_asignacion', 'estado')->withTimestamps();
    }

    /**
     * @return HasMany<MetricaConocimiento, $this>
     */
    public function metricas(): HasMany
    {
        return $this->hasMany(MetricaConocimiento::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<TemaTitulacion, $this>
     */
    public function temasTitulacion(): HasMany
    {
        return $this->hasMany(TemaTitulacion::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<AsignacionDocente, $this>
     */
    public function asignacionesDocente(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class, 'fk_id_usuario');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'estado' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
