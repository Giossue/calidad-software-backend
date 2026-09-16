<?php
// ============================================================
// app/Models/Carrera.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Carrera extends Model
{
    protected $table      = 'carrera';
    protected $primaryKey = 'id_carrera';
 
    protected $fillable = ['fk_facultad', 'nombre', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'fk_facultad');
    }
 
    public function ciclos()
    {
        return $this->hasMany(Ciclo::class, 'fk_carrera');
    }
}