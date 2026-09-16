<?php
// ============================================================
// app/Models/AsignaturaTutoria.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class AsignaturaTutoria extends Model
{
    protected $table      = 'asignatura_tutoria';
    protected $primaryKey = 'id_asig_tutoria';
 
    protected $fillable = [
        'fk_ciclo', 'fk_periodo', 'fk_modalidad',
        'fk_paralelo', 'fk_docente', 'nombre', 'estado',
    ];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function ciclo()       { return $this->belongsTo(Ciclo::class, 'fk_ciclo'); }
    public function periodo()     { return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo'); }
    public function modalidad()   { return $this->belongsTo(Modalidad::class, 'fk_modalidad'); }
    public function paralelo()    { return $this->belongsTo(Paralelo::class, 'fk_paralelo'); }
    public function docente()     { return $this->belongsTo(Usuario::class, 'fk_docente'); }
    public function horarios()    { return $this->hasMany(Horario::class, 'fk_asig_tutoria'); }
    public function temas()       { return $this->hasMany(Tema::class, 'fk_asig_tutoria'); }
    public function inscripciones() { return $this->hasMany(InscripcionTutoria::class, 'fk_asig_tutoria'); }
    public function observaciones() { return $this->hasMany(Observacion::class, 'fk_asig_tutoria'); }
    public function reportes()    { return $this->hasMany(Reporte::class, 'fk_asig_tutoria'); }
}
 