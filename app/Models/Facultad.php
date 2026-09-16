<?php
// ============================================================
// app/Models/Facultad.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Facultad extends Model
{
    protected $table      = 'facultad';
    protected $primaryKey = 'id_facultad';
 
    protected $fillable = ['nombre', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'fk_facultad');
    }
}