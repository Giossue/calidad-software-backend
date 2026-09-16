<?php
// ============================================================
// app/Models/Horario.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Horario extends Model
{
    protected $table      = 'horario';
    protected $primaryKey = 'id_horario';
 
    protected $fillable = [
        'fk_asig_tutoria', 'dia_semana',
        'hora_inicio', 'hora_fin', 'estado',
    ];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function asignaturaTutoria()
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
}