<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;
use App\Models\Malla;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de las mallas de una carrera, en un solo lugar:
 *  - cada carrera tiene exactamente una malla vigente («Malla inicial» al crearla);
 *  - cada estudiante sigue una malla de su carrera: la vigente al registrarse, o
 *    la de la tutoría por la que se le registra;
 *  - un estudiante solo se inscribe en tutorías de su malla.
 */
class Mallas
{
    public const INITIAL_NAME = 'Malla inicial';

    public const OTHER_MALLA = 'El estudiante sigue otra malla.';

    /** Malla vigente de la carrera. Una carrera sin malla recibe la inicial. */
    public static function current(int $careerId): Malla
    {
        return Malla::query()->where('career_id', $careerId)->where('is_current', true)->first()
            ?? self::ensureInitial($careerId);
    }

    /**
     * Garantiza que la carrera tenga una malla vigente: crea «Malla inicial» si
     * no tiene ninguna o marca vigente la más antigua si ninguna lo está.
     */
    public static function ensureInitial(int $careerId): Malla
    {
        return DB::transaction(function () use ($careerId): Malla {
            $current = Malla::query()->where('career_id', $careerId)->where('is_current', true)->first();
            if ($current !== null) {
                return $current;
            }
            $oldest = Malla::query()->where('career_id', $careerId)->orderBy('id')->first();
            if ($oldest !== null) {
                $oldest->forceFill(['is_current' => true])->save();

                return $oldest;
            }

            return Malla::query()->create(['career_id' => $careerId, 'name' => self::INITIAL_NAME, 'is_current' => true]);
        });
    }

    /** Marca la malla como vigente; la vigente anterior de la carrera deja de serlo. */
    public static function makeCurrent(Malla $malla): Malla
    {
        DB::transaction(function () use ($malla): void {
            // El índice único parcial impide dos vigentes aunque dos pedidos se crucen.
            Malla::query()->where('career_id', $malla->career_id)->whereKeyNot($malla->getKey())->where('is_current', true)
                ->update(['is_current' => false, 'updated_at' => now()]);
            $malla->forceFill(['is_current' => true])->save();
        });

        return $malla;
    }

    /**
     * Deja la malla del estudiante acorde a su carrera: conserva la que tiene si es
     * de su carrera; si no, toma `$preferred` cuando es de su carrera o, si no, la
     * vigente. Sin carrera no hay malla. Las cuentas que no son de estudiante no
     * siguen ninguna malla.
     */
    public static function syncStudent(Usuario $user, ?Malla $preferred = null): void
    {
        $careerId = $user->fk_carrera;
        // Consulta los roles en la base: pueden haber cambiado después de cargarlos.
        if ($careerId === null || ! $user->roles()->where('slug', 'estudiante')->exists()) {
            if ($user->malla_id !== null) {
                $user->forceFill(['malla_id' => null])->save();
            }

            return;
        }
        if ($user->malla_id !== null && Malla::query()->whereKey($user->malla_id)->where('career_id', $careerId)->exists()) {
            return;
        }

        $malla = $preferred !== null && $preferred->career_id === $careerId ? $preferred : self::current($careerId);
        $user->forceFill(['malla_id' => $malla->getKey()])->save();
    }

    /** El estudiante pasa a seguir `$malla`, que debe ser de su carrera. */
    public static function assign(Usuario $student, Malla $malla): void
    {
        if ($student->fk_carrera !== $malla->career_id) {
            throw ValidationException::withMessages(['malla_id' => 'La malla debe ser de la carrera del estudiante.']);
        }
        $student->forceFill(['malla_id' => $malla->getKey()])->save();
    }

    /**
     * Al cambiar la carrera de una cuenta, una malla de otra carrera deja paso a la
     * vigente de la nueva (o a ninguna, sin carrera). Se llama al guardar `Usuario`.
     */
    public static function keepConsistent(Usuario $user): void
    {
        if (! $user->isDirty('fk_carrera') || $user->malla_id === null) {
            return;
        }
        if ($user->fk_carrera === null) {
            $user->malla_id = null;
        } elseif (! Malla::query()->whereKey($user->malla_id)->where('career_id', $user->fk_carrera)->exists()) {
            $user->malla_id = self::current((int) $user->fk_carrera)->getKey();
        }
    }

    /** Malla de la tutoría: la de su asignatura (null en tutorías sin asignatura). */
    public static function ofTutoring(AsignaturaTutoria $tutoring): ?Malla
    {
        if ($tutoring->subject_id === null) {
            return null;
        }
        $subject = $tutoring->relationLoaded('subject') ? $tutoring->subject : Subject::query()->find($tutoring->subject_id);

        return $subject?->malla;
    }

    private static function tutoringMallaId(AsignaturaTutoria $tutoring): ?int
    {
        if ($tutoring->subject_id === null) {
            return null;
        }
        $mallaId = $tutoring->relationLoaded('subject') ? $tutoring->subject?->malla_id : Subject::query()->whereKey($tutoring->subject_id)->value('malla_id');

        return $mallaId === null ? null : (int) $mallaId;
    }

    /** El estudiante puede inscribirse en la tutoría: no sigue otra malla. */
    public static function follows(Usuario $student, AsignaturaTutoria $tutoring): bool
    {
        $mallaId = self::tutoringMallaId($tutoring);

        return $mallaId === null || $student->malla_id === null || $student->malla_id === $mallaId;
    }

    public static function assertFollows(Usuario $student, AsignaturaTutoria $tutoring, string $field = 'student_id'): void
    {
        if (! self::follows($student, $tutoring)) {
            throw ValidationException::withMessages([$field => self::OTHER_MALLA]);
        }
    }

    /**
     * Excluye a quienes siguen otra malla que la de la tutoría.
     *
     * @param  Builder<Usuario>  $students
     * @return Builder<Usuario>
     */
    public static function scopeFollowing(Builder $students, AsignaturaTutoria $tutoring): Builder
    {
        $mallaId = self::tutoringMallaId($tutoring);

        return $mallaId === null
            ? $students
            : $students->where(fn (Builder $query) => $query->whereNull('usuario.malla_id')->orWhere('usuario.malla_id', $mallaId));
    }
}
