<?php
// ============================================================
// app/Models/Reporte.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Reporte extends Model
{
    protected $table      = 'reporte';
    protected $primaryKey = 'id_reporte';
 
    protected $fillable = [
        'fk_asig_tutoria', 'tipo_reporte',
        'fk_id_usuario', 'fecha_generacion',
    ];
 
    protected function casts(): array
    {
        return ['fecha_generacion' => 'datetime'];
    }
 
    public function asignaturaTutoria()
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
 
    public function generadoPor()
    {
        return $this->belongsTo(Usuario::class, 'fk_id_usuario');
    }
}