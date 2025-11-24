<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Fixtures;

use DateTimeImmutable;
use Duyler\Workflow\Contract\StorageInterface;
use Duyler\Workflow\State\WorkflowState;
use Duyler\Workflow\State\WorkflowStatus;
use RuntimeException;

final class InMemoryStorage implements StorageInterface
{
    /** @var array<string, WorkflowState> */
    private array $states = [];

    public function save(WorkflowState $state): void
    {
        $this->states[$state->instanceId] = $state;
    }

    public function load(string $instanceId): WorkflowState
    {
        if (!isset($this->states[$instanceId])) {
            throw new RuntimeException("Workflow instance '{$instanceId}' not found");
        }

        return $this->states[$instanceId];
    }

    public function delete(string $instanceId): void
    {
        unset($this->states[$instanceId]);
    }

    public function exists(string $instanceId): bool
    {
        return isset($this->states[$instanceId]);
    }

    public function findByStatus(WorkflowStatus $status): array
    {
        return array_values(
            array_filter(
                $this->states,
                fn(WorkflowState $state) => $state->status === $status,
            ),
        );
    }

    public function findScheduledBefore(DateTimeImmutable $time): array
    {
        return array_values(
            array_filter(
                $this->states,
                fn(WorkflowState $state) => $state->scheduledAt !== null && $state->scheduledAt < $time,
            ),
        );
    }

    public function clear(): void
    {
        $this->states = [];
    }

    /**
     * @return array<WorkflowState>
     */
    public function all(): array
    {
        return array_values($this->states);
    }
}
