<?php

namespace App\Models;

use App\Support\TopicFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Archivo que el docente adjunta a un tema. El contenido vive en el disco de
 * recursos; aquí se guardan su ruta y sus datos para descargarlo.
 *
 * @property int $id
 * @property int $topic_id
 * @property int|null $uploaded_by
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TopicFile extends Model
{
    protected $fillable = ['topic_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size'];

    protected static function booted(): void
    {
        // Al borrar el registro se borra también el archivo del disco.
        static::deleted(fn (TopicFile $file) => TopicFiles::disk()->delete($file->path));
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<Tema, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Tema::class, 'topic_id', 'id_tema');
    }
}
