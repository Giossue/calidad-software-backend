<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Tema;
use App\Models\TopicFile;
use App\Models\Usuario;
use App\Support\TopicFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class TopicFileTest extends TeacherTestCase
{
    private Tema $topic;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->topic = Tema::query()->create(['fk_asig_tutoria' => $this->tutoring->getKey(), 'nombre' => 'Pruebas', 'visto' => false, 'estado' => true]);
    }

    private function filesPath(string $suffix = ''): string
    {
        return $this->path("/topics/{$this->topic->getKey()}/files{$suffix}");
    }

    private function pdf(string $name = 'Guía de pruebas.pdf', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_teacher_uploads_lists_downloads_and_deletes_topic_files(): void
    {
        $id = $this->post($this->filesPath(), ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.name', 'Guía de pruebas.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.size', 100 * 1024)
            ->assertJsonMissingPath('data.path')
            ->json('data.id');
        $this->post($this->filesPath(), ['file' => UploadedFile::fake()->create('Clase.pptx', 50, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')], ['Accept' => 'application/json'])->assertCreated();

        $file = TopicFile::query()->findOrFail($id);
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith("topic-files/{$this->tutoring->getKey()}/{$this->topic->getKey()}/", $file->path);

        $this->getJson($this->path('/topics'))->assertOk()
            ->assertJsonPath('data.0.files.0.name', 'Guía de pruebas.pdf')
            ->assertJsonPath('data.0.files.1.name', 'Clase.pptx');

        $this->get($this->filesPath("/{$id}"))->assertOk()->assertDownload('Guia de pruebas.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("filename*=utf-8''Gu%C3%ADa%20de%20pruebas.pdf", (string) $this->get($this->filesPath("/{$id}"))->headers->get('Content-Disposition'));

        $this->deleteJson($this->filesPath("/{$id}"))->assertNoContent();
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_only_documents_up_to_20_mb_and_10_per_topic_are_accepted(): void
    {
        $this->post($this->filesPath(), ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Solo se permiten archivos PDF, Word, PowerPoint, Excel o de texto.']);
        // Extensión permitida, pero el contenido es otra cosa.
        $this->post($this->filesPath(), ['file' => UploadedFile::fake()->create('foto.pdf', 10, 'image/png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->post($this->filesPath(), ['file' => $this->pdf('grande.pdf', TopicFiles::MAX_KILOBYTES + 1)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'El archivo no puede superar los 20 MB.']);

        for ($i = 1; $i <= TopicFiles::MAX_PER_TOPIC; $i++) {
            $this->post($this->filesPath(), ['file' => $this->pdf("guia-{$i}.pdf", 1)], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->post($this->filesPath(), ['file' => $this->pdf('once.pdf', 1)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Un tema puede tener como máximo 10 archivos.']);
        $this->assertSame(TopicFiles::MAX_PER_TOPIC, TopicFile::query()->count());
        $this->assertCount(TopicFiles::MAX_PER_TOPIC, Storage::disk('local')->allFiles('topic-files'));
    }

    public function test_disabled_topics_and_other_teachers_cannot_change_files(): void
    {
        $this->topic->update(['estado' => false]);
        $this->post($this->filesPath(), ['file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame([], Storage::disk('local')->allFiles('topic-files'));

        $this->topic->update(['estado' => true]);
        $id = $this->post($this->filesPath(), ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        Sanctum::actingAs(Usuario::factory()->withRole('docente')->create(), ['access-api']);
        $this->post($this->filesPath(), ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertForbidden();
        $this->get($this->filesPath("/{$id}"))->assertForbidden();
        $this->deleteJson($this->filesPath("/{$id}"))->assertForbidden();
        $this->assertModelExists(TopicFile::query()->findOrFail($id));
    }

    public function test_enrolled_students_download_files_of_active_topics(): void
    {
        $id = $this->post($this->filesPath(), ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $student = $this->enrollment()->estudiante;
        $studentPath = "/api/v1/student/tutoring/{$this->tutoring->getKey()}/topics/{$this->topic->getKey()}";

        Sanctum::actingAs($student, ['access-api']);
        $this->getJson("/api/v1/student/tutoring/{$this->tutoring->getKey()}/topics")->assertOk()
            ->assertJsonPath('data.topics.0.files.0.name', 'Guía de pruebas.pdf');
        $this->get("{$studentPath}/files/{$id}")->assertOk()->assertDownload('Guia de pruebas.pdf');

        $this->topic->update(['estado' => false]);
        $this->get("{$studentPath}/files/{$id}")->assertNotFound();

        $this->topic->update(['estado' => true]);
        Sanctum::actingAs(Usuario::factory()->student()->create(), ['access-api']);
        $this->get("{$studentPath}/files/{$id}")->assertForbidden();
    }
}
