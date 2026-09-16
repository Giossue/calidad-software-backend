<?php
// ============================================================
// app/Models/PeriodoAcademico.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class PeriodoAcademico extends Model
{
    protected $table      = 'periodo_academico';
    protected $primaryKey = 'id_periodo';
 
    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado'];
 
    protected function casts(): array
    {
        return [
            'estado'       => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }
 
    public function ciclos()
    {
        return $this->belongsToMany(
            Ciclo::class,
            'ciclo_periodo',
            'fk_periodo',
            'fk_ciclo'
        )->withPivot('estado')->withTimestamps();
    }
 
    public function asignaturasTutoria()
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_periodo');
    }
}