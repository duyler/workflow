<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Fixtures;

use DateInterval;
use DateTimeImmutable;
use Duyler\Workflow\Contract\ClockInterface;

final class FakeClock implements ClockInterface
{
    private DateTimeImmutable $currentTime;

    public function __construct(?DateTimeImmutable $startTime = null)
    {
        $this->currentTime = $startTime ?? new DateTimeImmutable();
    }

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function isPast(DateTimeImmutable $time): bool
    {
        return $this->currentTime >= $time;
    }

    public function addInterval(DateInterval $interval): DateTimeImmutable
    {
        return $this->currentTime->add($interval);
    }

    public function setTime(DateTimeImmutable $time): void
    {
        $this->currentTime = $time;
    }

    public function advance(DateInterval $interval): void
    {
        $this->currentTime = $this->currentTime->add($interval);
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->currentTime = $this->currentTime->modify("+{$seconds} seconds");
    }
}
