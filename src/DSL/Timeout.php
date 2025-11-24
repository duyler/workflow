<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

final readonly class Timeout
{
    public function __construct(
        public int $seconds,
    ) {}
}
