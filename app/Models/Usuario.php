<?php
// ============================================================
// app/Models/Usuario.php
// ============================================================
namespace App\Models;
 
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
 
class Usuario extends Authenticatable
{
    use HasApiTokens;
 
    protected $table      = 'usuario';
    protected $primaryKey = 'id_usuario';
 
    protected $fillable = [
        'cedula', 'nombre', 'correo',
        'telefono', 'password_hash', 'rol', 'estado',
    ];
 
    protected $hidden = ['password_hash', 'remember_token'];
 
    protected function casts(): array
    {
        return [
            'estado'            => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }
 
    // Relaciones
    public function inscripciones()
    {
        return $this->hasMany(InscripcionTutoria::class, 'fk_id_usuario');
    }
 
    public function asignaturasTutoria()
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_docente');
    }
 
    public function paralelos()
    {
        return $this->belongsToMany(
            Paralelo::class,
            'usuario_paralelo',
            'fk_usuario',
            'fk_paralelo'
        )->withPivot('fecha_asignacion', 'estado')->withTimestamps();
    }
 
    public function metricas()
    {
        return $this->hasMany(MetricaConocimiento::class, 'fk_id_usuario');
    }
 
    public function temasTitulacion()
    {
        return $this->hasMany(TemaTitulacion::class, 'fk_id_usuario');
    }
 
    public function asignacionesDocente()
    {
        return $this->hasMany(AsignacionDocente::class, 'fk_id_usuario');
    }
}