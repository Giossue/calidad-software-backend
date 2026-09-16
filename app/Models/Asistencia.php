<?php
// ============================================================
// app/Models/Asistencia.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Asistencia extends Model
{
    protected $table      = 'asistencia';
    protected $primaryKey = 'id_asistencia';
 
    protected $fillable = [
        'fk_inscripcion', 'fk_id_usuario',
        'fecha', 'estado_asistencia',
    ];
 
    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }
 
    public function inscripcion()
    {
        return $this->belongsTo(InscripcionTutoria::class, 'fk_inscripcion');
    }
 
    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
}