<?php

declare(strict_types=1);

namespace Duyler\Workflow\Build;

use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;

final class WorkflowBuilder
{
    public function build(Workflow $workflow): CompiledWorkflow
    {
        $steps = $workflow->getSteps();

        if (empty($steps)) {
            throw new InvalidWorkflowDefinitionException(
                "Workflow '{$workflow->getId()}' must have at least one step",
            );
        }

        $compiledSteps = [];
        foreach ($steps as $step) {
            $compiledSteps[$step->getId()] = new CompiledStep(
                id: $step->getId(),
                actions: $step->getActions(),
                parallelActions: $step->getParallelActions(),
                conditions: $step->getConditions(),
                successStep: $step->getSuccessStep(),
                failStep: $step->getFailStep(),
                delay: $step->getDelay(),
                timeout: $step->getTimeout(),
                retry: $step->getRetry(),
                isFinal: $step->isIsFinal(),
            );
        }

        $firstStep = $steps[0];

        return new CompiledWorkflow(
            id: $workflow->getId(),
            description: $workflow->getDescription(),
            steps: $compiledSteps,
            firstStepId: $firstStep->getId(),
        );
    }
}
