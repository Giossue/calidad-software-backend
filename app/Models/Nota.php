<?php
// ============================================================
// app/Models/Nota.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class Nota extends Model
{
    protected $table      = 'nota';
    protected $primaryKey = 'id_nota';
 
    protected $fillable = [
        'fk_inscripcion', 'tipo', 'valor', 'fecha_registro',
    ];
 
    protected function casts(): array
    {
        return [
            'valor'          => 'decimal:2',
            'fecha_registro' => 'date',
        ];
    }
 
    public function inscripcion()
    {
        return $this->belongsTo(InscripcionTutoria::class, 'fk_inscripcion');
    }
}