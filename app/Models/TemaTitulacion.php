<?php
// ============================================================
// app/Models/TemaTitulacion.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class TemaTitulacion extends Model
{
    protected $table      = 'tema_titulacion';
    protected $primaryKey = 'id_tema_tit';
 
    protected $fillable = [
        'fk_id_usuario', 'fk_periodo', 'fk_coord_revisor',
        'titulo', 'descripcion', 'estado',
        'fecha_propuesta', 'fecha_revision',
    ];
 
    protected function casts(): array
    {
        return [
            'estado'          => 'boolean',
            'fecha_propuesta' => 'date',
            'fecha_revision'  => 'date',
        ];
    }
 
    public function estudiante()
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
 
    public function periodo()
    {
        return $this->belongsTo(PeriodoAcademico::class, 'fk_periodo');
    }
 
    public function coordinadorRevisor()
    {
        return $this->belongsTo(Usuario::class, 'fk_coord_revisor');
    }
 
    public function asignaciones()
    {
        return $this->hasMany(AsignacionDocente::class, 'fk_tema_tit');
    }
 
    public function fichaSeguimiento()
    {
        return $this->hasOne(FichaSeguimiento::class, 'fk_tema_tit');
    }
 
    public function horarios()
    {
        return $this->hasMany(HorarioTitulacion::class, 'fk_tema_tit');
    }
 
    public function observaciones()
    {
        return $this->hasMany(ObservacionTitulacion::class, 'fk_tema_tit');
    }
}