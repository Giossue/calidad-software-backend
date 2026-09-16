<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id_usuario
 * @property string $cedula
 * @property string $nombre
 * @property string $correo
 * @property string $rol
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
#[Fillable(['cedula', 'nombre', 'correo', 'telefono', 'password_hash', 'rol', 'estado'])]
#[Hidden(['password_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
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
     * @return HasMany<InscripcionTutoria, $this>
     */
    public function tutoringEnrollments(): HasMany
    {
        return $this->hasMany(InscripcionTutoria::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<AsignaturaTutoria, $this>
     */
    public function tutoringSubjects(): HasMany
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_docente');
    }

    /**
     * @return BelongsToMany<Paralelo, $this>
     */
    public function parallels(): BelongsToMany
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
    public function knowledgeMetrics(): HasMany
    {
        return $this->hasMany(MetricaConocimiento::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<TemaTitulacion, $this>
     */
    public function degreeTopics(): HasMany
    {
        return $this->hasMany(TemaTitulacion::class, 'fk_id_usuario');
    }

    /**
     * @return HasMany<AsignacionDocente, $this>
     */
    public function teachingAssignments(): HasMany
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
