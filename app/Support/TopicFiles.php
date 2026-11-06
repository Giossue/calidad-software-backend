<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/** Reglas y disco de los recursos que se adjuntan a los temas de tutoría. */
final class TopicFiles
{
    /** Tamaño máximo por archivo, en kilobytes (20 MB). */
    public const MAX_KILOBYTES = 20 * 1024;

    public const MAX_PER_TOPIC = 10;

    /** PDF, Word, PowerPoint, Excel (y sus equivalentes de OpenDocument) y texto. */
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'ods', 'txt'];

    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.topic_files_disk'));
    }
}
