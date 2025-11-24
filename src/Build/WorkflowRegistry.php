<?php

declare(strict_types=1);

namespace Duyler\Workflow\Build;

use Duyler\Workflow\Exception\WorkflowNotFoundException;

final class WorkflowRegistry
{
    /** @var array<string, CompiledWorkflow> */
    private array $workflows = [];

    public function register(CompiledWorkflow $workflow): void
    {
        $this->workflows[$workflow->id] = $workflow;
    }

    public function get(string $workflowId): CompiledWorkflow
    {
        if (!isset($this->workflows[$workflowId])) {
            throw new WorkflowNotFoundException("Workflow '{$workflowId}' not found");
        }

        return $this->workflows[$workflowId];
    }

    public function has(string $workflowId): bool
    {
        return isset($this->workflows[$workflowId]);
    }

    /**
     * @return array<CompiledWorkflow>
     */
    public function all(): array
    {
        return array_values($this->workflows);
    }
}
