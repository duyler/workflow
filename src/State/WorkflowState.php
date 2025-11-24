<?php

declare(strict_types=1);

namespace Duyler\Workflow\State;

use DateTimeImmutable;

final readonly class WorkflowState
{
    /**
     * @param array<string, mixed> $context
     * @param array<array{stepId: string, actionId: string, result: mixed, timestamp: DateTimeImmutable}> $history
     */
    public function __construct(
        public string $instanceId,
        public string $workflowId,
        public string $currentStepId,
        public WorkflowStatus $status,
        public array $context,
        public ?DateTimeImmutable $scheduledAt,
        public array $history,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instanceId' => $this->instanceId,
            'workflowId' => $this->workflowId,
            'currentStepId' => $this->currentStepId,
            'status' => $this->status->value,
            'context' => $this->context,
            'scheduledAt' => $this->scheduledAt?->format(DateTimeImmutable::ATOM),
            'history' => array_map(
                fn(array $entry) => [
                    'stepId' => $entry['stepId'],
                    'actionId' => $entry['actionId'],
                    'result' => $entry['result'],
                    'timestamp' => $entry['timestamp']->format(DateTimeImmutable::ATOM),
                ],
                $this->history,
            ),
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        assert(is_string($data['instanceId']));
        assert(is_string($data['workflowId']));
        assert(is_string($data['currentStepId']));
        assert(is_string($data['status']));
        assert(is_array($data['context']));
        assert(is_array($data['history']));
        assert(is_string($data['createdAt']));
        assert(is_string($data['updatedAt']));

        /** @var array<string, mixed> $context */
        $context = $data['context'];

        return new self(
            instanceId: $data['instanceId'],
            workflowId: $data['workflowId'],
            currentStepId: $data['currentStepId'],
            status: WorkflowStatus::from($data['status']),
            context: $context,
            scheduledAt: isset($data['scheduledAt']) && is_string($data['scheduledAt'])
                ? new DateTimeImmutable($data['scheduledAt'])
                : null,
            history: array_map(
                function (mixed $entry): array {
                    assert(is_array($entry));
                    assert(is_string($entry['stepId']));
                    assert(is_string($entry['actionId']));
                    assert(is_string($entry['timestamp']));

                    return [
                        'stepId' => $entry['stepId'],
                        'actionId' => $entry['actionId'],
                        'result' => $entry['result'],
                        'timestamp' => new DateTimeImmutable($entry['timestamp']),
                    ];
                },
                $data['history'],
            ),
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt']),
        );
    }

    public function withNextStep(string $stepId): self
    {
        return new self(
            instanceId: $this->instanceId,
            workflowId: $this->workflowId,
            currentStepId: $stepId,
            status: $this->status,
            context: $this->context,
            scheduledAt: $this->scheduledAt,
            history: $this->history,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withStatus(WorkflowStatus $status): self
    {
        return new self(
            instanceId: $this->instanceId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            status: $status,
            context: $this->context,
            scheduledAt: $this->scheduledAt,
            history: $this->history,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): self
    {
        return new self(
            instanceId: $this->instanceId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            status: $this->status,
            context: $context,
            scheduledAt: $this->scheduledAt,
            history: $this->history,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withSchedule(DateTimeImmutable $scheduledAt): self
    {
        return new self(
            instanceId: $this->instanceId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            status: WorkflowStatus::Waiting,
            context: $this->context,
            scheduledAt: $scheduledAt,
            history: $this->history,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function addHistoryEntry(string $stepId, string $actionId, mixed $result): self
    {
        $history = $this->history;
        $history[] = [
            'stepId' => $stepId,
            'actionId' => $actionId,
            'result' => $result,
            'timestamp' => new DateTimeImmutable(),
        ];

        return new self(
            instanceId: $this->instanceId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            status: $this->status,
            context: $this->context,
            scheduledAt: $this->scheduledAt,
            history: $history,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function isDelayed(): bool
    {
        return $this->scheduledAt !== null && $this->status === WorkflowStatus::Waiting;
    }

    public function getRetryAttempt(string $stepId): int
    {
        $value = $this->context["_retry_{$stepId}"] ?? 0;

        return is_int($value) ? $value : 0;
    }

    public function incrementRetryAttempt(string $stepId): self
    {
        $context = $this->context;
        $context["_retry_{$stepId}"] = $this->getRetryAttempt($stepId) + 1;

        return $this->withContext($context);
    }

    public function resetRetryAttempt(string $stepId): self
    {
        $context = $this->context;
        unset($context["_retry_{$stepId}"]);

        return $this->withContext($context);
    }

    /**
     * @return array<string>
     */
    public function getCompletedActions(): array
    {
        /** @var array<string> $completed */
        $completed = $this->context['_completed_actions'] ?? [];

        return $completed;
    }

    public function markActionCompleted(string $actionId): self
    {
        $completed = $this->getCompletedActions();
        $completed[] = $actionId;

        $context = $this->context;
        $context['_completed_actions'] = $completed;

        return $this->withContext($context);
    }

    public function clearCompletedActions(): self
    {
        $context = $this->context;
        unset($context['_completed_actions']);

        return $this->withContext($context);
    }
}
