<?php
// ============================================================
// app/Models/Actividad.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Actividad extends Model
{
    protected $table      = 'actividad';
    protected $primaryKey = 'id_actividad';
 
    protected $fillable = ['fk_tema', 'nombre', 'duracion', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function tema()
    {
        return $this->belongsTo(Tema::class, 'fk_tema');
    }
 
    public function metodologias()
    {
        return $this->hasMany(Metodologia::class, 'fk_actividad');
    }
}