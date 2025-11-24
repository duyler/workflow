<?php

declare(strict_types=1);

namespace Duyler\Workflow\Build;

final readonly class CompiledWorkflow
{
    /**
     * @param array<string, CompiledStep> $steps
     */
    public function __construct(
        public string $id,
        public ?string $description,
        public array $steps,
        public string $firstStepId,
    ) {}

    public function getStep(string $stepId): ?CompiledStep
    {
        return $this->steps[$stepId] ?? null;
    }

    /**
     * @return array<CompiledStep>
     */
    public function getAllSteps(): array
    {
        return array_values($this->steps);
    }
}
