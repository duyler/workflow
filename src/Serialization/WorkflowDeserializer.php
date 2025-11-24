<?php

declare(strict_types=1);

namespace Duyler\Workflow\Serialization;

use Duyler\Workflow\DSL\RetryBackoff;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;
use UnitEnum;

final class WorkflowDeserializer
{
    /**
     * @param array<string, mixed> $data
     */
    public function deserialize(array $data): Workflow
    {
        $this->validateStructure($data);

        assert(is_string($data['id']));
        $workflow = Workflow::define($data['id']);

        if (isset($data['description'])) {
            assert(is_string($data['description']));
            $workflow->description($data['description']);
        }

        assert(is_array($data['steps']));
        $steps = [];
        foreach ($data['steps'] as $stepData) {
            assert(is_array($stepData));
            /** @var array<string, mixed> $stepData */
            $steps[] = $this->deserializeStep($stepData);
        }

        $workflow->sequence(...$steps);

        return $workflow;
    }

    public function fromJson(string $json): Workflow
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));
        /** @var array<string, mixed> $data */

        return $this->deserialize($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeStep(array $data): Step
    {
        assert(is_string($data['id']));
        $step = Step::withId($data['id']);

        if (!empty($data['actions']) && is_array($data['actions'])) {
            /** @var array<string|UnitEnum> $actions */
            $actions = $data['actions'];
            $step->actions($actions);
        }

        if (!empty($data['parallel_actions']) && is_array($data['parallel_actions'])) {
            /** @var array<string|UnitEnum> $parallelActions */
            $parallelActions = $data['parallel_actions'];
            $step->parallel($parallelActions);
        }

        if (!empty($data['conditions']) && is_array($data['conditions'])) {
            foreach ($data['conditions'] as $condition) {
                assert(is_array($condition));
                assert(is_string($condition['expression']));
                assert(is_string($condition['target_step']));
                $step->when(
                    $condition['expression'],
                    $condition['target_step'],
                    isset($condition['description']) && is_string($condition['description']) ? $condition['description'] : null,
                );
            }
        }

        if (isset($data['transitions']) && is_array($data['transitions'])) {
            if (isset($data['transitions']['success']) && is_string($data['transitions']['success'])) {
                $step->onSuccess($data['transitions']['success']);
            }

            if (isset($data['transitions']['fail']) && is_string($data['transitions']['fail'])) {
                $step->onFail($data['transitions']['fail']);
            }
        }

        if (isset($data['delay']) && is_int($data['delay'])) {
            $step->delay($data['delay']);
        }

        if (isset($data['timeout']) && is_int($data['timeout'])) {
            $step->timeout($data['timeout']);
        }

        if (isset($data['retry']) && is_array($data['retry'])) {
            assert(is_string($data['retry']['backoff']));
            assert(is_int($data['retry']['max_attempts']));
            $backoff = RetryBackoff::from($data['retry']['backoff']);
            $step->retry(
                $data['retry']['max_attempts'],
                isset($data['retry']['delay_seconds']) && is_int($data['retry']['delay_seconds']) ? $data['retry']['delay_seconds'] : 0,
                $backoff,
            );
        }

        if (($data['is_final'] ?? false) === true) {
            $step->isFinal();
        }

        return $step;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateStructure(array $data): void
    {
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw new InvalidWorkflowDefinitionException('Workflow ID is required and must be a string');
        }

        if (!isset($data['steps']) || !is_array($data['steps'])) {
            throw new InvalidWorkflowDefinitionException('Steps array is required');
        }

        if (empty($data['steps'])) {
            throw new InvalidWorkflowDefinitionException('Workflow must have at least one step');
        }

        foreach ($data['steps'] as $step) {
            if (!is_array($step) || !isset($step['id']) || !is_string($step['id'])) {
                throw new InvalidWorkflowDefinitionException('Step ID is required and must be a string');
            }
        }
    }
}
