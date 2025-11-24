<?php

declare(strict_types=1);

namespace Duyler\Workflow\Serialization;

use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;

final class JsonSchemaValidator
{
    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): void
    {
        $this->validateRequiredFields($data);
        $this->validateId($data);
        $this->validateSteps($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateRequiredFields(array $data): void
    {
        if (!isset($data['id'])) {
            throw new InvalidWorkflowDefinitionException('Field "id" is required');
        }

        if (!isset($data['steps'])) {
            throw new InvalidWorkflowDefinitionException('Field "steps" is required');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateId(array $data): void
    {
        if (!is_string($data['id']) || empty($data['id'])) {
            throw new InvalidWorkflowDefinitionException('Field "id" must be a non-empty string');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateSteps(array $data): void
    {
        if (!is_array($data['steps'])) {
            throw new InvalidWorkflowDefinitionException('Field "steps" must be an array');
        }

        if (empty($data['steps'])) {
            throw new InvalidWorkflowDefinitionException('Workflow must have at least one step');
        }

        foreach ($data['steps'] as $index => $step) {
            $this->validateStep($step, (int) $index);
        }
    }

    /**
     * @param mixed $step
     */
    private function validateStep(mixed $step, int $index): void
    {
        if (!is_array($step)) {
            throw new InvalidWorkflowDefinitionException("Step at index {$index} must be an array");
        }

        if (!isset($step['id'])) {
            throw new InvalidWorkflowDefinitionException("Step at index {$index} must have an 'id' field");
        }

        if (!is_string($step['id']) || empty($step['id'])) {
            throw new InvalidWorkflowDefinitionException("Step 'id' at index {$index} must be a non-empty string");
        }

        if (isset($step['actions']) && !is_array($step['actions'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' actions must be an array");
        }

        if (isset($step['parallel_actions']) && !is_array($step['parallel_actions'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' parallel_actions must be an array");
        }

        if (isset($step['conditions']) && !is_array($step['conditions'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' conditions must be an array");
        }

        if (isset($step['delay']) && !is_int($step['delay'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' delay must be an integer");
        }

        if (isset($step['timeout']) && !is_int($step['timeout'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' timeout must be an integer");
        }

        if (isset($step['is_final']) && !is_bool($step['is_final'])) {
            throw new InvalidWorkflowDefinitionException("Step '{$step['id']}' is_final must be a boolean");
        }
    }
}
