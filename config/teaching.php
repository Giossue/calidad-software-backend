<?php

return [
    // Provisional scale: the planning document does not prescribe thresholds.
    // Keep classification on the server; clients obtain this same configuration.
    'grade_min' => 0,
    'grade_max' => 10,
    'groups' => [
        ['key' => 'low', 'label' => 'Bajo', 'min' => 0, 'max' => 3.99],
        ['key' => 'medium', 'label' => 'Medio', 'min' => 4, 'max' => 6.99],
        ['key' => 'high', 'label' => 'Alto', 'min' => 7, 'max' => 10],
    ],
];
