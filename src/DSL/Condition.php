<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

final readonly class Condition
{
    public function __construct(
        public string $expression,
        public string $targetStepId,
        public ?string $description = null,
    ) {}
}
