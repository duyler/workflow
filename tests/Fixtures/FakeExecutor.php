<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Fixtures;

use Duyler\Workflow\Contract\ExecutorInterface;
use UnitEnum;

final class FakeExecutor implements ExecutorInterface
{
    /** @var array<string, mixed> */
    private array $results = [];

    /** @var array<string, bool> */
    private array $completed = [];

    /** @var array<array{actionId: string|UnitEnum, argument: ?object}> */
    private array $scheduledActions = [];

    public function scheduleAction(string|UnitEnum $actionId, ?object $argument = null): void
    {
        $key = $this->getKey($actionId);
        $this->scheduledActions[] = ['actionId' => $actionId, 'argument' => $argument];
        $this->completed[$key] = false;
    }

    public function scheduleParallelActions(array $actionIds): void
    {
        foreach ($actionIds as $actionId) {
            $this->scheduleAction($actionId);
        }
    }

    public function scheduleDelayedAction(
        string|UnitEnum $actionId,
        int $delaySeconds,
        ?object $argument = null,
    ): void {
        $this->scheduleAction($actionId, $argument);
    }

    public function isActionCompleted(string|UnitEnum $actionId): bool
    {
        $key = $this->getKey($actionId);
        return $this->completed[$key] ?? false;
    }

    public function getActionResult(string|UnitEnum $actionId): mixed
    {
        $key = $this->getKey($actionId);
        return $this->results[$key] ?? null;
    }

    public function cancelAction(string|UnitEnum $actionId): bool
    {
        $key = $this->getKey($actionId);
        unset($this->results[$key], $this->completed[$key]);
        return true;
    }

    public function completeAction(string|UnitEnum $actionId, mixed $result): void
    {
        $key = $this->getKey($actionId);
        $this->results[$key] = $result;
        $this->completed[$key] = true;
    }

    /**
     * @return array<array{actionId: string|UnitEnum, argument: ?object}>
     */
    public function getScheduledActions(): array
    {
        return $this->scheduledActions;
    }

    public function clear(): void
    {
        $this->results = [];
        $this->completed = [];
        $this->scheduledActions = [];
    }

    private function getKey(string|UnitEnum $actionId): string
    {
        return is_string($actionId) ? $actionId : $actionId->value;
    }
}
