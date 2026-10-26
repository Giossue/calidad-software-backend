<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Escala de conocimiento de la evaluación diagnóstica (config/teaching.php).
 *
 * @phpstan-type KnowledgeGroup array{key: string, label: string, range_label: string, min: float|int, max: float|int, action_plan: list<string>}
 */
class KnowledgeScale
{
    /** @return list<KnowledgeGroup> */
    public static function groups(): array
    {
        /** @var list<KnowledgeGroup> */
        return array_values(Config::array('teaching.groups'));
    }

    /** @return KnowledgeGroup|null */
    public static function classify(float $grade): ?array
    {
        return collect(self::groups())->first(fn (array $group) => $grade >= $group['min'] && $grade <= $group['max']);
    }
}
