<?php
// ============================================================
// app/Models/UsuarioParalelo.php
// ============================================================
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
 
class UsuarioParalelo extends Model
{
    protected $table      = 'usuario_paralelo';
    protected $primaryKey = 'id_usr_paralelo';
 
    protected $fillable = [
        'fk_usuario', 'fk_paralelo',
        'fecha_asignacion', 'estado',
    ];
 
    protected function casts(): array
    {
        return [
            'estado'           => 'boolean',
            'fecha_asignacion' => 'date',
        ];
    }
 
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'fk_usuario');
    }
 
    public function paralelo()
    {
        return $this->belongsTo(Paralelo::class, 'fk_paralelo');
    }
}