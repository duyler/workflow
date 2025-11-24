<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

use UnitEnum;

final class Step
{
    /** @var array<string|UnitEnum> */
    private array $actions = [];

    /** @var array<string|UnitEnum> */
    private array $parallelActions = [];

    /** @var array<Condition> */
    private array $conditions = [];

    private ?string $successStep = null;

    private ?string $failStep = null;

    private ?Delay $delay = null;

    private ?Timeout $timeout = null;

    private ?Retry $retry = null;

    private bool $isFinal = false;

    private function __construct(
        private readonly string $id,
    ) {}

    public static function withId(string $id): self
    {
        return new self($id);
    }

    /**
     * @param array<string|UnitEnum> $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @param array<string|UnitEnum> $actions
     */
    public function parallel(array $actions): self
    {
        $this->parallelActions = $actions;
        return $this;
    }

    public function when(string $expression, string $targetStepId, ?string $description = null): self
    {
        $this->conditions[] = new Condition($expression, $targetStepId, $description);
        return $this;
    }

    public function onSuccess(string $stepId): self
    {
        $this->successStep = $stepId;
        return $this;
    }

    public function onFail(string $stepId): self
    {
        $this->failStep = $stepId;
        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = new Delay($seconds);
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = new Timeout($seconds);
        return $this;
    }

    public function retry(int $maxAttempts, int $delaySeconds = 0, RetryBackoff $backoff = RetryBackoff::Fixed): self
    {
        $this->retry = new Retry($maxAttempts, $delaySeconds, $backoff);
        return $this;
    }

    public function isFinal(): self
    {
        $this->isFinal = true;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string|UnitEnum>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return array<string|UnitEnum>
     */
    public function getParallelActions(): array
    {
        return $this->parallelActions;
    }

    /**
     * @return array<Condition>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getSuccessStep(): ?string
    {
        return $this->successStep;
    }

    public function getFailStep(): ?string
    {
        return $this->failStep;
    }

    public function getDelay(): ?Delay
    {
        return $this->delay;
    }

    public function getTimeout(): ?Timeout
    {
        return $this->timeout;
    }

    public function getRetry(): ?Retry
    {
        return $this->retry;
    }

    public function isIsFinal(): bool
    {
        return $this->isFinal;
    }
}
