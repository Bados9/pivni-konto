<?php

namespace App\Service;

class DrinkingDayService
{
    private const DAY_BOUNDARY_HOUR = 5;

    /**
     * Get the start of the current "drinking day".
     * A drinking day runs from 5:00 AM to 5:00 AM next day.
     *
     * Examples:
     * - At 2026-01-31 23:00 -> returns 2026-01-31 05:00
     * - At 2026-02-01 02:00 -> returns 2026-01-31 05:00 (still "yesterday's" drinking day)
     * - At 2026-02-01 06:00 -> returns 2026-02-01 05:00
     */
    public function getDrinkingDayStart(?\DateTimeImmutable $at = null): \DateTimeImmutable
    {
        $at ??= new \DateTimeImmutable();
        $hour = (int) $at->format('H');

        $dayStart = $at->setTime(self::DAY_BOUNDARY_HOUR, 0, 0);

        if ($hour < self::DAY_BOUNDARY_HOUR) {
            return $dayStart->modify('-1 day');
        }

        return $dayStart;
    }

    /**
     * Get the end of the current "drinking day" (start of next drinking day).
     */
    public function getDrinkingDayEnd(?\DateTimeImmutable $at = null): \DateTimeImmutable
    {
        return $this->getDrinkingDayStart($at)->modify('+1 day');
    }

    /**
     * Get the "drinking date" for a given timestamp.
     * Returns the date that this timestamp belongs to in drinking-day logic.
     */
    public function getDrinkingDate(\DateTimeImmutable $at): string
    {
        $hour = (int) $at->format('H');

        if ($hour < self::DAY_BOUNDARY_HOUR) {
            return $at->modify('-1 day')->format('Y-m-d');
        }

        return $at->format('Y-m-d');
    }
}
