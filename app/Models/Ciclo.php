<?php
// ============================================================
// app/Models/Ciclo.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Ciclo extends Model
{
    protected $table      = 'ciclo';
    protected $primaryKey = 'id_ciclo';
 
    protected $fillable = ['fk_carrera', 'nombre', 'numero', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean', 'numero' => 'integer'];
    }
 
    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'fk_carrera');
    }
 
    public function periodos()
    {
        return $this->belongsToMany(
            PeriodoAcademico::class,
            'ciclo_periodo',
            'fk_ciclo',
            'fk_periodo'
        )->withPivot('estado')->withTimestamps();
    }
 
    public function asignaturasTutoria()
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_ciclo');
    }
}
