<?php

declare(strict_types=1);

namespace Duyler\Workflow\Integration;

use Duyler\Workflow\Build\CompiledWorkflow;
use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\Build\WorkflowValidator;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;

final class WorkflowLoader
{
    public function __construct(
        private readonly string $workflowsPath,
        private readonly WorkflowBuilder $builder,
        private readonly WorkflowValidator $validator,
    ) {}

    /**
     * @return array<CompiledWorkflow>
     */
    public function loadAll(): array
    {
        if (!is_dir($this->workflowsPath)) {
            throw new InvalidWorkflowDefinitionException(
                "Workflows directory '{$this->workflowsPath}' does not exist",
            );
        }

        $files = glob($this->workflowsPath . '/*.php');

        if ($files === false) {
            throw new InvalidWorkflowDefinitionException(
                "Failed to read workflows from '{$this->workflowsPath}'",
            );
        }

        $workflows = [];

        foreach ($files as $file) {
            $workflows[] = $this->loadOne($file);
        }

        return $workflows;
    }

    public function loadOne(string $filename): CompiledWorkflow
    {
        if (!file_exists($filename)) {
            throw new InvalidWorkflowDefinitionException(
                "Workflow file '{$filename}' does not exist",
            );
        }

        $workflow = require $filename;

        if (!$workflow instanceof Workflow) {
            throw new InvalidWorkflowDefinitionException(
                "File '{$filename}' must return Workflow instance",
            );
        }

        $this->validator->validate($workflow);

        return $this->builder->build($workflow);
    }
}
