<?php

namespace App\Tests\Unit\Service;

use App\Service\DrinkingDayService;
use PHPUnit\Framework\TestCase;

class DrinkingDayServiceTest extends TestCase
{
    private DrinkingDayService $service;

    protected function setUp(): void
    {
        $this->service = new DrinkingDayService();
    }

    public function testGetDrinkingDayStartAfterBoundary(): void
    {
        // 23:00 on Jan 31 -> drinking day started at 05:00 on Jan 31
        $at = new \DateTimeImmutable('2026-01-31 23:00:00');
        $result = $this->service->getDrinkingDayStart($at);

        $this->assertEquals('2026-01-31 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDayStartBeforeBoundary(): void
    {
        // 02:00 on Feb 1 -> still "yesterday's" drinking day (Jan 31 05:00)
        $at = new \DateTimeImmutable('2026-02-01 02:00:00');
        $result = $this->service->getDrinkingDayStart($at);

        $this->assertEquals('2026-01-31 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDayStartExactlyAtBoundary(): void
    {
        // 05:00 on Feb 1 -> new drinking day starts
        $at = new \DateTimeImmutable('2026-02-01 05:00:00');
        $result = $this->service->getDrinkingDayStart($at);

        $this->assertEquals('2026-02-01 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDayStartJustAfterBoundary(): void
    {
        // 06:00 on Feb 1 -> drinking day started at 05:00 on Feb 1
        $at = new \DateTimeImmutable('2026-02-01 06:00:00');
        $result = $this->service->getDrinkingDayStart($at);

        $this->assertEquals('2026-02-01 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDayEndAfterBoundary(): void
    {
        $at = new \DateTimeImmutable('2026-01-31 23:00:00');
        $result = $this->service->getDrinkingDayEnd($at);

        $this->assertEquals('2026-02-01 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDayEndBeforeBoundary(): void
    {
        // 02:00 on Feb 1 -> drinking day ends at Feb 1 05:00
        $at = new \DateTimeImmutable('2026-02-01 02:00:00');
        $result = $this->service->getDrinkingDayEnd($at);

        $this->assertEquals('2026-02-01 05:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetDrinkingDateAfterBoundary(): void
    {
        // 23:00 on Jan 31 -> belongs to Jan 31
        $at = new \DateTimeImmutable('2026-01-31 23:00:00');
        $result = $this->service->getDrinkingDate($at);

        $this->assertEquals('2026-01-31', $result);
    }

    public function testGetDrinkingDateBeforeBoundary(): void
    {
        // 02:00 on Feb 1 -> belongs to Jan 31 (still yesterday's drinking day)
        $at = new \DateTimeImmutable('2026-02-01 02:00:00');
        $result = $this->service->getDrinkingDate($at);

        $this->assertEquals('2026-01-31', $result);
    }

    public function testGetDrinkingDateExactlyAtBoundary(): void
    {
        // 05:00 on Feb 1 -> belongs to Feb 1
        $at = new \DateTimeImmutable('2026-02-01 05:00:00');
        $result = $this->service->getDrinkingDate($at);

        $this->assertEquals('2026-02-01', $result);
    }

    public function testGetDrinkingDateAt4AM(): void
    {
        // 04:00 on Feb 1 -> still belongs to Jan 31
        $at = new \DateTimeImmutable('2026-02-01 04:00:00');
        $result = $this->service->getDrinkingDate($at);

        $this->assertEquals('2026-01-31', $result);
    }

    public function testGetDrinkingDateAtMidnight(): void
    {
        // 00:00 on Feb 1 -> still belongs to Jan 31
        $at = new \DateTimeImmutable('2026-02-01 00:00:00');
        $result = $this->service->getDrinkingDate($at);

        $this->assertEquals('2026-01-31', $result);
    }
}
