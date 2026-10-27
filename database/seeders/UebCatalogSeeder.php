<?php

namespace Database\Seeders;

use App\Actions\Academic\GenerateCareerCycles;
use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo académico oficial de la Universidad Estatal de Bolívar (UEB):
 * facultades, carreras (con su modalidad y número de ciclos) y, donde la
 * malla curricular oficial lo permite, las asignaturas de cada ciclo.
 *
 * Fuente: mallas curriculares publicadas en ueb.edu.ec/s/Documentos/...
 * (consultadas el 27 de octubre de 2026). La mayoría de las mallas NO
 * imprimen un código institucional por asignatura; en esos casos `code`
 * queda en null a propósito — este catálogo no inventa códigos (igual que
 * la siembra histórica de Software en 2026_09_27_100000_seed_software_
 * career_subjects.php, cuyo catálogo se reproduce aquí).
 *
 * Avisos conocidos (no bloquean la siembra, pero conviene revisarlos desde
 * el panel de administración más adelante):
 * - "Gestión del Talento Humano" y "Psicología": el resumen inicial del
 *   relevamiento decía 9 ciclos; la malla oficial real de ambas tiene 8.
 *   Se usa 8 aquí, verificado contra el total de asignaturas impreso en
 *   cada PDF.
 * - "Contabilidad y Auditoría (Híbrida)": la oferta que el sitio listaba
 *   como "En línea" es, según su propia malla firmada, modalidad Híbrida
 *   (mismo catálogo de asignaturas y códigos CYA-* que la presencial).
 * - "Enfermería": su malla no usa una cuadrícula simple de 1 asignatura
 *   por celda (usa núcleos y un "proyecto integrador" que abarca varios
 *   periodos), así que aquí solo se crean la carrera y sus 9 ciclos, sin
 *   asignaturas, para no adivinar una estructura ambigua en datos de
 *   producción.
 * - Algunos nombres se transcriben con la misma errata o texto cortado que
 *   trae el PDF oficial (p. ej. "OperaciónTurística y Hotelera", "PRÁTICAS
 *   PRE PROFESIONALES LABORALES", un nombre cortado en Educación Inicial
 *   ciclo 4): se preservan tal cual en vez de corregirlos por adivinanza;
 *   son fáciles de editar luego desde el panel.
 * - "Ingeniería en Riesgos de Desastres" ciclo 3: el total de horas
 *   impreso no cuadra exactamente con la suma de sus 7 asignaturas (ver
 *   nota del extractor); se deja tal cual aparece en la malla.
 */
class UebCatalogSeeder extends Seeder
{
    private const FAC_ADMIN = 'Facultad de Ciencias Administrativas, Gestión Empresarial e Informática';

    private const FAC_DERECHO = 'Facultad de Jurisprudencia, Ciencias Sociales y Políticas';

    private const FAC_EDUCACION = 'Facultad de Ciencias de la Educación, Sociales, Filosóficas y Humanísticas';

    private const FAC_AGROPECUARIA = 'Facultad de Ciencias Agropecuarias, Recursos Naturales y del Ambiente';

    private const FAC_SALUD = 'Facultad de Ciencias de la Salud y del Ser Humano';

    private const FAC_SAN_MIGUEL = 'Extensión Universitaria de San Miguel';

    /** @var array<int, string> */
    private const FACULTIES = [
        self::FAC_ADMIN,
        self::FAC_DERECHO,
        self::FAC_EDUCACION,
        self::FAC_AGROPECUARIA,
        self::FAC_SALUD,
        self::FAC_SAN_MIGUEL,
    ];

    /**
     * @var array<string, array{faculty: string, modality: string, cycles: int, subjects: array<int, array<int, array{code: string|null, name: string}>>}>
     */
    private const CAREERS = [
        'Software' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => 'SW-B1-001', 'name' => 'Algoritmos y lógica de programación'],
                    ['code' => 'SW-B1-002', 'name' => 'Álgebra lineal'],
                    ['code' => 'SW-B1-003', 'name' => 'Estructuras discretas'],
                    ['code' => 'SW-B1-004', 'name' => 'Cálculo I'],
                    ['code' => 'SW-B1-005', 'name' => 'Lenguaje y comunicación'],
                ],
                2 => [
                    ['code' => 'SW-B2-006', 'name' => 'Programación orientada a objetos'],
                    ['code' => 'SW-B2-007', 'name' => 'Arquitectura de computadores'],
                    ['code' => 'SW-B2-008', 'name' => 'Fundamentos de física para ingeniería'],
                    ['code' => 'SW-B2-009', 'name' => 'Cálculo II'],
                    ['code' => 'SW-B2-010', 'name' => 'Realidad nacional y diversidad cultural'],
                ],
                3 => [
                    ['code' => 'SW-B3-011', 'name' => 'Estructura de datos'],
                    ['code' => 'SW-B3-012', 'name' => 'Ingeniería de requerimientos'],
                    ['code' => 'SW-B3-013', 'name' => 'Sistemas de información'],
                    ['code' => 'SW-B3-014', 'name' => 'Cálculo III'],
                    ['code' => 'SW-B3-015', 'name' => 'Estadística y probabilidades'],
                    ['code' => 'SW-B3-016', 'name' => 'Trabajo en equipo y comunicación eficaz'],
                ],
                4 => [
                    ['code' => 'SW-P4-017', 'name' => 'Programación web I'],
                    ['code' => 'SW-P4-018', 'name' => 'Modelamiento y diseño de software'],
                    ['code' => 'SW-P4-019', 'name' => 'Base de datos'],
                    ['code' => 'SW-P4-020', 'name' => 'Sistemas operativos'],
                    ['code' => 'SW-P4-021', 'name' => 'Métodos numéricos'],
                    ['code' => 'SW-P4-022', 'name' => 'Sostenibilidad ambiental'],
                ],
                5 => [
                    ['code' => 'SW-P5-023', 'name' => 'Programación web II'],
                    ['code' => 'SW-P5-024', 'name' => 'Arquitectura del software'],
                    ['code' => 'SW-P5-025', 'name' => 'Administración de base de datos'],
                    ['code' => 'SW-P5-026', 'name' => 'Investigación de operaciones'],
                    ['code' => 'SW-P5-027', 'name' => 'Epistemología y metodología de investigación'],
                ],
                6 => [
                    ['code' => 'SW-P6-028', 'name' => 'Programación móvil'],
                    ['code' => 'SW-P6-029', 'name' => 'Mantenimiento y configuración de software'],
                    ['code' => 'SW-P6-030', 'name' => 'Interacción hombre máquina'],
                    ['code' => 'SW-P6-031', 'name' => 'Fundamentos de redes y conectividad'],
                    ['code' => 'SW-P6-032', 'name' => 'Simulación'],
                    ['code' => 'SW-P6-033', 'name' => 'Liderazgo y emprendimiento'],
                ],
                7 => [
                    ['code' => 'SW-P7-034', 'name' => 'Aplicaciones distribuidas'],
                    ['code' => 'SW-P7-035', 'name' => 'Seguridad del software'],
                    ['code' => 'SW-P7-036', 'name' => 'Redes de datos'],
                    ['code' => 'SW-P7-037', 'name' => 'Inteligencia artificial'],
                    ['code' => null, 'name' => 'Prácticas preprofesionales'],
                ],
                8 => [
                    ['code' => 'SW-IC8-038', 'name' => 'Gestión de las tecnologías de la información'],
                    ['code' => 'SW-IC8-039', 'name' => 'Calidad de software'],
                    ['code' => 'SW-IC8-040', 'name' => 'Deontología informática'],
                    ['code' => null, 'name' => 'Prácticas de servicio comunitario'],
                    ['code' => null, 'name' => 'Trabajo de titulación e integración curricular'],
                ],
            ],
        ],

        'Tecnologías de la Información' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => 'TI-B1-001', 'name' => 'Algoritmos y Fundamentos de Programación'],
                    ['code' => 'TI-B1-002', 'name' => 'Álgebra Lineal'],
                    ['code' => 'TI-B1-003', 'name' => 'Estructuras Discretas'],
                    ['code' => 'TI-B1-004', 'name' => 'Cálculo I'],
                    ['code' => 'TI-B1-005', 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => 'TI-B2-006', 'name' => 'Programación Orientada a Objetos'],
                    ['code' => 'TI-B2-007', 'name' => 'Ingeniería de Requerimientos'],
                    ['code' => 'TI-B2-008', 'name' => 'Estadística'],
                    ['code' => 'TI-B2-009', 'name' => 'Cálculo II'],
                    ['code' => 'TI-B2-010', 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                3 => [
                    ['code' => 'TI-B3-011', 'name' => 'Programación Web I'],
                    ['code' => 'TI-B3-012', 'name' => 'Gestión de Procesos'],
                    ['code' => 'TI-B3-013', 'name' => 'Probabilidades'],
                    ['code' => 'TI-B3-014', 'name' => 'Cálculo III'],
                    ['code' => 'TI-B3-015', 'name' => 'Trabajo en Equipo y Comunicación Eficaz'],
                ],
                4 => [
                    ['code' => 'TI-P4-016', 'name' => 'Programación Web II'],
                    ['code' => 'TI-P4-017', 'name' => 'Base de Datos'],
                    ['code' => 'TI-P4-018', 'name' => 'Fundamentos de Redes y Conectividad'],
                    ['code' => 'TI-P4-019', 'name' => 'Gestión de Servicios TI'],
                    ['code' => 'TI-P4-020', 'name' => 'Sostenibilidad Ambiental'],
                ],
                5 => [
                    ['code' => 'TI-P5-021', 'name' => 'Gerencia Financiera'],
                    ['code' => 'TI-P5-022', 'name' => 'Administración de Base de Datos'],
                    ['code' => 'TI-P5-023', 'name' => 'Redes de Datos'],
                    ['code' => 'TI-P5-024', 'name' => 'Sistemas Operativos'],
                    ['code' => 'TI-P5-025', 'name' => 'Epistemología y Metodología de Investigación'],
                ],
                6 => [
                    ['code' => 'TI-P6-026', 'name' => 'Gerencia de Proyectos'],
                    ['code' => 'TI-P6-027', 'name' => 'Seguridad de Base de Datos'],
                    ['code' => 'TI-P6-028', 'name' => 'Gestión y Seguridad de Redes'],
                    ['code' => 'TI-P6-029', 'name' => 'Administración de Sistemas Operativos'],
                    ['code' => 'TI-P6-030', 'name' => 'Liderazgo y Emprendimiento'],
                ],
                7 => [
                    ['code' => 'TI-P7-031', 'name' => 'Gestión Estratégica de la Información'],
                    ['code' => 'TI-P7-032', 'name' => 'Diseño del Trabajo de Integración Curricular'],
                    ['code' => 'TI-P7-033', 'name' => 'Arquitectura y Plataforma de Servidores'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => 'TI-IC8-034', 'name' => 'Gestión del Conocimiento e Innovación'],
                    ['code' => 'TI-IC8-035', 'name' => 'Gestión y Gobierno de TI'],
                    ['code' => 'TI-IC8-036', 'name' => 'Deontología y Legislación Informática'],
                    ['code' => 'TI-IC8-037', 'name' => 'Cloud Computing'],
                    ['code' => null, 'name' => 'Trabajo de Integración Curricular'],
                ],
            ],
        ],

        'Administración de Empresas' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Fundamentos de Administración'],
                    ['code' => null, 'name' => 'Matemática I'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Derecho Mercantil y Laboral'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Gestión Administrativa'],
                    ['code' => null, 'name' => 'Matemática II'],
                    ['code' => null, 'name' => 'Contabilidad'],
                    ['code' => null, 'name' => 'Microeconomía'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Gestión del Talento Humano'],
                    ['code' => null, 'name' => 'Comportamiento y Desarrollo Organizacional'],
                    ['code' => null, 'name' => 'Contabilidad de Costos'],
                    ['code' => null, 'name' => 'Macroeconomía'],
                    ['code' => null, 'name' => 'Estadística Descriptiva'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Gestión por Procesos'],
                    ['code' => null, 'name' => 'Investigación de Mercados'],
                    ['code' => null, 'name' => 'Estadística Inferencial'],
                    ['code' => null, 'name' => 'Gestión Tributaria'],
                    ['code' => null, 'name' => 'Herramientas Informáticas'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Reingeniería de Procesos'],
                    ['code' => null, 'name' => 'Fundamentos de Marketing'],
                    ['code' => null, 'name' => 'Investigación Operativa'],
                    ['code' => null, 'name' => 'Administración Financiera'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Gestión de la Calidad'],
                    ['code' => null, 'name' => 'Elaboración de Proyectos'],
                    ['code' => null, 'name' => 'Administración Estratégica'],
                    ['code' => null, 'name' => 'Gestión y Control de la Producción'],
                    ['code' => null, 'name' => 'Finanzas Corporativas'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Gestión Ambiental Empresarial y Seguridad Industrial'],
                    ['code' => null, 'name' => 'Sistemas de Información Gerencial'],
                    ['code' => null, 'name' => 'Evaluación de Proyectos'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Negocios Internacionales'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Auditoría Administrativa'],
                    ['code' => null, 'name' => 'Habilidades Gerenciales'],
                    ['code' => null, 'name' => 'Emprendimiento'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Administración de Empresas (En línea)' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Virtual',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Fundamentos de Administración'],
                    ['code' => null, 'name' => 'Matemática I'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Derecho Mercantil y Laboral'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Gestión Administrativa'],
                    ['code' => null, 'name' => 'Matemática II'],
                    ['code' => null, 'name' => 'Contabilidad'],
                    ['code' => null, 'name' => 'Microeconomía'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Gestión del Talento Humano'],
                    ['code' => null, 'name' => 'Comportamiento y Desarrollo Organizacional'],
                    ['code' => null, 'name' => 'Contabilidad de Costos'],
                    ['code' => null, 'name' => 'Macroeconomía'],
                    ['code' => null, 'name' => 'Estadística Descriptiva'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Gestión por Procesos'],
                    ['code' => null, 'name' => 'Investigación de Mercados'],
                    ['code' => null, 'name' => 'Estadística Inferencial'],
                    ['code' => null, 'name' => 'Gestión Tributaria'],
                    ['code' => null, 'name' => 'Herramientas Informáticas'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Reingeniería de Procesos'],
                    ['code' => null, 'name' => 'Fundamentos de Marketing'],
                    ['code' => null, 'name' => 'Investigación Operativa'],
                    ['code' => null, 'name' => 'Administración Financiera'],
                    ['code' => null, 'name' => 'Gestión de la Calidad'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Elaboración de Proyectos'],
                    ['code' => null, 'name' => 'Administración Estratégica'],
                    ['code' => null, 'name' => 'Gestión y Control de la Producción'],
                    ['code' => null, 'name' => 'Finanzas Corporativas'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Gestión Ambiental Empresarial y Seguridad Industrial'],
                    ['code' => null, 'name' => 'Sistemas de Información Gerencial'],
                    ['code' => null, 'name' => 'Evaluación de Proyectos'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Negocios Internacionales'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Auditoría Administrativa'],
                    ['code' => null, 'name' => 'Habilidades Gerenciales'],
                    ['code' => null, 'name' => 'Emprendimiento'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Comunicación' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Híbrida',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Oratoria'],
                    ['code' => null, 'name' => 'Epistemología de la Comunicación y Periodismo'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Introducción al Periodismo y Comunicación'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Teoría de la Comunicación'],
                    ['code' => null, 'name' => 'Géneros Periodísticos'],
                    ['code' => null, 'name' => 'Redacción Académica'],
                    ['code' => null, 'name' => 'Tecnologías de la Información y Comunicación'],
                    ['code' => null, 'name' => 'Fotoperiodismo'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Educomunicación'],
                    ['code' => null, 'name' => 'Redacción Periodística'],
                    ['code' => null, 'name' => 'Psicología de la Comunicación'],
                    ['code' => null, 'name' => 'Comunicación para el Desarrollo'],
                    ['code' => null, 'name' => 'Diseño y Comunicación Visual'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Administración de Medios de Comunicación'],
                    ['code' => null, 'name' => 'Medios de Comunicación Digitales'],
                    ['code' => null, 'name' => 'Periodismo Especializado'],
                    ['code' => null, 'name' => 'Opinión Pública'],
                    ['code' => null, 'name' => 'Redacción y Estilo Periodísticos'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Producción de Medios Escritos'],
                    ['code' => null, 'name' => 'Producción de Radio I'],
                    ['code' => null, 'name' => 'Producción de Televisión I'],
                    ['code' => null, 'name' => 'Deontología de la Comunicación'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Producción Multimedia'],
                    ['code' => null, 'name' => 'Proyectos Comunicacionales y Sociales'],
                    ['code' => null, 'name' => 'Marketing para Comunicación'],
                    ['code' => null, 'name' => 'Producción de Radio II'],
                    ['code' => null, 'name' => 'Producción de Televisión II'],
                    ['code' => null, 'name' => 'Práctica Laboral'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Cibercultura'],
                    ['code' => null, 'name' => 'Metodología de la Investigación Social'],
                    ['code' => null, 'name' => 'Gestión y Emprendimiento'],
                    ['code' => null, 'name' => 'Comunicación Corporativa - DirCom'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Estadística Aplicada a la Investigación Social'],
                    ['code' => null, 'name' => 'Periodismo de Datos'],
                    ['code' => null, 'name' => 'Comunicación Política'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Contabilidad y Auditoría' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => 'CYA-B1-001', 'name' => 'NIIFS-NIC Generales'],
                    ['code' => 'CYA-B1-002', 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => 'CYA-B1-003', 'name' => 'Matemática I'],
                    ['code' => 'CYA-B1-004', 'name' => 'Informática Aplicada a la Contabilidad'],
                    ['code' => 'CYA-B1-005', 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => 'CYA-B2-006', 'name' => 'Contabilidad I'],
                    ['code' => 'CYA-B2-007', 'name' => 'Fundamentos de la Administración'],
                    ['code' => 'CYA-B2-008', 'name' => 'Matemática II'],
                    ['code' => 'CYA-B2-009', 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => 'CYA-B2-010', 'name' => 'Legislación I (Mercantil, Societaria y Laboral)'],
                ],
                3 => [
                    ['code' => 'CYA-B3-011', 'name' => 'Contabilidad II'],
                    ['code' => 'CYA-B3-012', 'name' => 'Legislación II (Tributaria y Contratación Pública)'],
                    ['code' => 'CYA-B3-013', 'name' => 'Matemática Financiera'],
                    ['code' => 'CYA-B3-014', 'name' => 'Contabilidad de Costos I'],
                    ['code' => 'CYA-B3-015', 'name' => 'Administración Estratégica'],
                ],
                4 => [
                    ['code' => 'CYA-P4-016', 'name' => 'Estadística (Descriptiva e Inferencial)'],
                    ['code' => 'CYA-P4-017', 'name' => 'Gestión del Talento Humano'],
                    ['code' => 'CYA-P4-018', 'name' => 'Contabilidad de IFIS/EPS'],
                    ['code' => 'CYA-P4-019', 'name' => 'Contabilidad de Costos II'],
                    ['code' => 'CYA-P4-020', 'name' => 'Sistemas Contables'],
                    ['code' => 'CYA-P4-021', 'name' => 'Economía Aplicada a la Contabilidad'],
                ],
                5 => [
                    ['code' => 'CYA-P5-022', 'name' => 'Auditoría (Fundamentos)'],
                    ['code' => 'CYA-P5-023', 'name' => 'Emprendimiento'],
                    ['code' => 'CYA-P5-024', 'name' => 'Contabilidades Especiales'],
                    ['code' => 'CYA-P5-025', 'name' => 'Investigación Operativa'],
                    ['code' => 'CYA-P5-026', 'name' => 'Marketing para Contadores'],
                    ['code' => 'CYA-P5-027', 'name' => 'Finanzas I (Aplicación y Finanzas Corporativas)'],
                ],
                6 => [
                    ['code' => 'CYA-P6-028', 'name' => 'Auditoría Financiera'],
                    ['code' => 'CYA-P6-029', 'name' => 'Gestión Tributaria'],
                    ['code' => 'CYA-P6-030', 'name' => 'Contabilidad Gubernamental'],
                    ['code' => 'CYA-P6-031', 'name' => 'Auditoría de Gestión'],
                    ['code' => 'CYA-P6-032', 'name' => 'Finanzas II'],
                    ['code' => 'CYA-P6-033', 'name' => 'Administración Gerencial'],
                ],
                7 => [
                    ['code' => 'CYA-P7-034', 'name' => 'Evaluación de Proyectos Sociales y Productivos'],
                    ['code' => 'CYA-P7-035', 'name' => 'Auditoría Tributaria'],
                    ['code' => 'CYA-P7-036', 'name' => 'Diseño y Elaboración de Trabajo de Titulación'],
                    ['code' => 'CYA-P7-037', 'name' => 'Auditoría Forense'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                8 => [
                    ['code' => 'CYA-UIC8-038', 'name' => 'Mercado de Valores y de Capital'],
                    ['code' => 'CYA-UIC8-039', 'name' => 'Balance Social (Responsabilidad Empresarial y Social)'],
                    ['code' => 'CYA-UIC8-040', 'name' => 'Comercio Exterior'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Contabilidad y Auditoría (Híbrida)' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Híbrida',
            'cycles' => 8,
            // Misma malla y los mismos códigos CYA-* que la versión presencial;
            // el PDF de esta oferta ("Contabilidad y Auditoría (Híbrida)" en la
            // URL oficial) imprime literalmente "MODALIDAD HÍBRIDA", no "en línea".
            'subjects' => [
                1 => [
                    ['code' => 'CYA-B1-001', 'name' => 'NIIFS-NIC Generales'],
                    ['code' => 'CYA-B1-002', 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => 'CYA-B1-003', 'name' => 'Matemática I'],
                    ['code' => 'CYA-B1-004', 'name' => 'Informática Aplicada a la Contabilidad'],
                    ['code' => 'CYA-B1-005', 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => 'CYA-B2-006', 'name' => 'Contabilidad I'],
                    ['code' => 'CYA-B2-007', 'name' => 'Fundamentos de la Administración'],
                    ['code' => 'CYA-B2-008', 'name' => 'Matemática II'],
                    ['code' => 'CYA-B2-009', 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => 'CYA-B2-010', 'name' => 'Legislación I (Mercantil, Societaria y Laboral)'],
                ],
                3 => [
                    ['code' => 'CYA-B3-011', 'name' => 'Contabilidad II'],
                    ['code' => 'CYA-B3-012', 'name' => 'Legislación II (Tributaria y Contratación Pública)'],
                    ['code' => 'CYA-B3-013', 'name' => 'Matemática Financiera'],
                    ['code' => 'CYA-B3-014', 'name' => 'Contabilidad de Costos I'],
                    ['code' => 'CYA-B3-015', 'name' => 'Administración Estratégica'],
                ],
                4 => [
                    ['code' => 'CYA-P4-016', 'name' => 'Estadística (Descriptiva e Inferencial)'],
                    ['code' => 'CYA-P4-017', 'name' => 'Gestión del Talento Humano'],
                    ['code' => 'CYA-P4-018', 'name' => 'Contabilidad de IFIS/EPS'],
                    ['code' => 'CYA-P4-019', 'name' => 'Contabilidad de Costos II'],
                    ['code' => 'CYA-P4-020', 'name' => 'Sistemas Contables'],
                    ['code' => 'CYA-P4-021', 'name' => 'Economía Aplicada a la Contabilidad'],
                ],
                5 => [
                    ['code' => 'CYA-P5-022', 'name' => 'Auditoría (Fundamentos)'],
                    ['code' => 'CYA-P5-023', 'name' => 'Emprendimiento'],
                    ['code' => 'CYA-P5-024', 'name' => 'Contabilidades Especiales'],
                    ['code' => 'CYA-P5-025', 'name' => 'Investigación Operativa'],
                    ['code' => 'CYA-P5-026', 'name' => 'Marketing para Contadores'],
                    ['code' => 'CYA-P5-027', 'name' => 'Finanzas I (Aplicación y Finanzas Corporativas)'],
                ],
                6 => [
                    ['code' => 'CYA-P6-028', 'name' => 'Auditoría Financiera'],
                    ['code' => 'CYA-P6-029', 'name' => 'Gestión Tributaria'],
                    ['code' => 'CYA-P6-030', 'name' => 'Contabilidad Gubernamental'],
                    ['code' => 'CYA-P6-031', 'name' => 'Auditoría de Gestión'],
                    ['code' => 'CYA-P6-032', 'name' => 'Finanzas II'],
                    ['code' => 'CYA-P6-033', 'name' => 'Administración Gerencial'],
                ],
                7 => [
                    ['code' => 'CYA-P7-034', 'name' => 'Evaluación de Proyectos Sociales y Productivos'],
                    ['code' => 'CYA-P7-035', 'name' => 'Auditoría Tributaria'],
                    ['code' => 'CYA-P7-036', 'name' => 'Diseño y Elaboración de Trabajo de Titulación'],
                    ['code' => 'CYA-P7-037', 'name' => 'Auditoría Forense'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                8 => [
                    ['code' => 'CYA-UIC8-038', 'name' => 'Mercado de Valores y de Capital'],
                    ['code' => 'CYA-UIC8-039', 'name' => 'Balance Social (Responsabilidad Empresarial y Social)'],
                    ['code' => 'CYA-UIC8-040', 'name' => 'Comercio Exterior'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Emprendimiento e Innovación Social' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Virtual',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Matemáticas'],
                    ['code' => null, 'name' => 'Bases de Emprendimiento'],
                    ['code' => null, 'name' => 'Fundamentos de la Administracion'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Matemática Financiera'],
                    ['code' => null, 'name' => 'Innovacion y Creatividad Aplicada a los Negocios'],
                    ['code' => null, 'name' => 'Gerencia del Talento Humano'],
                    ['code' => null, 'name' => 'Bases Legales para Organizaciones'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Estadística para Administración y Economía'],
                    ['code' => null, 'name' => 'Emprendimiento e Innovación I'],
                    ['code' => null, 'name' => 'Negociación y Liderazgo'],
                    ['code' => null, 'name' => 'Microeconomía'],
                    ['code' => null, 'name' => 'Informática para negocios'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Contabilidad General y Tributacion'],
                    ['code' => null, 'name' => 'Emprendimiento e Innovación II'],
                    ['code' => null, 'name' => 'Comportamiento Organizacional'],
                    ['code' => null, 'name' => 'Macroeconomía'],
                    ['code' => null, 'name' => 'Competitividad y Globalización'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Análisis financiero'],
                    ['code' => null, 'name' => 'Investigación y Análisis de Mercado'],
                    ['code' => null, 'name' => 'Simulación para Emprendimiento e Innovación'],
                    ['code' => null, 'name' => 'Planificación Estratégica'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Administración financiera'],
                    ['code' => null, 'name' => 'Gestión de la Innovación Empresarial y Social'],
                    ['code' => null, 'name' => 'Marketing'],
                    ['code' => null, 'name' => 'Promoción y Publicidad'],
                    ['code' => null, 'name' => 'Formulación y Evaluación de Proyectos'],
                    ['code' => null, 'name' => 'Diseño y Desarrollo de productos'],
                    ['code' => null, 'name' => 'Prácticas Pre Profesionales I'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Metodología de la Investigación Aplicada al Emprendimiento'],
                    ['code' => null, 'name' => 'Comercialización y Administración de Ventas'],
                    ['code' => null, 'name' => 'Operaciones y Logística'],
                    ['code' => null, 'name' => 'Plan de Negocios'],
                    ['code' => null, 'name' => 'Prácticas Pre Profesionales II'],
                ],
                8 => [
                    ['code' => null, 'name' => 'E commerce'],
                    ['code' => null, 'name' => 'Responsabilidad Social Empresarial'],
                    ['code' => null, 'name' => 'Liderazgo y Coaching Empresarial'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Marketing Digital' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Virtual',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => 'MKTD-B1-001', 'name' => 'Matemática'],
                    ['code' => 'MKTD-B1-002', 'name' => 'Herramientas Informáticas'],
                    ['code' => 'MKTD-B1-003', 'name' => 'Lenguaje y Comunicación'],
                    ['code' => 'MKTD-B1-004', 'name' => 'Fundamentos de Marketing'],
                    ['code' => 'MKTD-B1-005', 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => 'MKTD-B2-006', 'name' => 'Estadística Descriptiva'],
                    ['code' => 'MKTD-B2-007', 'name' => 'Diseño Digital I'],
                    ['code' => 'MKTD-B2-008', 'name' => 'Comportamiento del Consumidor'],
                    ['code' => 'MKTD-B2-009', 'name' => 'Investigación e Inteligencia de Mercado'],
                    ['code' => 'MKTD-B2-010', 'name' => 'Contabilidad'],
                ],
                3 => [
                    ['code' => 'MKTD-B3-011', 'name' => 'Estadística Inferencial'],
                    ['code' => 'MKTD-B3-012', 'name' => 'Diseño Digital II'],
                    ['code' => 'MKTD-B3-013', 'name' => 'Marketing de Contenidos'],
                    ['code' => 'MKTD-B3-014', 'name' => 'Gestión de la Calidad'],
                    ['code' => 'MKTD-B3-015', 'name' => 'Presupuesto y Análisis Financiero'],
                ],
                4 => [
                    ['code' => 'MKTD-P4-016', 'name' => 'Responsabilidad Social'],
                    ['code' => 'MKTD-P4-017', 'name' => 'Diseño Digital III'],
                    ['code' => 'MKTD-P4-018', 'name' => 'Gestión de Marca'],
                    ['code' => 'MKTD-P4-019', 'name' => 'Desarrollo del Producto y Políticas de Precios'],
                    ['code' => 'MKTD-P4-020', 'name' => 'Formulación de Proyectos'],
                ],
                5 => [
                    ['code' => 'MKTD-P5-021', 'name' => 'Inbound Marketing'],
                    ['code' => 'MKTD-P5-022', 'name' => 'Marketing en Motores de Búsqueda'],
                    ['code' => 'MKTD-P5-023', 'name' => 'Canales de Distribución y Trade Marketing'],
                    ['code' => 'MKTD-P5-024', 'name' => 'Dirección Estratégica de Ventas'],
                    ['code' => 'MKTD-P5-025', 'name' => 'Evaluación de Proyectos'],
                    ['code' => 'MKTD-P5-026', 'name' => 'Legislación Aplicada'],
                ],
                6 => [
                    ['code' => 'MKTD-P6-027', 'name' => 'Gestión de las Relaciones con el Cliente CRM'],
                    ['code' => 'MKTD-P6-028', 'name' => 'Neuromarketing'],
                    ['code' => 'MKTD-P6-029', 'name' => 'Social Media y Community Manager'],
                    ['code' => 'MKTD-P6-030', 'name' => 'Planificación Estratégica'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                7 => [
                    ['code' => 'MKTD-P7-031', 'name' => 'Emprendimiento'],
                    ['code' => 'MKTD-P7-032', 'name' => 'Audiencia y Planificación de Medios'],
                    ['code' => 'MKTD-P7-033', 'name' => 'Comercio Electrónico'],
                    ['code' => 'MKTD-P7-034', 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => 'MKTD-P8-035', 'name' => 'Promoción y Campañas Digitales'],
                    ['code' => 'MKTD-P8-036', 'name' => 'Marketing Estratégico'],
                    ['code' => 'MKTD-P8-037', 'name' => 'Inteligencia de Negocios'],
                    ['code' => 'MKTD-UIC8-038', 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Mercadotecnia' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => 'MDT-B1-001', 'name' => 'Matemática Financiera'],
                    ['code' => 'MDT-B1-002', 'name' => 'Fundamentos de Administración'],
                    ['code' => 'MDT-B1-003', 'name' => 'Lenguaje y Comunicación'],
                    ['code' => 'MDT-B1-004', 'name' => 'Fundamentos de Mercadotecnia'],
                    ['code' => 'MDT-B1-005', 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => 'MDT-B2-006', 'name' => 'Informática'],
                    ['code' => 'MDT-B2-007', 'name' => 'Fundamentos de Diseño Gráfico'],
                    ['code' => 'MDT-B2-008', 'name' => 'Métodos de Dirección y Administración'],
                    ['code' => 'MDT-B2-009', 'name' => 'Contabilidad General'],
                    ['code' => 'MDT-B2-010', 'name' => 'Microeconomía'],
                ],
                3 => [
                    ['code' => 'MDT-B3-011', 'name' => 'Investigación y Análisis de Mercados'],
                    ['code' => 'MDT-B3-012', 'name' => 'Diseño Gráfico'],
                    ['code' => 'MDT-B3-013', 'name' => 'Estadística'],
                    ['code' => 'MDT-B3-014', 'name' => 'Macroeconomía'],
                    ['code' => 'MDT-B3-015', 'name' => 'Derecho Mercantil y Societario'],
                ],
                4 => [
                    ['code' => 'MDT-P4-016', 'name' => 'Segmentación y Selección de Mercados'],
                    ['code' => 'MDT-P4-017', 'name' => 'Comportamiento del Consumidor'],
                    ['code' => 'MDT-P4-018', 'name' => 'Planeación Estratégica'],
                    ['code' => 'MDT-P4-019', 'name' => 'Presupuestos y Análisis Financiero'],
                    ['code' => 'MDT-P4-020', 'name' => 'Dirección Estratégica de Ventas'],
                ],
                5 => [
                    ['code' => 'MDT-P5-021', 'name' => 'Mercadotecnia de Servicios'],
                    ['code' => 'MDT-P5-022', 'name' => 'Gestión de la Calidad'],
                    ['code' => 'MDT-P5-023', 'name' => 'Formulación y Evaluación de Proyectos'],
                    ['code' => 'MDT-P5-024', 'name' => 'Mercadotecnia y Comunicación Política'],
                    ['code' => 'MDT-P5-025', 'name' => 'Mercadotecnia Social'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales I'],
                ],
                6 => [
                    ['code' => 'MDT-P6-026', 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => 'MDT-P6-027', 'name' => 'Gestión de Marca'],
                    ['code' => 'MDT-P6-028', 'name' => 'Mercadotecnia de Eventos y Relaciones Públicas'],
                    ['code' => 'MDT-P6-029', 'name' => 'Productos Publicitarios y Merchandising'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales II'],
                ],
                7 => [
                    ['code' => 'MDT-P7-030', 'name' => 'Emprendimiento'],
                    ['code' => 'MDT-P7-031', 'name' => 'Mercadotecnia Digital'],
                    ['code' => 'MDT-P7-032', 'name' => 'Neuromarketing'],
                    ['code' => 'MDT-P7-033', 'name' => 'Gerencia y Desarrollo del Producto'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => 'MDT-UIC8-034', 'name' => 'Gerencia de Mercadotecnia'],
                    ['code' => 'MDT-UIC8-035', 'name' => 'Canales y Trade Marketing'],
                    ['code' => 'MDT-UIC8-036', 'name' => 'Redacción Académica'],
                    ['code' => 'MDT-UIC8-037', 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Turismo y Hotelería' => [
            'faculty' => self::FAC_ADMIN,
            'modality' => 'Híbrida',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Matemática Financiera'],
                    ['code' => null, 'name' => 'OperaciónTurística y Hotelera'],
                    ['code' => null, 'name' => 'Patrimonio Natural y Biodiversidad'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Realidad Nacional y diversidad cultural'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Contabilidad de Costos para Establecimientos Turísticos'],
                    ['code' => null, 'name' => 'Operación de Front Office & Housekeeping'],
                    ['code' => null, 'name' => 'Patrimonio Cultural'],
                    ['code' => null, 'name' => 'Facilidades Turísticas'],
                    ['code' => null, 'name' => 'Geografía Turística'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Sistemas Turísticos'],
                    ['code' => null, 'name' => 'Gestión de Alimentos y Bebidas'],
                    ['code' => null, 'name' => 'Interpretación Turística Ambiental'],
                    ['code' => null, 'name' => 'Estadística aplicada al Turismo'],
                    ['code' => null, 'name' => 'Arte Colonial y contemporáneo del Ecuador'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Legislación Turística y Hotelera'],
                    ['code' => null, 'name' => 'Análisis Socioeconómico y Ambiental del Turismo'],
                    ['code' => null, 'name' => 'Gastronomía Nacional e Internacional'],
                    ['code' => null, 'name' => 'Turismo Rural y Desarrollo Local'],
                    ['code' => null, 'name' => 'Técnicas de Interpretación, Guianza y Animación'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Desarrollo Sostenible del Turismo'],
                    ['code' => null, 'name' => 'Marketing Turístico y Hotelero'],
                    ['code' => null, 'name' => 'Técnicas de Bares y Enología'],
                    ['code' => null, 'name' => 'Intermediación y Comercialización Turística'],
                    ['code' => null, 'name' => 'Gestión de la calidad Turística'],
                    ['code' => null, 'name' => 'Prácticas Laborales I'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Gestión de Impactos Ambientales'],
                    ['code' => null, 'name' => 'Tecnologías Aplicadas al Turismo y Hospitalidad'],
                    ['code' => null, 'name' => 'Planificación y Ordenamiento Territorial del Turismo'],
                    ['code' => null, 'name' => 'Prácticas Laborales II'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Investigación Aplicada al Turismo'],
                    ['code' => null, 'name' => 'Desarrollo de Destinos Turísticos'],
                    ['code' => null, 'name' => 'Gestión de Eventos y Congresos'],
                    ['code' => null, 'name' => 'Formulación y Evaluación de Proyectos Turísticos'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Modelo de Gestión de la Actividad Hotelera'],
                    ['code' => null, 'name' => 'Modelo de Gestión de la Actividad Turística'],
                    ['code' => null, 'name' => 'Emprendimiento e Innovación Turística'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Derecho' => [
            'faculty' => self::FAC_DERECHO,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Expresión Oral y Redacción Jurídica'],
                    ['code' => null, 'name' => 'Historia e Introducción al Derecho'],
                    ['code' => null, 'name' => 'Herramientas Informáticas'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Teoría del Derecho'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Filosofía del Derecho'],
                    ['code' => null, 'name' => 'Universidad Ética e Institucionalidad'],
                    ['code' => null, 'name' => 'Tecnologías de la Información y Comunicación'],
                    ['code' => null, 'name' => 'Investigación Jurídica'],
                    ['code' => null, 'name' => 'Derecho Civil I (Personas)'],
                    ['code' => null, 'name' => 'Sociología Jurídica'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Derecho Penal I'],
                    ['code' => null, 'name' => 'Derecho Constitucional I'],
                    ['code' => null, 'name' => 'Derecho Notarial y Registral'],
                    ['code' => null, 'name' => 'Derecho Civil II (Bienes)'],
                    ['code' => null, 'name' => 'Derecho Indígena'],
                    ['code' => null, 'name' => 'Derecho Internacional Público I'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Derecho Penal II'],
                    ['code' => null, 'name' => 'Derecho Constitucional II'],
                    ['code' => null, 'name' => 'Derecho Internacional Público II'],
                    ['code' => null, 'name' => 'Derecho Civil III (Sucesiones)'],
                    ['code' => null, 'name' => 'Derecho Tributario'],
                    ['code' => null, 'name' => 'Criminología y Criminalística'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Derecho Penal III'],
                    ['code' => null, 'name' => 'Derecho Mercantil y Societario'],
                    ['code' => null, 'name' => 'Medicina Legal'],
                    ['code' => null, 'name' => 'Derecho Civil IV (Contratos y Obligaciones)'],
                    ['code' => null, 'name' => 'Derecho de Familia'],
                    ['code' => null, 'name' => 'Derecho Económico y Financiero'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Derecho Laboral y Seguridad Social I'],
                    ['code' => null, 'name' => 'Derecho Administrativo y Contratación Pública'],
                    ['code' => null, 'name' => 'Derecho Procesal Penal I'],
                    ['code' => null, 'name' => 'Derecho Procesal Civil I'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Derecho Procesal Civil II'],
                    ['code' => null, 'name' => 'Derecho Procesal Penal II'],
                    ['code' => null, 'name' => 'Derecho de Propiedad Intelectual'],
                    ['code' => null, 'name' => 'Derecho Laboral y Seguridad Social II'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Clínica Jurídica Penal'],
                    ['code' => null, 'name' => 'Clínica Jurídica Constitucional'],
                    ['code' => null, 'name' => 'Clínica Jurídica Laboral'],
                    ['code' => null, 'name' => 'Clínica Jurídica Civil'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Sociología' => [
            'faculty' => self::FAC_DERECHO,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Introducción a la Economía'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Razonamiento Matemático'],
                    ['code' => null, 'name' => 'Introducción a la Sociología'],
                    ['code' => null, 'name' => 'Historia de las Ciencias Sociales y Cambios Sociales'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Economía Política I'],
                    ['code' => null, 'name' => 'Epistemología'],
                    ['code' => null, 'name' => 'Ofimática'],
                    ['code' => null, 'name' => 'Estadística I'],
                    ['code' => null, 'name' => 'Filosofía I'],
                    ['code' => null, 'name' => 'Sociología I'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Metodología de Investigación'],
                    ['code' => null, 'name' => 'Economía Política II'],
                    ['code' => null, 'name' => 'Historia Social Moderna'],
                    ['code' => null, 'name' => 'Filosofía II'],
                    ['code' => null, 'name' => 'Sociología II'],
                    ['code' => null, 'name' => 'Estadística II y Herramientas Informaticas'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Teorías del Desarrollo I'],
                    ['code' => null, 'name' => 'Teoría Política'],
                    ['code' => null, 'name' => 'Historia de América Latina'],
                    ['code' => null, 'name' => 'Sociología III'],
                    ['code' => null, 'name' => 'Tecnologías de la Información y Comunicación'],
                    ['code' => null, 'name' => 'Antropología'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Teorías del Desarrollo II'],
                    ['code' => null, 'name' => 'Historia de la Formación Social Ecuatoriana 1'],
                    ['code' => null, 'name' => 'Práctica Investigativa Problemas Globales, Nacionales y Locales'],
                    ['code' => null, 'name' => 'Sociología IV'],
                    ['code' => null, 'name' => 'Elaboración y Evaluación de Proyectos Sociales 1'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Planificación'],
                    ['code' => null, 'name' => 'Historia de la Formación Social Ecuatoriana 2'],
                    ['code' => null, 'name' => 'Sociología del Riesgo'],
                    ['code' => null, 'name' => 'Métodos Cualitativos de Investigación'],
                    ['code' => null, 'name' => 'Práctica de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Elaboración y Evaluación de Proyectos Sociales 2'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Sociología Instrumental'],
                    ['code' => null, 'name' => 'Métodos Cuantitativos de Investigación'],
                    ['code' => null, 'name' => 'Sociología Urbana y Rural'],
                    ['code' => null, 'name' => 'Sociología Comunitaria y de las Organizaciones'],
                    ['code' => null, 'name' => 'Práctica Preprofesional'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Gestión Social'],
                    ['code' => null, 'name' => 'Pensamiento Social Latinoamericano'],
                    ['code' => null, 'name' => 'Cultura, Género e Identidades'],
                    ['code' => null, 'name' => 'Planificación del Desarrollo Institucional'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Educación Básica' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Filosofía de la Educación'],
                    ['code' => null, 'name' => 'Sistemas y contextos socioeducativos en EB'],
                    ['code' => null, 'name' => 'Fundamentos epistemológicos de la Educación Básica'],
                    ['code' => null, 'name' => 'Tecnologías de la información y la comunicación aplicadas a la educación I'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Tecnologías de la información y la comunicación aplicadas a la educación II'],
                    ['code' => null, 'name' => 'Psicología general y evolutiva'],
                    ['code' => null, 'name' => 'Educación física'],
                    ['code' => null, 'name' => 'Estudios sociales'],
                    ['code' => null, 'name' => 'Matemática'],
                    ['code' => null, 'name' => 'Pedagogía General'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Educación cultural y artística'],
                    ['code' => null, 'name' => 'Psicología del aprendizaje'],
                    ['code' => null, 'name' => 'Lengua y Literatura'],
                    ['code' => null, 'name' => 'Políticas y Legislación Educativa'],
                    ['code' => null, 'name' => 'Ciencias naturales'],
                    ['code' => null, 'name' => 'Didáctica'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Ética profesional, valores y derechos humanos'],
                    ['code' => null, 'name' => 'Investigación Cualitativa'],
                    ['code' => null, 'name' => 'Inclusión e intervención educativa'],
                    ['code' => null, 'name' => 'Modelos educativos'],
                    ['code' => null, 'name' => 'Currículo'],
                    ['code' => null, 'name' => 'Neurociencia educativa'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Didáctica de Ciencias Naturales'],
                    ['code' => null, 'name' => 'Didáctica de Matemática'],
                    ['code' => null, 'name' => 'Didáctica de Estudios Sociales'],
                    ['code' => null, 'name' => 'Didácticas de Lengua y Literatura'],
                    ['code' => null, 'name' => 'Investigación Cuantitativa'],
                    ['code' => null, 'name' => 'Evaluación de los aprendizajes'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Emprendimiento'],
                    ['code' => null, 'name' => 'Sociología de la educación'],
                    ['code' => null, 'name' => 'Estrategias y ambientes metodológicos'],
                    ['code' => null, 'name' => 'Planificación Curricular'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Docencia y comunidad educativa'],
                    ['code' => null, 'name' => 'Estilo y Redacción I'],
                    ['code' => null, 'name' => 'Gestión Educativa'],
                    ['code' => null, 'name' => 'Titulación I'],
                    ['code' => null, 'name' => 'Práticas Pre Profesionales Laborales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Sexualidad y educación sexual'],
                    ['code' => null, 'name' => 'Introducción a las lenguas originarias y saberes ancestrales'],
                    ['code' => null, 'name' => 'Estilo y Redacción II'],
                    ['code' => null, 'name' => 'Estadística Educativa'],
                    ['code' => null, 'name' => 'Titulación II'],
                ],
            ],
        ],

        'Educación Inicial' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Filosofía de la Educación'],
                    ['code' => null, 'name' => 'Desarrollo de la Inteligencia'],
                    ['code' => null, 'name' => 'Informática para la Educación I'],
                    ['code' => null, 'name' => 'Familia y Sociedad'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Psicología del Desarrollo'],
                    ['code' => null, 'name' => 'Puericultura (Salud Integral)'],
                    ['code' => null, 'name' => 'Psicomotricidad y Lúdica'],
                    ['code' => null, 'name' => 'Desarrollo del Lenguaje hasta los 5 años'],
                    ['code' => null, 'name' => 'Pedagogía General'],
                    ['code' => null, 'name' => 'Informática para la Educación II'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Psicología del Aprendizaje'],
                    ['code' => null, 'name' => 'Educación Física Infantil'],
                    ['code' => null, 'name' => 'Música Infantil'],
                    ['code' => null, 'name' => 'Iniciación a la Lecto Escritura'],
                    ['code' => null, 'name' => 'Pedagogía y Didáctica en Educación Infantil'],
                    ['code' => null, 'name' => 'Nutrición y Dietética Infantil'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Estimulación Adecuada I'],
                    ['code' => null, 'name' => 'Manifestaciones Artísticas I'],
                    ['code' => null, 'name' => 'Neurociencia Educativa'],
                    ['code' => null, 'name' => 'Didáctica General'],
                    // Nombre cortado tal cual en el PDF oficial (ver nota de la clase).
                    ['code' => null, 'name' => 'Investigación Humana-Cultural-Educativa: Fundamentos en la'],
                    ['code' => null, 'name' => 'Primeros Auxilios'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Estimulación Adecuada II'],
                    ['code' => null, 'name' => 'Manifestaciones Artísticas II'],
                    ['code' => null, 'name' => 'Inclusión Educativa e Interculturalidad'],
                    ['code' => null, 'name' => 'Planificación Curricular'],
                    ['code' => null, 'name' => 'Investigación Humana-Cultural-Educativa: Enfoques Metodológicos'],
                    ['code' => null, 'name' => 'Literatura Infantil'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Evaluación de los Aprendizajes'],
                    ['code' => null, 'name' => 'Desarrollo del Pensamiento Lógico Matemático'],
                    ['code' => null, 'name' => 'Software en Educación Inicial'],
                    ['code' => null, 'name' => 'Necesidades Educativas Especiales'],
                    ['code' => null, 'name' => 'Investigación Humana-Cultural-Educativa: Enfoques Metodológicos'],
                    ['code' => null, 'name' => 'Medio Natural y Cultural'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Teatro, Payasería y Mimo'],
                    ['code' => null, 'name' => 'Gestión Educativa'],
                    ['code' => null, 'name' => 'Ética y Valores'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Prácticas Pre-Profesionales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Estudio de las Culturas y el Contexto'],
                    ['code' => null, 'name' => 'Estadística Educativa'],
                    ['code' => null, 'name' => 'Diseño y Elaboración de Material Didáctico'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Educación Intercultural Bilingüe' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Virtual',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Lenguaje y comunicación'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Filosofía de la educación'],
                    ['code' => null, 'name' => 'Cosmovisión y Epistemología del Sur'],
                    ['code' => null, 'name' => 'Historia del Ecuador: Contexto Regional y Global'],
                    ['code' => null, 'name' => 'Antropología sociocultural en la EIB'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Contextos socioeducativos y comunitarios en EIB'],
                    ['code' => null, 'name' => 'Tecnologías de la información y la comunicación aplicadas a la EIB I'],
                    ['code' => null, 'name' => 'Psicología General y evolutiva'],
                    ['code' => null, 'name' => 'Pedagogía'],
                    ['code' => null, 'name' => 'Teorías Sociopolíticas y Legislación aplicada EIB'],
                    ['code' => null, 'name' => 'Ética profesional, valores y derechos humanos'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Introducción a las lenguas originarias y saberes ancestrales'],
                    ['code' => null, 'name' => 'Tecnologías de la información y la comunicación aplicadas a la EIB II'],
                    ['code' => null, 'name' => 'Psicología del Aprendizaje'],
                    ['code' => null, 'name' => 'Educación Física Intercultural'],
                    ['code' => null, 'name' => 'Didáctica general'],
                    ['code' => null, 'name' => 'Expresiones artísticas del patrimonio cultural'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Lengua Kichwa I'],
                    ['code' => null, 'name' => 'Enseñanza-Aprendizaje de: Ciencias naturales y de la vida'],
                    ['code' => null, 'name' => 'Inclusión e intervención educativa'],
                    ['code' => null, 'name' => 'Neurociencia educativa'],
                    ['code' => null, 'name' => 'Enseñanza-Aprendizaje de: Etnomatemáticas de las civilizaciones, pueblos y nacionalidades'],
                    ['code' => null, 'name' => 'Investigación humana-cultural-educativa: fundamentos en la construcción del conocimiento científico'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Lengua Kichwa II'],
                    ['code' => null, 'name' => 'Currículum del Sistema de Educación Intercultural Bilingüe'],
                    ['code' => null, 'name' => 'Enseñanza-Aprendizaje de: Estudios sociales y etnohistoria'],
                    ['code' => null, 'name' => 'Enseñanza-Aprendizaje de: lenguas, etnolingüística y Literaturas'],
                    ['code' => null, 'name' => 'Estrategias metodológicas innovadoras del SEIB'],
                    ['code' => null, 'name' => 'Investigación humana-cultural-educativa: enfoques metodológicos cualitativos en el proceso investigativo'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Lengua Kichwa III'],
                    ['code' => null, 'name' => 'Planificación Curricular del SEIB'],
                    ['code' => null, 'name' => 'Liderazgo, emprendimiento educativo comunitario'],
                    ['code' => null, 'name' => 'Arqueología'],
                    ['code' => null, 'name' => 'Evaluación de los aprendizajes'],
                    ['code' => null, 'name' => 'Investigación humana-cultural-educativa: enfoques metodológicos cuantitativos en el proceso investigativo'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Lengua Kichwa IV'],
                    ['code' => null, 'name' => 'Gestión Educativa y Comunitaria'],
                    ['code' => null, 'name' => 'Estilo y redacción de trabajos académicos I'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Prácticas Pre-Profesionales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Lengua Kichwa V'],
                    ['code' => null, 'name' => 'Estadística Educativa'],
                    ['code' => null, 'name' => 'Estilo y redacción de trabajos académicos II'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Pedagogía de los Idiomas Nacionales y Extranjeros' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Híbrida',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'English I'],
                    ['code' => null, 'name' => 'Basic Comunicative Grammar I'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Politica Educativa y Marco Legal en Educación'],
                    ['code' => null, 'name' => 'Pedagogía'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => null, 'name' => 'English II'],
                    ['code' => null, 'name' => 'Basic Comunicative Grammar II'],
                    ['code' => null, 'name' => 'Didactica General'],
                    ['code' => null, 'name' => 'Technological Tools in EFL'],
                    ['code' => null, 'name' => 'Psicología del Aprendizaje'],
                ],
                3 => [
                    ['code' => null, 'name' => 'English III'],
                    ['code' => null, 'name' => 'Curriculum Design and Planning'],
                    ['code' => null, 'name' => 'Basic Comunicative Grammar III'],
                    ['code' => null, 'name' => 'Linguistics'],
                    ['code' => null, 'name' => 'Neurociencia Educativa'],
                    ['code' => null, 'name' => 'Phonetics and Phonology I'],
                ],
                4 => [
                    ['code' => null, 'name' => 'English B-1'],
                    ['code' => null, 'name' => 'Syntax'],
                    ['code' => null, 'name' => 'Professional Development'],
                    ['code' => null, 'name' => 'Translation Techniques'],
                    ['code' => null, 'name' => 'Phonetics and Phonology II'],
                    ['code' => null, 'name' => 'Literature in EFL Classroom'],
                    ['code' => null, 'name' => 'English B-2.1'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Semantics and Pragmatics'],
                    ['code' => null, 'name' => 'Assessment and Evaluation'],
                    ['code' => null, 'name' => 'English Methodology and Didactics I'],
                    ['code' => null, 'name' => 'Investigación Cualitativa y Cuantitativa'],
                    ['code' => null, 'name' => 'Sociolinguistics'],
                ],
                6 => [
                    ['code' => null, 'name' => 'English B-2.2'],
                    ['code' => null, 'name' => 'Language Development Integrated Skills English I'],
                    ['code' => null, 'name' => 'New Trends in English Methodology and Didactics II'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Discourse Analysis'],
                    ['code' => null, 'name' => 'Academic Writing'],
                    ['code' => null, 'name' => 'New Trends in English Methodology and Didactics III'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Language Development: Conversation and Argumentation English II'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'International Exam Preparation'],
                    ['code' => null, 'name' => 'Academic Writing Research Paper English - Project Design'],
                    ['code' => null, 'name' => 'Educational Management'],
                    ['code' => null, 'name' => 'Estadistica'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Pedagogía de la Matemática y la Física' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Híbrida',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Filosofía de la Educación'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Trigonometría'],
                    ['code' => null, 'name' => 'Matemática Básica'],
                    ['code' => null, 'name' => 'Dibujo Técnico'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Pedagogía General'],
                    ['code' => null, 'name' => 'Estatica y Cinemática'],
                    ['code' => null, 'name' => 'Geometría Plana'],
                    ['code' => null, 'name' => 'Matemática Estructurada'],
                    ['code' => null, 'name' => 'Psicología del Desarrollo'],
                    ['code' => null, 'name' => 'Fundamentos Epistemológicos de la Investigación Humana, Cultural Educativa'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Didactica General'],
                    ['code' => null, 'name' => 'Dinámica'],
                    ['code' => null, 'name' => 'Geometría Analítica Plana'],
                    ['code' => null, 'name' => 'Análisis Matemático'],
                    ['code' => null, 'name' => 'Psicología del Aprendizaje'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Ondas y Calor'],
                    ['code' => null, 'name' => 'Geometría Analítica Tridimencional'],
                    ['code' => null, 'name' => 'Cálculo Diferencial'],
                    ['code' => null, 'name' => 'Investigación Cualitativa y Cuantitativa'],
                    ['code' => null, 'name' => 'Estadistica Descriptiva'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Planificación Curricular'],
                    ['code' => null, 'name' => 'Termodinámica'],
                    ['code' => null, 'name' => 'Cálculo Integral'],
                    ['code' => null, 'name' => 'Educación Inclusiva en Atención a la Diversidad'],
                    ['code' => null, 'name' => 'Proyectos Educativos y Comunitarios'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Evaluación de Aprendizajes'],
                    ['code' => null, 'name' => 'Electricidad'],
                    ['code' => null, 'name' => 'Legislación Educativa'],
                    ['code' => null, 'name' => 'Ecuaciones Diferenciales'],
                    ['code' => null, 'name' => 'Sofware de Matemática y Física'],
                    ['code' => null, 'name' => 'Didáctica de la Matemática y la Física'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Gestión Educativa'],
                    ['code' => null, 'name' => 'Electromagnetismo'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                    ['code' => null, 'name' => 'Matemática Financiera'],
                    ['code' => null, 'name' => 'Educación Física'],
                    ['code' => null, 'name' => 'Probabilidad y Estadistica'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Fisica Moderna'],
                    ['code' => null, 'name' => 'Algebra Superior'],
                    ['code' => null, 'name' => 'Matemática Discreta'],
                    ['code' => null, 'name' => 'Trabajo de Tutulación e Integración Curricular'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
            ],
        ],

        'Pedagogía de las Ciencias Experimentales – Informática' => [
            'faculty' => self::FAC_EDUCACION,
            'modality' => 'Virtual',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Filosofía de la Educación'],
                    ['code' => null, 'name' => 'Matemáticas I'],
                    ['code' => null, 'name' => 'Lenguajes Algorítmicos'],
                    ['code' => null, 'name' => 'Sociedad Contemporánea y Política Educativa en la Era Digital'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Pedagogía'],
                    ['code' => null, 'name' => 'Matemáticas II'],
                    ['code' => null, 'name' => 'Programación I'],
                    ['code' => null, 'name' => 'Informática para la Educación'],
                    ['code' => null, 'name' => 'Psicología del Aprendizaje'],
                    ['code' => null, 'name' => 'Física'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Sistemas Operativos'],
                    ['code' => null, 'name' => 'Sexualidad y Educación Sexual'],
                    ['code' => null, 'name' => 'Programación Orientada a Objetos'],
                    ['code' => null, 'name' => 'Innovación Educativa'],
                    ['code' => null, 'name' => 'Neurociencia Educativa'],
                    ['code' => null, 'name' => 'Electrónica'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Planificación Curricular'],
                    ['code' => null, 'name' => 'Base de Datos I'],
                    ['code' => null, 'name' => 'Programación Visual'],
                    ['code' => null, 'name' => 'Diseño de Materiales y Recursos Educativos Digitales'],
                    ['code' => null, 'name' => 'La Inclusión y Digiculturalidad'],
                    ['code' => null, 'name' => 'Robótica Educativa'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Didactica de la Informática'],
                    ['code' => null, 'name' => 'Base de Datos II'],
                    ['code' => null, 'name' => 'Mantenimiento de los Ordenadores'],
                    ['code' => null, 'name' => 'Inteligencia Artificial en la Educación'],
                    ['code' => null, 'name' => 'Epistemología y Fundamentos de la Investigación'],
                    ['code' => null, 'name' => 'Software Educativo I'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Realidad Virtual y Aumentada en la Educación'],
                    ['code' => null, 'name' => 'Software Educativo II'],
                    ['code' => null, 'name' => 'Redes de Computadoras I'],
                    ['code' => null, 'name' => 'Emprendimiento'],
                    ['code' => null, 'name' => 'Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Gamificación Educativa'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Diseño y Gestión de Ambientes de Aprendizaje'],
                    ['code' => null, 'name' => 'Diseño Web I'],
                    ['code' => null, 'name' => 'Redes de Computadoras II'],
                    ['code' => null, 'name' => 'Titulación I'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Diseño Web II'],
                    ['code' => null, 'name' => 'Estadística Educativa'],
                    ['code' => null, 'name' => 'Evaluación de los Aprendizajes'],
                    ['code' => null, 'name' => 'Gestión Educativa'],
                    ['code' => null, 'name' => 'Titulación II'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
            ],
        ],

        'Agroindustrias' => [
            'faculty' => self::FAC_AGROPECUARIA,
            'modality' => 'Presencial',
            'cycles' => 9,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Algebra Lineal'],
                    ['code' => null, 'name' => 'Física'],
                    ['code' => null, 'name' => 'Química General e Inorgánica'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Cálculo Diferencial e Integral'],
                    ['code' => null, 'name' => 'Tecnologías de la Información y Comunicación'],
                    ['code' => null, 'name' => 'Termodinámica'],
                    ['code' => null, 'name' => 'Química Orgánica'],
                    ['code' => null, 'name' => 'Biología Celular y Molecular'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Ecuaciones Diferenciales'],
                    ['code' => null, 'name' => 'Estadística y Probabilidad'],
                    ['code' => null, 'name' => 'Mecánica de Fluidos'],
                    ['code' => null, 'name' => 'Bioquímica'],
                    ['code' => null, 'name' => 'Microbiología'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Cálculos Básicos de Ingeniería'],
                    ['code' => null, 'name' => 'Diseño Experimental'],
                    ['code' => null, 'name' => 'Transferencia de Calor'],
                    ['code' => null, 'name' => 'Investigación Operativa'],
                    ['code' => null, 'name' => 'Química Analítica'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Operaciones Unitarias'],
                    ['code' => null, 'name' => 'Diseño y Automatización de Plantas Agroindustriales'],
                    ['code' => null, 'name' => 'Ingeniería de Poscosecha'],
                    ['code' => null, 'name' => 'Ingeniería de Procesos'],
                    ['code' => null, 'name' => 'Análisis Instrumental'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Procesos Tecnológicos'],
                    ['code' => null, 'name' => 'Modelación y Simulación de Procesos Agroindustriales'],
                    ['code' => null, 'name' => 'Seguridad Alimentaria y Gestión de la Calidad'],
                    ['code' => null, 'name' => 'Industria de Frutas y Hortalizas'],
                    ['code' => null, 'name' => 'Análisis y Composición de Productos Agroindustriales'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Biotecnología'],
                    ['code' => null, 'name' => 'Procesos Agroindustriales No Alimentarios'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Industrialización de Productos Lácteos'],
                    ['code' => null, 'name' => 'Labor Comunitario'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Bioingeniería'],
                    ['code' => null, 'name' => 'Gestión Ambiental'],
                    ['code' => null, 'name' => 'Diseño y Desarrollo de Nuevos Productos'],
                    ['code' => null, 'name' => 'Industrialización de Cereales, Leguminosas y Oleaginosas'],
                    ['code' => null, 'name' => 'Industrialización de Productos Cárnicos, Acuícolas y Pesqueros'],
                ],
                9 => [
                    ['code' => null, 'name' => 'Proyectos Agroindustriales'],
                    ['code' => null, 'name' => 'Gestión Empresarial y Mercadeo'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Agronomía' => [
            'faculty' => self::FAC_AGROPECUARIA,
            'modality' => 'Presencial',
            'cycles' => 9,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Biología'],
                    ['code' => null, 'name' => 'Quimica Inorgánica'],
                    ['code' => null, 'name' => 'Matemáticas'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Botanica General'],
                    ['code' => null, 'name' => 'Química Orgánica'],
                    ['code' => null, 'name' => 'Cálculo Diferenecial e Integral'],
                    ['code' => null, 'name' => 'Física'],
                    ['code' => null, 'name' => 'Informática Aplicada'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Botanica Sistemática'],
                    ['code' => null, 'name' => 'Agroquímica'],
                    ['code' => null, 'name' => 'Topografía'],
                    ['code' => null, 'name' => 'Meteorología Agrícola'],
                    ['code' => null, 'name' => 'Introducción a la Agronomía'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Fisiología Vegetal'],
                    ['code' => null, 'name' => 'Edafología'],
                    ['code' => null, 'name' => 'Riego y Drenaje'],
                    ['code' => null, 'name' => 'Mecanización Agrícola'],
                    ['code' => null, 'name' => 'Agroecología'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Nutrición Vegetal'],
                    ['code' => null, 'name' => 'Microbiología Agrícola'],
                    ['code' => null, 'name' => 'Bioestadística'],
                    ['code' => null, 'name' => 'Genética'],
                    ['code' => null, 'name' => 'Manejo de Cuencas Hidrográficas'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Biología Molecular'],
                    ['code' => null, 'name' => 'Fitopatología'],
                    ['code' => null, 'name' => 'Análisis y Diseño Experimental'],
                    ['code' => null, 'name' => 'Fitomejoramiento'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Biotecnología Agrícola'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Entomología General'],
                    ['code' => null, 'name' => 'Cultivos de Clima Templado y Frío'],
                    ['code' => null, 'name' => 'Practicas Preprofesionales I'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Sistemas de Producción Agrícola'],
                    ['code' => null, 'name' => 'Horticultura'],
                    ['code' => null, 'name' => 'Protección de Cultivos'],
                    ['code' => null, 'name' => 'Cultivos Tropicales'],
                    ['code' => null, 'name' => 'Practicas Preprofesionales II'],
                ],
                9 => [
                    ['code' => null, 'name' => 'Desarrollo Rural'],
                    ['code' => null, 'name' => 'Fruticultura'],
                    ['code' => null, 'name' => 'Gestión de Empresas Agrícolas'],
                    ['code' => null, 'name' => 'Tecnología de Semillas'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Medicina Veterinaria' => [
            'faculty' => self::FAC_AGROPECUARIA,
            'modality' => 'Presencial',
            'cycles' => 9,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Matemáticas'],
                    ['code' => null, 'name' => 'Anatomía I'],
                    ['code' => null, 'name' => 'Informática'],
                    ['code' => null, 'name' => 'Biología'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Bioestadística'],
                    ['code' => null, 'name' => 'Química'],
                    ['code' => null, 'name' => 'Anatomía II'],
                    ['code' => null, 'name' => 'Embriología'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Zoología General'],
                    ['code' => null, 'name' => 'Bioquímica'],
                    ['code' => null, 'name' => 'Fisiología I'],
                    ['code' => null, 'name' => 'Histología'],
                    ['code' => null, 'name' => 'Genética Animal'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Pastos y Forrajes'],
                    ['code' => null, 'name' => 'Farmacología General'],
                    ['code' => null, 'name' => 'Fisiología II'],
                    ['code' => null, 'name' => 'Microbiología'],
                    ['code' => null, 'name' => 'Nutrición Animal'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Etología y Bienestar Animal'],
                    ['code' => null, 'name' => 'Toxicología'],
                    ['code' => null, 'name' => 'Fisiopatología'],
                    ['code' => null, 'name' => 'Virología e Inmunología'],
                    ['code' => null, 'name' => 'Semiología y Propedeutica'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Epidemiología y Salud Pública'],
                    ['code' => null, 'name' => 'Laboratorio Clínico'],
                    ['code' => null, 'name' => 'Patología General'],
                    ['code' => null, 'name' => 'Enfermedades Infecciosas'],
                    ['code' => null, 'name' => 'Parasitología'],
                    ['code' => null, 'name' => 'Prácticas Pre Profesionales I'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Salud y Bienestar de la Fauna Silvestre'],
                    ['code' => null, 'name' => 'Epistemología y Metodología de la Investigación'],
                    ['code' => null, 'name' => 'Clínica Menor'],
                    ['code' => null, 'name' => 'Biotecnologías de la Reproducción Animal'],
                    ['code' => null, 'name' => 'Prácticas Pre Profesionales II'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Diseño Experimental'],
                    ['code' => null, 'name' => 'Clínica Mayor'],
                    ['code' => null, 'name' => 'Imagenología'],
                    ['code' => null, 'name' => 'Cirugía Menor'],
                    ['code' => null, 'name' => 'Gestión y Emprendimiento de Empresas Veterinarias'],
                    ['code' => null, 'name' => 'Servicio Comunitario'],
                ],
                9 => [
                    ['code' => null, 'name' => 'Medicina y Salud de Animales de Granja'],
                    ['code' => null, 'name' => 'Cirugía Mayor'],
                    ['code' => null, 'name' => 'Medicina Interna'],
                    ['code' => null, 'name' => 'Desarrollo Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Enfermería' => [
            'faculty' => self::FAC_SALUD,
            'modality' => 'Presencial',
            'cycles' => 9,
            // Malla con estructura de núcleos/"proyecto integrador" transversal
            // a varios periodos, no una cuadrícula simple de 1 asignatura por
            // celda: se deja sin asignaturas para que se carguen a mano o por
            // CSV una vez confirmada la estructura con la carrera.
            'subjects' => [],
        ],

        'Ingeniería en Riesgos de Desastres' => [
            'faculty' => self::FAC_SALUD,
            'modality' => 'Presencial',
            'cycles' => 10,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Algebra Lineal y Trigonometría'],
                    ['code' => null, 'name' => 'Física I'],
                    ['code' => null, 'name' => 'Química General'],
                    ['code' => null, 'name' => 'Informática I'],
                    ['code' => null, 'name' => 'Interculturalidad y Saberes Ancestrales'],
                    ['code' => null, 'name' => 'Expresión Oral y Escrita'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Cálculo Diferencial'],
                    ['code' => null, 'name' => 'Física II'],
                    ['code' => null, 'name' => 'Estadística Descriptiva'],
                    ['code' => null, 'name' => 'Informática II'],
                    ['code' => null, 'name' => 'Responsabilidad Social y Ética'],
                    ['code' => null, 'name' => 'Comunicación e Información'],
                ],
                // El PDF oficial imprime 760h para este ciclo pero la suma real
                // de sus 7 materias (según las horas impresas por materia) da
                // 880h; la diferencia coincide con "Comunicación e Información",
                // que se repite igual en el Ciclo 2. Se deja tal cual aparece
                // en el documento (ver nota de la clase).
                3 => [
                    ['code' => null, 'name' => 'Calculo Integral'],
                    ['code' => null, 'name' => 'Geografía Física'],
                    ['code' => null, 'name' => 'Fundamentos de Riesgos de desastres'],
                    ['code' => null, 'name' => 'Estadística Inferencial'],
                    ['code' => null, 'name' => 'Informática II'],
                    ['code' => null, 'name' => 'Responsabilidad Social y Ética'],
                    ['code' => null, 'name' => 'Comunicación e Información'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Modelamiento y simulación'],
                    ['code' => null, 'name' => 'Amenazas Naturales'],
                    ['code' => null, 'name' => 'Geología'],
                    ['code' => null, 'name' => 'Cartografía'],
                    ['code' => null, 'name' => 'Metodología de la Investigación'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Fundamentos de la Gestión del Riesgo'],
                    ['code' => null, 'name' => 'Geomorfología'],
                    ['code' => null, 'name' => 'Hidrometereológica'],
                    ['code' => null, 'name' => 'Sismología'],
                    ['code' => null, 'name' => 'Fotointerpretación'],
                    ['code' => null, 'name' => 'Metodología de la Investigación'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Amenazas Antrópicas'],
                    ['code' => null, 'name' => 'Vulnerabilidades'],
                    ['code' => null, 'name' => 'Hidrogeología'],
                    ['code' => null, 'name' => 'Vulcanología'],
                    ['code' => null, 'name' => 'Sistemas de Información Geográfica Básica'],
                    ['code' => null, 'name' => 'Ecología y Ambiente'],
                    ['code' => null, 'name' => 'Vinculación con la Comunidad'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Evaluación de Amenazas'],
                    ['code' => null, 'name' => 'Evaluación de Vulnerabilidades'],
                    ['code' => null, 'name' => 'Sistemas de Información Geográfica Aplicado'],
                    ['code' => null, 'name' => 'Cambio Climático'],
                    ['code' => null, 'name' => 'Prácticas preprofesionales'],
                    ['code' => null, 'name' => 'Administración'],
                    ['code' => null, 'name' => 'Marco Legal para la Gestión del Riesgo'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Programación'],
                    ['code' => null, 'name' => 'Evaluación de Riesgos'],
                    ['code' => null, 'name' => 'Evaluación de Impactos Ambientales'],
                    ['code' => null, 'name' => 'Prácticas preprofesionales'],
                    ['code' => null, 'name' => 'Planificación Estratégica'],
                ],
                9 => [
                    ['code' => null, 'name' => 'Planes de Reducción de Riesgos'],
                    ['code' => null, 'name' => 'Evaluación y Mitigación de Riesgos Estructurales'],
                    ['code' => null, 'name' => 'Planificación del Desarrollo y Ordenamiento Territorial'],
                    ['code' => null, 'name' => 'Trabajo de titulación I'],
                ],
                10 => [
                    ['code' => null, 'name' => 'Sistemas de Manejo de Desastres'],
                    ['code' => null, 'name' => 'Recuperación, Rehabilitación y Resiliencia'],
                    ['code' => null, 'name' => 'Trabajo de titulación II'],
                ],
            ],
        ],

        'Psicología' => [
            'faculty' => self::FAC_SALUD,
            // Ciclos corregidos a 8 (no 9): la malla oficial real solo tiene
            // niveles I-VIII, verificado contra el total de asignaturas y
            // horas impreso en el PDF.
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Comunicación Profesional en Salud'],
                    ['code' => null, 'name' => 'Epistemología'],
                    ['code' => null, 'name' => 'Bases Biológicas de la Conducta'],
                    ['code' => null, 'name' => 'Ofimática'],
                    ['code' => null, 'name' => 'Psicología General'],
                    ['code' => null, 'name' => 'Fundamentos Teóricos e Históricos de la Psicología'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Psicología del Desarrollo del Niño y del Adolescente'],
                    ['code' => null, 'name' => 'Neurociencia'],
                    ['code' => null, 'name' => 'Corrientes Psicológicas I'],
                    ['code' => null, 'name' => 'Procesos Psicológicos Básicos'],
                    ['code' => null, 'name' => 'Actividad Física'],
                    ['code' => null, 'name' => 'Psicología de la Comunicación'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Corrientes Psicológicas II'],
                    ['code' => null, 'name' => 'Psicología del Desarrollo del Adulto y Adulto Mayor'],
                    ['code' => null, 'name' => 'Procesos Psicológicos Complejos'],
                    ['code' => null, 'name' => 'Psicología de la Familia'],
                    ['code' => null, 'name' => 'Psicología Social'],
                    ['code' => null, 'name' => 'Psicofisiología'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Psicología del Genero y Sexualidad'],
                    ['code' => null, 'name' => 'Psicofarmacología'],
                    ['code' => null, 'name' => 'Psicología de la Personalidad'],
                    ['code' => null, 'name' => 'Inclusión y Diversidad Humana'],
                    ['code' => null, 'name' => 'Técnicas de Abordaje Grupal'],
                    ['code' => null, 'name' => 'Fundamentos de la Investigación'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Neuropsicología'],
                    ['code' => null, 'name' => 'Manejo y Mediación de Conflictos'],
                    ['code' => null, 'name' => 'Psicopatología I'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales I'],
                    ['code' => null, 'name' => 'Psicología en Emergencias y Desastres'],
                    ['code' => null, 'name' => 'Psicometría'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Intervención en Crisis'],
                    ['code' => null, 'name' => 'Psicopatología II'],
                    ['code' => null, 'name' => 'Evaluación Neuropsicológica del Niño y Adolescente'],
                    ['code' => null, 'name' => 'Prácticas Preprofesionales II'],
                    ['code' => null, 'name' => 'Psicología Jurídica'],
                    ['code' => null, 'name' => 'Psicoestadística'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Evaluación Neuropsicológica del Adulto y Adulto Mayor'],
                    ['code' => null, 'name' => 'Técnicas y Pruebas Psicológicas'],
                    ['code' => null, 'name' => 'Psicoterapia I'],
                    ['code' => null, 'name' => 'Diseños Experimentales en Psicología'],
                    ['code' => null, 'name' => 'Prácticas Pre Profesionales III'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Estimulación y Rehabilitación Cognitiva'],
                    ['code' => null, 'name' => 'Psicodiagnóstico'],
                    ['code' => null, 'name' => 'Psicoterapia II'],
                    ['code' => null, 'name' => 'Psicología de la Salud'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Terapia Física' => [
            'faculty' => self::FAC_SALUD,
            'modality' => 'Presencial',
            'cycles' => 9,
            'subjects' => [
                1 => [
                    ['code' => 'FT.AF1.01', 'name' => 'Anatomía Funcional I'],
                    ['code' => 'FT.HE.01', 'name' => 'Histo-Embriología'],
                    ['code' => 'FT.ITF.01', 'name' => 'Introducción a la Terapia Física'],
                    ['code' => 'ISCC.SA.01', 'name' => 'Socioantropología'],
                    ['code' => 'ISCC.RNDS.01', 'name' => 'Realidad Nacional Desarrollo Sustentable'],
                    ['code' => 'CL.LC.01', 'name' => 'Lenguaje y Comunicación'],
                ],
                2 => [
                    ['code' => 'FT.AF2.02', 'name' => 'Anatomía Funcional II'],
                    ['code' => 'FT.FSL.02', 'name' => 'Fisiología'],
                    ['code' => 'FT.PG.02', 'name' => 'Patología General'],
                    ['code' => 'FT.BQ.02', 'name' => 'Bioquímica'],
                    ['code' => 'FT.BF.02', 'name' => 'Biofísica'],
                ],
                3 => [
                    ['code' => 'FT.PA.03', 'name' => 'Primeros Auxilios'],
                    ['code' => 'FT.BS.03', 'name' => 'Bioseguridad'],
                    ['code' => 'FT.NT.03', 'name' => 'Nutrición'],
                    ['code' => 'FT.SL.03', 'name' => 'Semiología'],
                    ['code' => 'EMI.EL.03', 'name' => 'Epidemiología'],
                    ['code' => 'FT.BE.03', 'name' => 'Bioética'],
                ],
                4 => [
                    ['code' => 'PP.PGO1.04', 'name' => 'Prácticas Formativas Observacionales'],
                    ['code' => 'PP.TMMV.04', 'name' => 'Técnicas de Movilización Vendaje y Masoterapia'],
                    ['code' => 'PP.BM.04', 'name' => 'Biomecánica'],
                    ['code' => 'PP.KT.04', 'name' => 'Kinesioterapia'],
                    ['code' => 'PP.OP.04', 'name' => 'Órtesis & Prótesis'],
                    ['code' => 'PP.TRB.04', 'name' => 'Traumatología y Radiología Básica'],
                    ['code' => 'CL.PSL.04', 'name' => 'Psicología'],
                ],
                5 => [
                    ['code' => 'PP.PF02.05', 'name' => 'Prácticas Formativas Observacionales'],
                    ['code' => 'PP.FPET.05', 'name' => 'Fisioterapia Pediátrica'],
                    ['code' => 'PP.EG.05', 'name' => 'Ergonomía'],
                    ['code' => 'PP.AFE.05', 'name' => 'Agentes Físicos y Electroterapia'],
                    ['code' => 'PP.MT.05', 'name' => 'Mecanoterapia'],
                    ['code' => 'PP.FPSQ.05', 'name' => 'Fisioterapia Psiquiátrica'],
                ],
                6 => [
                    ['code' => 'PP.PFO3.06', 'name' => 'Prácticas Formativas Observacionales'],
                    ['code' => 'PP.ECO.06', 'name' => 'Farmacología en Fisioterapia'],
                    ['code' => 'PP.SMP.06', 'name' => 'Terapia Manual Técnicas Posturales'],
                    ['code' => 'PP.NA.06', 'name' => 'Neuroanatomía'],
                    ['code' => 'EMI.BE.06', 'name' => 'Bioestadística'],
                    ['code' => 'ISCC.DS1.06', 'name' => 'Discapacidad y Sociedad'],
                ],
                7 => [
                    ['code' => 'PP.PPP.07', 'name' => 'Prácticas Pre-Profesionales I'],
                    ['code' => 'PP.FCQ.07', 'name' => 'Fisioterapia Clínico Quirúrgica'],
                    ['code' => 'PP.FG.07', 'name' => 'Fisioterapia Geriátrica'],
                    ['code' => 'EMI.MI.07', 'name' => 'Metodología de la Investigación'],
                    ['code' => 'EMI.PV.07', 'name' => 'Proyecto de Vinculación'],
                    ['code' => 'ISCC.DS2.07', 'name' => 'Discapacidad y Sociedad II'],
                ],
                8 => [
                    ['code' => 'PP.PPP2.08', 'name' => 'Prácticas Pre-Profesionales II'],
                    ['code' => 'PP.FN1.08', 'name' => 'Fisioterapia Neurológica I'],
                    ['code' => 'PP.FT1.08', 'name' => 'Fisioterapia Traumatológica I'],
                    ['code' => 'PP.FCR1.08', 'name' => 'Fisioterapia Cardio Respiratoria I'],
                    ['code' => 'PP.FD1.08', 'name' => 'Fisioterapia Deportiva I'],
                    ['code' => 'EMI.TT1.08', 'name' => 'Trabajo de Titulación I'],
                ],
                9 => [
                    ['code' => 'PP.PPP3.09', 'name' => 'Prácticas Pre-Profesionales III'],
                    ['code' => 'PP.FN2.09', 'name' => 'Fisioterapia Neurológica II'],
                    ['code' => 'PP.FT2.09', 'name' => 'Fisioterapia Traumatológica II'],
                    ['code' => 'PP.FCR2.09', 'name' => 'Fisioterapia Cardio Respiratoria II'],
                    ['code' => 'PP.PD2.09', 'name' => 'Fisioterapia Deportiva II'],
                    ['code' => 'EMI.TT2.09', 'name' => 'Trabajo de Titulación II'],
                ],
            ],
        ],

        'Criminalística' => [
            'faculty' => self::FAC_SAN_MIGUEL,
            'modality' => 'Híbrida',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Lenguaje y Comunicación'],
                    ['code' => null, 'name' => 'Derecho Constitucional'],
                    ['code' => null, 'name' => 'Análisis y Razonamiento Matemático'],
                    ['code' => null, 'name' => 'Principios Básicos de Física'],
                    ['code' => null, 'name' => 'Principios Básicos de Química'],
                    ['code' => null, 'name' => 'Realidad Nacional y Diversidad Cultural'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Criminalística I'],
                    ['code' => null, 'name' => 'Fundamentos de la Investigación'],
                    ['code' => null, 'name' => 'Física Forense'],
                    ['code' => null, 'name' => 'Química Forense'],
                    ['code' => null, 'name' => 'Informática'],
                    ['code' => null, 'name' => 'Introducción al Derecho'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Documentología y Grafología'],
                    ['code' => null, 'name' => 'Balística Forense I'],
                    ['code' => null, 'name' => 'Biología'],
                    ['code' => null, 'name' => 'Sociología Criminal'],
                    ['code' => null, 'name' => 'Derecho Penal'],
                    ['code' => null, 'name' => 'Psicología Criminal'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Estadística Descriptiva y Aplicada'],
                    ['code' => null, 'name' => 'Identidad Humana'],
                    ['code' => null, 'name' => 'Balística Forense II'],
                    ['code' => null, 'name' => 'Derecho Penal Sustantivo'],
                    ['code' => null, 'name' => 'Criminología'],
                    ['code' => null, 'name' => 'Biología Forense'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Inspección Ocular Técnica I'],
                    ['code' => null, 'name' => 'Derecho Penal Adjetivo I'],
                    ['code' => null, 'name' => 'Deontología'],
                    ['code' => null, 'name' => 'Informática Forense, Audio, Video y Afines'],
                    ['code' => null, 'name' => 'Accidentología Vial'],
                    ['code' => null, 'name' => 'Emprendimiento e Innovación'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Inspección Ocular Técnica II'],
                    ['code' => null, 'name' => 'Teoría de la Prueba'],
                    ['code' => null, 'name' => 'Auditoría Forense'],
                    ['code' => null, 'name' => 'Derecho Penal Adjetivo II'],
                    ['code' => null, 'name' => 'Prácticas de Servicio Comunitario'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Medicina Legal y Forense'],
                    ['code' => null, 'name' => 'Antropología y Odontología Forense'],
                    ['code' => null, 'name' => 'Investigación Científica de Incendios y Explosivos'],
                    ['code' => null, 'name' => 'Redacción y Argumentación del Dictamen Pericial'],
                    ['code' => null, 'name' => 'Prácticas Laborales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Prácticas en Laboratorios Forenses'],
                    ['code' => null, 'name' => 'Patología Forense'],
                    ['code' => null, 'name' => 'Práctica en Inspección Ocular Técnica'],
                    ['code' => null, 'name' => 'Dictamen Pericial en el Juicio Oral'],
                    ['code' => null, 'name' => 'Trabajo de Titulación e Integración Curricular'],
                ],
            ],
        ],

        'Gestión del Talento Humano' => [
            'faculty' => self::FAC_SAN_MIGUEL,
            // Ciclos corregidos a 8 (no 9): la malla oficial real solo tiene
            // niveles I-VIII.
            'modality' => 'Presencial',
            'cycles' => 8,
            'subjects' => [
                1 => [
                    ['code' => null, 'name' => 'Contabilidad general'],
                    ['code' => null, 'name' => 'Fundamentos de la Administración'],
                    ['code' => null, 'name' => 'Realidad nacional y diversidad cultural'],
                    ['code' => null, 'name' => 'Lenguaje y comunicación'],
                    ['code' => null, 'name' => 'Comportamiento organizacional'],
                ],
                2 => [
                    ['code' => null, 'name' => 'Tecnologías de la información en la gestión del talento humano'],
                    ['code' => null, 'name' => 'Planificación y gestión del talento humano'],
                    ['code' => null, 'name' => 'Matemática'],
                    ['code' => null, 'name' => 'Legislación'],
                    ['code' => null, 'name' => 'Integración laboral en la diversidad'],
                ],
                3 => [
                    ['code' => null, 'name' => 'Planificación estratégica'],
                    ['code' => null, 'name' => 'Liderazgo y trabajo en equipo'],
                    ['code' => null, 'name' => 'Legislación laboral'],
                    ['code' => null, 'name' => 'Análisis estadístico'],
                    ['code' => null, 'name' => 'Responsabilidad social empresarial y ética profesional'],
                ],
                4 => [
                    ['code' => null, 'name' => 'Conflictos y Negociación'],
                    ['code' => null, 'name' => 'Análisis, descripción y valoración de cargos'],
                    ['code' => null, 'name' => 'Administración de sueldos y salarios'],
                    ['code' => null, 'name' => 'Clima y cultura organizacional'],
                    ['code' => null, 'name' => 'Gestión por competencias'],
                ],
                5 => [
                    ['code' => null, 'name' => 'Seguridad y salud ocupacional'],
                    ['code' => null, 'name' => 'Selección de Personal'],
                    ['code' => null, 'name' => 'Evaluación de Perfiles'],
                    ['code' => null, 'name' => 'Organización y métodos'],
                    ['code' => null, 'name' => 'Capacitación y desarrollo estratégico'],
                    ['code' => null, 'name' => 'Motivación Laboral'],
                ],
                6 => [
                    ['code' => null, 'name' => 'Ergonomía'],
                    ['code' => null, 'name' => 'Selección de personal para el sector público'],
                    ['code' => null, 'name' => 'Psicopatologías laborales'],
                    ['code' => null, 'name' => 'Metodología de la investigación'],
                    ['code' => null, 'name' => 'Evaluación de desempeño'],
                    ['code' => null, 'name' => 'Prácticas de servicio comunitario'],
                ],
                7 => [
                    ['code' => null, 'name' => 'Auditoría de la gestión de talento humano'],
                    ['code' => null, 'name' => 'Titulación I'],
                    ['code' => null, 'name' => 'Emprendimiento'],
                    ['code' => null, 'name' => 'Neuromanagement en el talento humano'],
                    ['code' => null, 'name' => 'Prácticas laborales'],
                ],
                8 => [
                    ['code' => null, 'name' => 'Desarrollo de habilidades gerenciales'],
                    ['code' => null, 'name' => 'Gestión de la calidad'],
                    ['code' => null, 'name' => 'Formulación y evaluación de proyectos'],
                    ['code' => null, 'name' => 'Titulación II'],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::FACULTIES as $name) {
            Facultad::query()->firstOrCreate(['nombre' => $name], ['estado' => true]);
        }

        $cycles = new GenerateCareerCycles;
        $now = now();

        foreach (self::CAREERS as $careerName => $data) {
            $faculty = Facultad::query()->where('nombre', $data['faculty'])->first();
            $modality = Modalidad::query()->firstOrCreate(['nombre' => $data['modality']], ['estado' => true]);

            $career = Carrera::query()->firstOrCreate(
                ['nombre' => $careerName],
                [
                    'fk_facultad' => $faculty->id_facultad,
                    'fk_modalidad' => $modality->id_modalidad,
                    'estado' => true,
                ]
            );

            $cycles->handle($career, $data['cycles']);

            foreach ($data['subjects'] as $cycleNumber => $subjects) {
                $cycleIds = Ciclo::query()
                    ->where('fk_carrera', $career->id_carrera)
                    ->where('numero', $cycleNumber)
                    ->pluck('id_ciclo');

                foreach ($subjects as $subject) {
                    $name = SubjectRequest::normalizeName($subject['name']);

                    $subjectId = DB::table('subjects')
                        ->where('career_id', $career->id_carrera)
                        ->where('name', $name)
                        ->value('id');

                    if ($subjectId === null) {
                        $subjectId = DB::table('subjects')->insertGetId([
                            'career_id' => $career->id_carrera,
                            'code' => $subject['code'],
                            'name' => $name,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    foreach ($cycleIds as $cycleId) {
                        $alreadyLinked = DB::table('subject_cycle')
                            ->where('subject_id', $subjectId)
                            ->where('cycle_id', $cycleId)
                            ->exists();

                        if (! $alreadyLinked) {
                            DB::table('subject_cycle')->insert([
                                'subject_id' => $subjectId,
                                'cycle_id' => $cycleId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
