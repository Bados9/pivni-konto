<?php

declare(strict_types=1);

namespace App\Service;

final class ScoreFormatter
{
    /**
     * Czech format, half-beers keep one decimal: 5 -> "5", 4.5 -> "4,5".
     */
    public static function score(float $score): string
    {
        $formatted = number_format($score, 1, ',', ' ');

        return str_ends_with($formatted, ',0') ? substr($formatted, 0, -2) : $formatted;
    }

    public static function litres(int $volumeMl): string
    {
        return self::score(round($volumeMl / 1000, 1));
    }
}
