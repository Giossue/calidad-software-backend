<?php
// ============================================================
// app/Models/InformeTitulacion.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class InformeTitulacion extends Model
{
    protected $table      = 'informe_titulacion';
    protected $primaryKey = 'id_informe';
 
    protected $fillable = [
        'fk_ficha', 'fk_coord_tit',
        'fecha_generacion', 'observaciones_finales', 'estado',
    ];
 
    protected function casts(): array
    {
        return [
            'estado'           => 'boolean',
            'fecha_generacion' => 'date',
        ];
    }
 
    public function ficha()
    {
        return $this->belongsTo(FichaSeguimiento::class, 'fk_ficha');
    }
 
    public function coordinador()
    {
        return $this->belongsTo(Usuario::class, 'fk_coord_tit');
    }
}