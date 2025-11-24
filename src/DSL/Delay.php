<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

final readonly class Delay
{
    public function __construct(
        public int $seconds,
    ) {}
}
