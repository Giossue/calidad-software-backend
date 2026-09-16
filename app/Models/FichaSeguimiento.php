<?php
// ============================================================
// app/Models/FichaSeguimiento.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class FichaSeguimiento extends Model
{
    protected $table      = 'ficha_seguimiento';
    protected $primaryKey = 'id_ficha';
 
    protected $fillable = [
        'fk_tema_tit', 'fecha_apertura',
        'porcentaje_avance', 'estado',
    ];
 
    protected function casts(): array
    {
        return [
            'fecha_apertura'    => 'date',
            'porcentaje_avance' => 'decimal:2',
        ];
    }
 
    public function temaTitulacion()
    {
        return $this->belongsTo(TemaTitulacion::class, 'fk_tema_tit');
    }
 
    public function actividades()
    {
        return $this->hasMany(ActividadAvance::class, 'fk_ficha');
    }
 
    public function informes()
    {
        return $this->hasMany(InformeTitulacion::class, 'fk_ficha');
    }
}