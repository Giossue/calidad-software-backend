<?php
// ============================================================
// app/Models/InscripcionTutoria.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class InscripcionTutoria extends Model
{
    protected $table      = 'inscripcion_tutoria';
    protected $primaryKey = 'id_inscripcion';
 
    protected $fillable = [
        'fk_asig_tutoria', 'fk_id_usuario',
        'fecha_inscripcion', 'estado',
    ];
 
    protected function casts(): array
    {
        return [
            'estado'            => 'boolean',
            'fecha_inscripcion' => 'date',
        ];
    }
 
    public function asignaturaTutoria()
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
 
    public function estudiante()
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
 
    public function notas()
    {
        return $this->hasMany(Nota::class, 'fk_inscripcion');
    }
 
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'fk_inscripcion');
    }
}