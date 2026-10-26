<?php

return [
    // Escala oficial del formato "1. Reporte Evaluación Diagnóstica" (Plan de Tutorías
    // Académicas): Bajo 0%-50%, Medio 51%-69% y Alto 70%-100% sobre una nota de 10.
    // Con dos decimales, todo lo que supera el 50% (5,01) ya es Medio.
    // La clasificación se hace en el servidor; los clientes reciben esta configuración.
    'grade_min' => 0,
    'grade_max' => 10,
    // Informe final PAO: aprueba quien obtiene un promedio de los dos parciales igual o mayor.
    'passing_grade' => 7,
    'groups' => [
        [
            'key' => 'low', 'label' => 'Bajo', 'range_label' => '0% - 50%', 'min' => 0, 'max' => 5,
            'action_plan' => ['Semana de adaptación de Conocimientos', 'Tutorías adicionales obligatorias', 'Estudio Autónomo'],
        ],
        [
            'key' => 'medium', 'label' => 'Medio', 'range_label' => '51% - 69%', 'min' => 5.01, 'max' => 6.99,
            'action_plan' => ['Semana de adaptación de Conocimientos', 'Tutorías adicionales obligatorias'],
        ],
        [
            'key' => 'high', 'label' => 'Alto', 'range_label' => '70% - 100%', 'min' => 7, 'max' => 10,
            'action_plan' => ['Semana de adaptación de Conocimientos'],
        ],
    ],
];
