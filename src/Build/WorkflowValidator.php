<?php

declare(strict_types=1);

namespace Duyler\Workflow\Build;

use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;
use Duyler\Workflow\Expression\ExpressionEvaluator;

final class WorkflowValidator
{
    private ExpressionEvaluator $expressionEvaluator;

    public function __construct()
    {
        $this->expressionEvaluator = new ExpressionEvaluator();
    }

    public function validate(Workflow $workflow): void
    {
        $steps = $workflow->getSteps();

        if (empty($steps)) {
            throw new InvalidWorkflowDefinitionException(
                "Workflow '{$workflow->getId()}' must have at least one step",
            );
        }

        $stepIds = [];
        foreach ($steps as $step) {
            if (isset($stepIds[$step->getId()])) {
                throw new InvalidWorkflowDefinitionException(
                    "Duplicate step ID '{$step->getId()}' in workflow '{$workflow->getId()}'",
                );
            }
            $stepIds[$step->getId()] = true;

            if (empty($step->getActions()) && empty($step->getParallelActions())) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$step->getId()}' must have at least one action",
                );
            }

            if ($step->isIsFinal()) {
                if ($step->getSuccessStep() !== null || $step->getFailStep() !== null) {
                    throw new InvalidWorkflowDefinitionException(
                        "Final step '{$step->getId()}' cannot have transitions",
                    );
                }
            }

            foreach ($step->getConditions() as $condition) {
                if (!$this->expressionEvaluator->isValid($condition->expression)) {
                    throw new InvalidWorkflowDefinitionException(
                        "Invalid expression '{$condition->expression}' in step '{$step->getId()}'",
                    );
                }
            }
        }

        foreach ($steps as $step) {
            if ($step->getSuccessStep() !== null && !isset($stepIds[$step->getSuccessStep()])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$step->getId()}' references non-existent success step '{$step->getSuccessStep()}'",
                );
            }

            if ($step->getFailStep() !== null && !isset($stepIds[$step->getFailStep()])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$step->getId()}' references non-existent fail step '{$step->getFailStep()}'",
                );
            }

            foreach ($step->getConditions() as $condition) {
                if (!isset($stepIds[$condition->targetStepId])) {
                    throw new InvalidWorkflowDefinitionException(
                        "Condition in step '{$step->getId()}' references non-existent step '{$condition->targetStepId}'",
                    );
                }
            }
        }

        $hasFinalStep = false;
        foreach ($steps as $step) {
            if ($step->isIsFinal()) {
                $hasFinalStep = true;
                break;
            }
        }

        if (!$hasFinalStep) {
            throw new InvalidWorkflowDefinitionException(
                "Workflow '{$workflow->getId()}' must have at least one final step",
            );
        }
    }
}
