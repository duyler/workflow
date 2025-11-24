<?php

declare(strict_types=1);

namespace Duyler\Workflow\Serialization;

use Duyler\Workflow\Build\CompiledWorkflow;
use RuntimeException;
use UnitEnum;

final class WorkflowSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(CompiledWorkflow $workflow): array
    {
        $steps = [];

        foreach ($workflow->getAllSteps() as $step) {
            $steps[] = [
                'id' => $step->id,
                'actions' => $this->serializeActions($step->actions),
                'parallel_actions' => $this->serializeActions($step->parallelActions),
                'conditions' => array_map(
                    fn($condition) => [
                        'expression' => $condition->expression,
                        'target_step' => $condition->targetStepId,
                        'description' => $condition->description,
                    ],
                    $step->conditions,
                ),
                'transitions' => [
                    'success' => $step->successStep,
                    'fail' => $step->failStep,
                ],
                'delay' => $step->delay?->seconds,
                'timeout' => $step->timeout?->seconds,
                'retry' => $step->retry ? [
                    'max_attempts' => $step->retry->maxAttempts,
                    'delay_seconds' => $step->retry->delaySeconds,
                    'backoff' => $step->retry->backoff->value,
                ] : null,
                'is_final' => $step->isFinal,
            ];
        }

        return [
            'id' => $workflow->id,
            'description' => $workflow->description,
            'first_step' => $workflow->firstStepId,
            'steps' => $steps,
        ];
    }

    public function toJson(CompiledWorkflow $workflow, int $flags = JSON_PRETTY_PRINT): string
    {
        $json = json_encode($this->serialize($workflow), $flags);

        if ($json === false) {
            throw new RuntimeException('Failed to encode workflow to JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * @param array<string|UnitEnum> $actions
     * @return array<string>
     */
    private function serializeActions(array $actions): array
    {
        return array_map(
            fn($action) => is_string($action) ? $action : $action->name,
            $actions,
        );
    }
}
