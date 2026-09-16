<?php
// ============================================================
// app/Models/AsignacionDocente.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class AsignacionDocente extends Model
{
    protected $table      = 'asignacion_docente';
    protected $primaryKey = 'id_asignacion';
 
    protected $fillable = [
        'fk_tema_tit', 'fk_id_usuario',
        'rol', 'fecha_asignacion', 'estado',
    ];
 
    protected function casts(): array
    {
        return [
            'estado'           => 'boolean',
            'fecha_asignacion' => 'date',
        ];
    }
 
    public function temaTitulacion()
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }
 
    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
}