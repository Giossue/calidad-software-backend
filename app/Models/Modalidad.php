<?php
// ============================================================
// app/Models/Modalidad.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Modalidad extends Model
{
    protected $table      = 'modalidad';
    protected $primaryKey = 'id_modalidad';
 
    protected $fillable = ['nombre', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function asignaturasTutoria()
    {
        return $this->hasMany(AsignaturaTutoria::class, 'fk_modalidad');
    }
}