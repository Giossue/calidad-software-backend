<?php

use App\Http\Controllers\Api\V1\AcademicPeriodController;
use App\Http\Controllers\Api\V1\Admin\CareerController;
use App\Http\Controllers\Api\V1\Admin\CoordinatorCareerAssignmentController;
use App\Http\Controllers\Api\V1\Admin\CycleController;
use App\Http\Controllers\Api\V1\Admin\FacultyController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Coordination\DegreeTopicController;
use App\Http\Controllers\Api\V1\Coordination\PeriodSectionController;
use App\Http\Controllers\Api\V1\Coordination\TeacherCoordinationController;
use App\Http\Controllers\Api\V1\ModalityController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\Student\StudentDegreeTopicController;
use App\Http\Controllers\Api\V1\Student\StudentTutoringController;
use App\Http\Controllers\Api\V1\Teacher\ContentController as TeacherContentController;
use App\Http\Controllers\Api\V1\Teacher\DegreeAssignmentController;
use App\Http\Controllers\Api\V1\Teacher\EnrollmentController;
use App\Http\Controllers\Api\V1\Teacher\GradeController;
use App\Http\Controllers\Api\V1\Teacher\ReportController as TeacherReportController;
use App\Http\Controllers\Api\V1\Teacher\ScheduleController as TeacherScheduleController;
use App\Http\Controllers\Api\V1\Teacher\SessionController;
use App\Http\Controllers\Api\V1\Teacher\TeacherDegreeTrackingController;
use App\Http\Controllers\Api\V1\Teacher\TutoringController as TeacherTutoringController;
use App\Http\Controllers\Api\V1\Tutoring\CatalogController as TutoringCatalogController;
use App\Http\Controllers\Api\V1\Tutoring\CoordinatorDegreeEnrollmentController;
use App\Http\Controllers\Api\V1\Tutoring\CoordinatorEnrollmentController;
use App\Http\Controllers\Api\V1\Tutoring\CoordinatorStudentController;
use App\Http\Controllers\Api\V1\Tutoring\ScheduleController as TutoringScheduleController;
use App\Http\Controllers\Api\V1\Tutoring\SubjectController as TutoringSubjectController;
use App\Http\Controllers\Api\V1\Tutoring\SupervisionController as TutoringSupervisionController;
use App\Http\Controllers\Api\V1\Tutoring\TeacherController as TutoringTeacherController;
use App\Http\Controllers\Api\V1\Tutoring\TutoringController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegistrationController::class, 'store'])
            ->middleware('throttle:3,1')->name('register');
        Route::post('login', [TokenController::class, 'store'])
            ->middleware('throttle:5,1')->name('login');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware(['auth:sanctum', 'throttle:5,1'])->name('two-factor.challenge');
        Route::post('forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:5,1')->name('password.forgot');
        Route::post('reset-password', [PasswordController::class, 'reset'])
            ->middleware('throttle:5,1')->name('password.reset');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('user', [TokenController::class, 'show'])->name('user');
            Route::delete('logout', [TokenController::class, 'destroy'])->name('logout');
            Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:3,1')->name('verification.send');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::patch('{user}', [UserController::class, 'update'])->name('update');
            Route::patch('{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
            Route::patch('{user}/activate', [UserController::class, 'activate'])->name('activate');
        });

        Route::prefix('faculties')->name('faculties.')->group(function (): void {
            Route::get('/', [FacultyController::class, 'index'])->name('index');
            Route::post('/', [FacultyController::class, 'store'])->name('store');
            Route::patch('{faculty}', [FacultyController::class, 'update'])->name('update');
            Route::patch('{faculty}/deactivate', [FacultyController::class, 'deactivate'])->name('deactivate');
            Route::patch('{faculty}/activate', [FacultyController::class, 'activate'])->name('activate');
        });
    });

    Route::middleware(['auth:sanctum', 'verified', CheckAbilities::class.':access-api', EnsureActiveAccount::class])->group(function (): void {
        Route::get('academic-periods/current', [PeriodSectionController::class, 'currentPeriod'])->name('academic-periods.current');
        Route::get('academic-periods/current/sections', [PeriodSectionController::class, 'index'])->name('academic-periods.current.sections.index');
        Route::post('academic-periods/current/sections', [PeriodSectionController::class, 'store'])->name('academic-periods.current.sections.store');

        Route::prefix('coordination')->name('coordination.')->group(function (): void {
            Route::get('degree-students', [CoordinatorDegreeEnrollmentController::class, 'index'])->name('degree-students.index');
            Route::post('degree-students/{student}/enroll', [CoordinatorDegreeEnrollmentController::class, 'enroll'])->name('degree-students.enroll');
            Route::delete('degree-students/{student}/unenroll', [CoordinatorDegreeEnrollmentController::class, 'unenroll'])->name('degree-students.unenroll');
            Route::get('teachers', [TeacherCoordinationController::class, 'index'])->name('teachers.index');
            Route::get('degree-topics', [DegreeTopicController::class, 'index'])->name('degree-topics.index');
            Route::get('degree-topics/pending', [DegreeTopicController::class, 'indexPending'])->name('degree-topics.pending');
            Route::get('degree-topics/{topic}', [DegreeTopicController::class, 'show'])->name('degree-topics.show');
            Route::post('degree-topics/{topic}/approve', [DegreeTopicController::class, 'approve'])->name('degree-topics.approve');
            Route::post('degree-topics/{topic}/reject', [DegreeTopicController::class, 'reject'])->name('degree-topics.reject');
            Route::post('degree-topics/{topic}/observations', [DegreeTopicController::class, 'storeObservation'])->name('degree-topics.observations.store');
            Route::get('degree-topics/{topic}/peers', [DegreeTopicController::class, 'peers'])->name('degree-topics.peers.index');
            Route::put('degree-topics/{topic}/peers', [DegreeTopicController::class, 'updatePeers'])->name('degree-topics.peers.update');
            Route::post('degree-topics/{topic}/activities', [DegreeTopicController::class, 'storeActivity'])->name('degree-topics.activities.store');
            Route::patch('degree-topics/{topic}/activities/{activity}/toggle', [DegreeTopicController::class, 'toggleActivity'])->name('degree-topics.activities.toggle');
            Route::patch('degree-topics/{topic}/progress', [DegreeTopicController::class, 'updateProgress'])->name('degree-topics.progress.update');
            Route::post('degree-topics/{topic}/reports', [DegreeTopicController::class, 'storeReport'])->name('degree-topics.reports.store');
            Route::get('degree-reports', [DegreeTopicController::class, 'reports'])->name('degree-reports.index');
        });

        Route::prefix('student')->name('student.')->group(function (): void {
            Route::get('degree-enrollment-status', [StudentDegreeTopicController::class, 'enrollmentStatus'])->name('degree-enrollment-status');
            Route::get('degree-topics', [StudentDegreeTopicController::class, 'index'])->name('degree-topics.index');
            Route::post('degree-topics', [StudentDegreeTopicController::class, 'store'])->name('degree-topics.store');
            Route::get('degree-topics/assignments', [StudentDegreeTopicController::class, 'assignments'])->name('degree-topics.assignments');
            Route::get('degree-topics/{topic}', [StudentDegreeTopicController::class, 'show'])->name('degree-topics.show');
            Route::match(['put', 'patch'], 'degree-topics/{topic}', [StudentDegreeTopicController::class, 'update'])->name('degree-topics.update');
            Route::get('degree-topics/{topic}/assignments', [StudentDegreeTopicController::class, 'topicAssignments'])->name('degree-topics.topic-assignments');
            Route::get('tutoring', [StudentTutoringController::class, 'index'])->name('tutoring.index');
            Route::get('tutoring/grades', [StudentTutoringController::class, 'allGrades'])->name('tutoring.grades.all');
            Route::get('tutoring/{tutoring}/grades', [StudentTutoringController::class, 'grades'])->name('tutoring.grades');
            Route::get('tutoring/attendance', [StudentTutoringController::class, 'allAttendance'])->name('tutoring.attendance.all');
            Route::get('tutoring/{tutoring}/attendance', [StudentTutoringController::class, 'attendance'])->name('tutoring.attendance');
            Route::get('tutoring/{tutoring}/topics', [StudentTutoringController::class, 'topics'])->name('tutoring.topics');
            Route::get('tutoring/{tutoring}/topics/{topic}', [StudentTutoringController::class, 'topicDetail'])->name('tutoring.topics.show');
            Route::get('tutoring/{tutoring}/sessions', [StudentTutoringController::class, 'sessions'])->name('tutoring.sessions');
        });

        Route::prefix('teacher')->name('teacher.')->group(function (): void {
            Route::get('tutorings', [TeacherTutoringController::class, 'index'])->name('tutorings.index');
            Route::get('grade-settings', [TeacherTutoringController::class, 'gradeSettings'])->name('grade-settings');
            Route::get('degree-assignments', [DegreeAssignmentController::class, 'index'])->name('degree-assignments.index');
            Route::get('degree-tracking', [TeacherDegreeTrackingController::class, 'index'])->name('degree-tracking.index');
            Route::get('degree-tracking/{topic}', [TeacherDegreeTrackingController::class, 'show'])->name('degree-tracking.show');
            Route::post('degree-tracking/{topic}/activities', [TeacherDegreeTrackingController::class, 'storeActivity'])->name('degree-tracking.activities.store');
            Route::patch('degree-tracking/{topic}/activities/{activity}/toggle', [TeacherDegreeTrackingController::class, 'toggleActivity'])->name('degree-tracking.activities.toggle');
            Route::patch('degree-tracking/{topic}/progress', [TeacherDegreeTrackingController::class, 'updateProgress'])->name('degree-tracking.progress.update');

            Route::get('tutorings/{tutoring}/students', [EnrollmentController::class, 'index'])->name('students.index');
            Route::get('tutorings/{tutoring}/available-students', [EnrollmentController::class, 'available'])->name('students.available');
            Route::post('tutorings/{tutoring}/students', [EnrollmentController::class, 'store'])->name('students.store');
            Route::patch('tutorings/{tutoring}/students/{enrollment}', [EnrollmentController::class, 'update'])->name('students.update');
            Route::patch('tutorings/{tutoring}/students/{enrollment}/deactivate', [EnrollmentController::class, 'deactivate'])->name('students.deactivate');
            Route::put('tutorings/{tutoring}/students/{enrollment}/grades/{type}', [GradeController::class, 'store'])->whereIn('type', ['diagnostic', 'partial', 'partial_two'])->name('grades.store');
            Route::put('tutorings/{tutoring}/grades', [GradeController::class, 'bulk'])->name('grades.bulk');

            Route::get('tutorings/{tutoring}/topics', [TeacherContentController::class, 'index'])->name('topics.index');
            Route::post('tutorings/{tutoring}/topics', [TeacherContentController::class, 'storeTopic'])->name('topics.store');
            Route::patch('tutorings/{tutoring}/topics/{topic}', [TeacherContentController::class, 'updateTopic'])->name('topics.update');
            Route::patch('tutorings/{tutoring}/topics/{topic}/toggle-covered', [TeacherContentController::class, 'toggleCovered'])->name('topics.toggle-covered');
            Route::patch('tutorings/{tutoring}/topics/{topic}/deactivate', [TeacherContentController::class, 'deactivateTopic'])->name('topics.deactivate');
            Route::post('tutorings/{tutoring}/topics/{topic}/activities', [TeacherContentController::class, 'storeActivity'])->name('activities.store');
            Route::patch('tutorings/{tutoring}/topics/{topic}/activities/{activity}', [TeacherContentController::class, 'updateActivity'])->name('activities.update');
            Route::patch('tutorings/{tutoring}/topics/{topic}/activities/{activity}/deactivate', [TeacherContentController::class, 'deactivateActivity'])->name('activities.deactivate');
            Route::post('tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies', [TeacherContentController::class, 'storeMethodology'])->name('methodologies.store');
            Route::patch('tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies/{methodology}', [TeacherContentController::class, 'updateMethodology'])->name('methodologies.update');
            Route::patch('tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies/{methodology}/deactivate', [TeacherContentController::class, 'deactivateMethodology'])->name('methodologies.deactivate');

            Route::get('tutorings/{tutoring}/sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::get('tutorings/{tutoring}/attendance', [SessionController::class, 'attendance'])->name('attendance.index');
            Route::put('tutorings/{tutoring}/sessions', [SessionController::class, 'store'])->name('sessions.store');
            Route::patch('tutorings/{tutoring}/sessions/{session}/topics', [SessionController::class, 'updateTopics'])->name('sessions.topics.update');
            Route::get('tutorings/{tutoring}/reports', [TeacherReportController::class, 'index'])->name('reports.index');
            Route::post('tutorings/{tutoring}/reports', [TeacherReportController::class, 'store'])->name('reports.store');

            Route::get('tutorings/{tutoring}/schedules', [TeacherScheduleController::class, 'index'])->name('schedules.index');
            Route::put('tutorings/{tutoring}/schedules', [TeacherScheduleController::class, 'sync'])->name('schedules.sync');
            Route::post('tutorings/{tutoring}/schedules', [TeacherScheduleController::class, 'store'])->name('schedules.store');
            Route::patch('tutorings/{tutoring}/schedules/{schedule}', [TeacherScheduleController::class, 'update'])->name('schedules.update');
            Route::patch('tutorings/{tutoring}/schedules/{schedule}/deactivate', [TeacherScheduleController::class, 'deactivate'])->name('schedules.deactivate');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index.legacy');
        Route::post('academic-periods', [AcademicPeriodController::class, 'store'])->name('academic-periods.store.legacy');
        Route::patch('academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update'])->name('academic-periods.update.legacy');
        Route::patch('academic-periods/{academicPeriod}/deactivate', [AcademicPeriodController::class, 'deactivate'])
            ->middleware('can:deactivate,academicPeriod')->name('academic-periods.deactivate.legacy');
        Route::patch('academic-periods/{academicPeriod}/activate', [AcademicPeriodController::class, 'activate'])
            ->middleware('can:activate,academicPeriod')->name('academic-periods.activate.legacy');
        Route::get('modalities', [ModalityController::class, 'index'])->name('modalities.index.legacy');
        Route::post('modalities', [ModalityController::class, 'store'])->name('modalities.store.legacy');
        Route::patch('modalities/{modality}', [ModalityController::class, 'update'])->name('modalities.update.legacy');
        Route::patch('modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])
            ->middleware('can:deactivate,modality')->name('modalities.deactivate.legacy');
        Route::patch('modalities/{modality}/activate', [ModalityController::class, 'activate'])
            ->middleware('can:activate,modality')->name('modalities.activate.legacy');
    });

    Route::middleware(['auth:sanctum', 'verified', CheckAbilities::class.':access-api'])
        ->prefix('tutoring-coordination')
        ->name('tutoring-coordination.')
        ->group(function (): void {
            Route::get('careers', [TutoringCatalogController::class, 'careers'])->name('careers.index');
            Route::get('cycles', [TutoringCatalogController::class, 'cycles'])->name('cycles.index');
            Route::get('periods', [TutoringCatalogController::class, 'periods'])->name('periods.index');
            Route::get('modalities', [TutoringCatalogController::class, 'modalities'])->name('modalities.index');
            Route::get('sections', [TutoringCatalogController::class, 'sections'])->name('sections.index');
            Route::post('sections', [TutoringCatalogController::class, 'storeSection'])->name('sections.store');

            Route::get('subjects', [TutoringSubjectController::class, 'index'])->name('subjects.index');
            Route::post('subjects', [TutoringSubjectController::class, 'store'])->name('subjects.store');
            Route::patch('subjects/{subject}', [TutoringSubjectController::class, 'update'])->name('subjects.update');
            Route::patch('subjects/{subject}/deactivate', [TutoringSubjectController::class, 'deactivate'])->name('subjects.deactivate');
            Route::put('subjects/{subject}/cycles/{cycle}', [TutoringSubjectController::class, 'assignCycle'])->name('subjects.cycles.assign');
            Route::delete('subjects/{subject}/cycles/{cycle}', [TutoringSubjectController::class, 'unassignCycle'])->name('subjects.cycles.unassign');
            Route::post('subjects/{subject}/parallels', [TutoringSubjectController::class, 'assignParallel'])->name('subjects.parallels.assign');
            Route::delete('subjects/{subject}/parallels/{parallel}', [TutoringSubjectController::class, 'unassignParallel'])->name('subjects.parallels.unassign');

            Route::get('teachers', [TutoringTeacherController::class, 'index'])->name('teachers.index');
            Route::get('available-teachers', [TutoringTeacherController::class, 'available'])->name('teachers.available');
            Route::post('teachers', [TutoringTeacherController::class, 'store'])->name('teachers.store');
            Route::post('teachers/{teacher}/careers', [TutoringTeacherController::class, 'linkCareer'])->name('teachers.careers.link');
            Route::delete('teachers/{teacher}/careers/{career}', [TutoringTeacherController::class, 'unlinkCareer'])->name('teachers.careers.unlink');
            Route::patch('teachers/{teacher}', [TutoringTeacherController::class, 'update'])->name('teachers.update');
            Route::patch('teachers/{teacher}/deactivate', [TutoringTeacherController::class, 'deactivate'])->name('teachers.deactivate');

            Route::get('tutorings', [TutoringController::class, 'index'])->name('tutorings.index');
            Route::post('tutorings', [TutoringController::class, 'store'])->name('tutorings.store');
            Route::patch('tutorings/{tutoring}', [TutoringController::class, 'update'])->name('tutorings.update');
            Route::patch('tutorings/{tutoring}/deactivate', [TutoringController::class, 'deactivate'])->name('tutorings.deactivate');
            Route::patch('tutorings/{tutoring}/activate', [TutoringController::class, 'activate'])->name('tutorings.activate');
            Route::put('tutorings/{tutoring}/cycle', [TutoringController::class, 'assignCycle'])->name('tutorings.cycle.assign');
            Route::put('tutorings/{tutoring}/teacher', [TutoringController::class, 'assignTeacher'])->name('tutorings.teacher.assign');
            Route::get('tutorings/{tutoring}/schedules', [TutoringScheduleController::class, 'index'])->name('schedules.index');
            Route::post('tutorings/{tutoring}/schedules', [TutoringScheduleController::class, 'store'])->name('schedules.store');
            Route::patch('tutorings/{tutoring}/schedules/{schedule}', [TutoringScheduleController::class, 'update'])->name('schedules.update');
            Route::patch('tutorings/{tutoring}/schedules/{schedule}/deactivate', [TutoringScheduleController::class, 'deactivate'])->name('schedules.deactivate');
            Route::get('tutorings/{tutoring}/attendance', [TutoringSupervisionController::class, 'attendance'])->name('tutorings.attendance');
            Route::get('tutorings/{tutoring}/reports', [TutoringSupervisionController::class, 'reports'])->name('tutorings.reports');
            Route::get('reports', [TutoringSupervisionController::class, 'allReports'])->name('tutorings.all-reports');

            Route::get('tutorings/{tutoring}/students', [CoordinatorEnrollmentController::class, 'index'])->name('tutorings.students.index');
            Route::get('tutorings/{tutoring}/available-students', [CoordinatorEnrollmentController::class, 'available'])->name('tutorings.students.available');
            Route::post('tutorings/{tutoring}/students', [CoordinatorEnrollmentController::class, 'store'])->name('tutorings.students.store');
            Route::patch('tutorings/{tutoring}/students/{enrollment}', [CoordinatorEnrollmentController::class, 'update'])->name('tutorings.students.update');
            Route::patch('tutorings/{tutoring}/students/{enrollment}/deactivate', [CoordinatorEnrollmentController::class, 'deactivate'])->name('tutorings.students.deactivate');
            Route::patch('tutorings/{tutoring}/students/{enrollment}/reenroll', [CoordinatorEnrollmentController::class, 'reenroll'])->name('tutorings.students.reenroll');

            Route::get('students', [CoordinatorStudentController::class, 'index'])->name('students.index');
            Route::post('students', [CoordinatorStudentController::class, 'store'])->name('students.store');
            Route::get('students/{student}/available-tutorings', [CoordinatorStudentController::class, 'availableTutorings'])->name('students.available-tutorings');
            Route::post('students/{student}/enroll', [CoordinatorStudentController::class, 'enroll'])->name('students.enroll');
            Route::delete('students/{student}/enrollments/{enrollment}', [CoordinatorStudentController::class, 'unenroll'])->name('students.unenroll');
            Route::get('degree-students', [CoordinatorDegreeEnrollmentController::class, 'index'])->name('degree-students.index');
            Route::post('degree-students/{student}/enroll', [CoordinatorDegreeEnrollmentController::class, 'enroll'])->name('degree-students.enroll');
            Route::delete('degree-students/{student}/unenroll', [CoordinatorDegreeEnrollmentController::class, 'unenroll'])->name('degree-students.unenroll');
        });

    Route::middleware(['auth:sanctum', 'verified'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::put('users/{user}/careers', [CoordinatorCareerAssignmentController::class, 'update'])
                ->middleware(CheckAbilities::class.':access-api')->name('users.careers.update');
            Route::get('faculties', [FacultyController::class, 'index'])->name('faculties.index');
            Route::get('careers', [CareerController::class, 'index'])->name('careers.index');
            Route::post('careers', [CareerController::class, 'store'])->name('careers.store');
            Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
            Route::patch('careers/{career}', [CareerController::class, 'update'])->name('careers.update');
            Route::patch('careers/{career}/deactivate', [CareerController::class, 'deactivate'])
                ->middleware('can:deactivate,career')->name('careers.deactivate');
            Route::patch('careers/{career}/activate', [CareerController::class, 'activate'])
                ->middleware('can:activate,career')->name('careers.activate');
            Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
            Route::patch('cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
            Route::patch('cycles/{cycle}/deactivate', [CycleController::class, 'deactivate'])
                ->middleware('can:deactivate,cycle')->name('cycles.deactivate');
            Route::patch('cycles/{cycle}/activate', [CycleController::class, 'activate'])
                ->middleware('can:activate,cycle')->name('cycles.activate');
            Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index');
            Route::post('academic-periods', [AcademicPeriodController::class, 'store'])->name('academic-periods.store');
            Route::patch('academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update'])->name('academic-periods.update');
            Route::patch('academic-periods/{academicPeriod}/deactivate', [AcademicPeriodController::class, 'deactivate'])
                ->middleware('can:deactivate,academicPeriod')->name('academic-periods.deactivate');
            Route::patch('academic-periods/{academicPeriod}/activate', [AcademicPeriodController::class, 'activate'])
                ->middleware('can:activate,academicPeriod')->name('academic-periods.activate');
            Route::get('modalities', [ModalityController::class, 'index'])->name('modalities.index');
            Route::post('modalities', [ModalityController::class, 'store'])->name('modalities.store');
            Route::patch('modalities/{modality}', [ModalityController::class, 'update'])->name('modalities.update');
            Route::patch('modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])
                ->middleware('can:deactivate,modality')->name('modalities.deactivate');
            Route::patch('modalities/{modality}/activate', [ModalityController::class, 'activate'])
                ->middleware('can:activate,modality')->name('modalities.activate');
            Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
            Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
            Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
            Route::patch('sections/{section}/deactivate', [SectionController::class, 'deactivate'])
                ->middleware('can:deactivate,section')->name('sections.deactivate');
            Route::patch('sections/{section}/activate', [SectionController::class, 'activate'])
                ->middleware('can:activate,section')->name('sections.activate');
        });
});
