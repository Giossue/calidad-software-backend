<?php
// ============================================================
// app/Models/Observacion.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Observacion extends Model
{
    protected $table      = 'observacion';
    protected $primaryKey = 'id_observacion';
 
    protected $fillable = [
        'fk_asig_tutoria', 'fk_docente',
        'descripcion', 'fecha_registro',
    ];
 
    protected function casts(): array
    {
        return ['fecha_registro' => 'date'];
    }
 
    public function asignaturaTutoria()
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
 
    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'fk_docente');
    }
}
 