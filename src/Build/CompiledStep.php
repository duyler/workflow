<?php

declare(strict_types=1);

namespace Duyler\Workflow\Build;

use Duyler\Workflow\DSL\Condition;
use Duyler\Workflow\DSL\Delay;
use Duyler\Workflow\DSL\Retry;
use Duyler\Workflow\DSL\Timeout;
use UnitEnum;

final readonly class CompiledStep
{
    /**
     * @param array<string|UnitEnum> $actions
     * @param array<string|UnitEnum> $parallelActions
     * @param array<Condition> $conditions
     */
    public function __construct(
        public string $id,
        public array $actions,
        public array $parallelActions,
        public array $conditions,
        public ?string $successStep,
        public ?string $failStep,
        public ?Delay $delay,
        public ?Timeout $timeout,
        public ?Retry $retry,
        public bool $isFinal,
    ) {}

    public function hasParallelActions(): bool
    {
        return !empty($this->parallelActions);
    }

    /**
     * @return array<string|UnitEnum>
     */
    public function getAllActions(): array
    {
        return array_merge($this->actions, $this->parallelActions);
    }
}
