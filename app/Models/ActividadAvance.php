<?php
// ============================================================
// app/Models/ActividadAvance.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class ActividadAvance extends Model
{
    protected $table      = 'actividad_avance';
    protected $primaryKey = 'id_actividad_av';
 
    protected $fillable = [
        'fk_ficha', 'fk_docente',
        'descripcion', 'completada', 'fecha_registro',
    ];
 
    protected function casts(): array
    {
        return [
            'completada'      => 'boolean',
            'fecha_registro'  => 'date',
        ];
    }
 
    public function ficha()
    {
        return $this->belongsTo(FichaSeguimiento::class, 'fk_ficha');
    }
 
    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'fk_docente');
    }
}