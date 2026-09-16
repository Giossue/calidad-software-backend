<?php
// ============================================================
// app/Models/Metodologia.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Metodologia extends Model
{
    protected $table      = 'metodologia';
    protected $primaryKey = 'id_metodologia';
 
    protected $fillable = ['fk_actividad', 'descripcion', 'estado'];
 
    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
 
    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'fk_actividad');
    }
}