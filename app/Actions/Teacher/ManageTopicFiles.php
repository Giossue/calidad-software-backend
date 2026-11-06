<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Tema;
use App\Models\TopicFile;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use App\Support\TopicFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageTopicFiles
{
    public function __construct(private TeacherWorkspace $workspace) {}

    public function upload(Usuario $teacher, AsignaturaTutoria $tutoring, Tema $topic, UploadedFile $upload): TopicFile
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $path = TopicFiles::disk()->putFileAs("topic-files/{$tutoring->getKey()}/{$topic->getKey()}", $upload, Str::uuid().'.'.$extension);
        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'No fue posible guardar el archivo. Inténtalo de nuevo.']);
        }

        try {
            return $this->workspace->write($teacher, $tutoring, function () use ($teacher, $tutoring, $topic, $upload, $path): TopicFile {
                $this->workspace->topic($tutoring, $topic);
                $locked = Tema::query()->whereKey($topic->getKey())->lockForUpdate()->firstOrFail();
                if (! $locked->estado) {
                    throw ValidationException::withMessages(['file' => 'El tema está deshabilitado y conserva únicamente su historial.']);
                }
                if ($locked->files()->count() >= TopicFiles::MAX_PER_TOPIC) {
                    throw ValidationException::withMessages(['file' => 'Un tema puede tener como máximo '.TopicFiles::MAX_PER_TOPIC.' archivos.']);
                }

                return TopicFile::query()->create([
                    'topic_id' => $locked->getKey(),
                    'uploaded_by' => $teacher->getKey(),
                    'original_name' => Str::limit($upload->getClientOriginalName(), 250, ''),
                    'path' => $path,
                    'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                    'size' => $upload->getSize(),
                ]);
            });
        } catch (\Throwable $exception) {
            // Si no se registró, el archivo no debe quedar huérfano en el disco.
            TopicFiles::disk()->delete($path);
            throw $exception;
        }
    }

    public function delete(Usuario $teacher, AsignaturaTutoria $tutoring, Tema $topic, TopicFile $file): void
    {
        $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $topic, $file): void {
            $this->workspace->topic($tutoring, $topic);
            abort_unless($file->topic_id === $topic->getKey(), 404);
            if (! $topic->estado) {
                throw ValidationException::withMessages(['file' => 'El tema está deshabilitado y conserva únicamente su historial.']);
            }
            $file->deleteQuietly();
            // El archivo del disco se borra después de confirmar la transacción.
            DB::afterCommit(fn () => TopicFiles::disk()->delete($file->path));
        });
    }
}
