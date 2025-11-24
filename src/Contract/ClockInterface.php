<?php

declare(strict_types=1);

namespace Duyler\Workflow\Contract;

use DateInterval;
use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;

    public function isPast(DateTimeImmutable $time): bool;

    public function addInterval(DateInterval $interval): DateTimeImmutable;
}
