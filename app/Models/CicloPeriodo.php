<?php
// ============================================================
// app/Models/CicloPeriodo.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class CicloPeriodo extends Model
{
    protected $table      = 'ciclo_periodo';
    protected $primaryKey = null;
    public    $incrementing = false;
 
    protected $fillable = ['fk_ciclo', 'fk_periodo', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function ciclo()
    {
        return $this->belongsTo(Ciclo::class, 'fk_ciclo');
    }
 
    public function periodo()
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }
}