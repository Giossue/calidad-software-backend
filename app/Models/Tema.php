<?php
// ============================================================
// app/Models/Tema.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Tema extends Model
{
    protected $table      = 'tema';
    protected $primaryKey = 'id_tema';
 
    protected $fillable = [
        'fk_asig_tutoria', 'nombre',
        'descripcion', 'visto', 'estado',
    ];
 
    protected function casts(): array
    {
        return ['visto' => 'boolean', 'estado' => 'boolean'];
    }
 
    public function asignaturaTutoria()
    {
        return $this->belongsTo(AsignaturaTutoria::class, 'fk_asig_tutoria');
    }
 
    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'fk_tema');
    }
}