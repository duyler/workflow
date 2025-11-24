<?php

declare(strict_types=1);

namespace Duyler\Workflow\Contract;

use DateTimeImmutable;
use Duyler\Workflow\State\WorkflowState;
use Duyler\Workflow\State\WorkflowStatus;

interface StorageInterface
{
    public function save(WorkflowState $state): void;

    public function load(string $instanceId): WorkflowState;

    public function delete(string $instanceId): void;

    public function exists(string $instanceId): bool;

    /**
     * @return array<WorkflowState>
     */
    public function findByStatus(WorkflowStatus $status): array;

    /**
     * @return array<WorkflowState>
     */
    public function findScheduledBefore(DateTimeImmutable $time): array;
}
