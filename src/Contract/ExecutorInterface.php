<?php

declare(strict_types=1);

namespace Duyler\Workflow\Contract;

use UnitEnum;

interface ExecutorInterface
{
    public function scheduleAction(
        string|UnitEnum $actionId,
        ?object $argument = null,
    ): void;

    /**
     * @param array<string|UnitEnum> $actionIds
     */
    public function scheduleParallelActions(array $actionIds): void;

    public function scheduleDelayedAction(
        string|UnitEnum $actionId,
        int $delaySeconds,
        ?object $argument = null,
    ): void;

    public function isActionCompleted(string|UnitEnum $actionId): bool;

    public function getActionResult(string|UnitEnum $actionId): mixed;

    public function cancelAction(string|UnitEnum $actionId): bool;
}
