<?php

declare(strict_types=1);

namespace Duyler\Workflow\Runtime;

use DateInterval;
use Duyler\Workflow\Build\CompiledStep;
use Duyler\Workflow\Build\WorkflowRegistry;
use Duyler\Workflow\Contract\ClockInterface;
use Duyler\Workflow\Contract\ExecutorInterface;
use Duyler\Workflow\Contract\StorageInterface;
use Duyler\Workflow\Exception\InvalidStepTransitionException;
use Duyler\Workflow\Expression\ExpressionEvaluator;
use Duyler\Workflow\State\WorkflowState;
use Duyler\Workflow\State\WorkflowStatus;
use Throwable;
use UnitEnum;

final class WorkflowManager
{
    private ExpressionEvaluator $expressionEvaluator;

    public function __construct(
        private readonly ExecutorInterface $executor,
        private readonly StorageInterface $storage,
        private readonly ClockInterface $clock,
        private readonly WorkflowRegistry $registry,
    ) {
        $this->expressionEvaluator = new ExpressionEvaluator();
    }

    public function tick(): void
    {
        $activeWorkflows = $this->storage->findByStatus(WorkflowStatus::Running);
        $waitingWorkflows = $this->storage->findByStatus(WorkflowStatus::Waiting);

        foreach ($waitingWorkflows as $state) {
            if ($state->scheduledAt !== null && $this->clock->isPast($state->scheduledAt)) {
                $updatedState = $state->withStatus(WorkflowStatus::Running);
                $this->storage->save($updatedState);
                $this->continueWorkflow($updatedState);
            }
        }

        foreach ($activeWorkflows as $state) {
            $this->checkWorkflowProgress($state);
        }
    }

    public function actionReceived(string|UnitEnum $actionId): void
    {
        $affectedWorkflows = $this->findWorkflowsWaitingForAction($actionId);

        if (empty($affectedWorkflows)) {
            return;
        }

        foreach ($affectedWorkflows as $state) {
            $result = $this->executor->getActionResult($actionId);
            $this->processActionResult($state, $actionId, $result);
        }
    }

    private function areAllParallelActionsCompleted(WorkflowState $state, CompiledStep $step): bool
    {
        if (!$step->hasParallelActions()) {
            return true;
        }

        $completed = $state->getCompletedActions();

        foreach ($step->parallelActions as $actionId) {
            $key = is_string($actionId) ? $actionId : $actionId->name;
            if (!in_array($key, $completed, true)) {
                return false;
            }
        }

        return true;
    }

    public function start(string $workflowId, ?object $argument = null): string
    {
        $workflow = $this->registry->get($workflowId);
        $instanceId = $this->generateInstanceId();

        $state = new WorkflowState(
            instanceId: $instanceId,
            workflowId: $workflowId,
            currentStepId: $workflow->firstStepId,
            status: WorkflowStatus::Running,
            context: [],
            scheduledAt: null,
            history: [],
            createdAt: $this->clock->now(),
            updatedAt: $this->clock->now(),
        );

        $this->storage->save($state);

        $firstStep = $workflow->getStep($workflow->firstStepId);
        if ($firstStep === null) {
            throw new InvalidStepTransitionException("First step not found in workflow '{$workflowId}'");
        }

        if ($firstStep->hasParallelActions()) {
            $this->executor->scheduleParallelActions($firstStep->parallelActions);
        }

        foreach ($firstStep->actions as $actionId) {
            $this->executor->scheduleAction($actionId, $argument);
        }

        return $instanceId;
    }

    public function cancel(string $instanceId): void
    {
        if (!$this->storage->exists($instanceId)) {
            return;
        }

        $state = $this->storage->load($instanceId);
        $updatedState = $state->withStatus(WorkflowStatus::Cancelled);
        $this->storage->save($updatedState);
    }

    public function getState(string $instanceId): WorkflowState
    {
        return $this->storage->load($instanceId);
    }

    private function continueWorkflow(WorkflowState $state): void
    {
        $workflow = $this->registry->get($state->workflowId);
        $step = $workflow->getStep($state->currentStepId);

        if ($step === null) {
            return;
        }

        if ($step->hasParallelActions()) {
            $this->executor->scheduleParallelActions($step->parallelActions);
        }

        foreach ($step->actions as $actionId) {
            $this->executor->scheduleAction($actionId);
        }

        $this->storage->save($state);
    }

    /**
     * @return array<WorkflowState>
     */
    private function findWorkflowsWaitingForAction(string|UnitEnum $actionId): array
    {
        $running = $this->storage->findByStatus(WorkflowStatus::Running);
        $result = [];

        foreach ($running as $state) {
            $workflow = $this->registry->get($state->workflowId);
            $step = $workflow->getStep($state->currentStepId);

            if ($step === null) {
                continue;
            }

            if (in_array($actionId, $step->actions, true)
                || in_array($actionId, $step->parallelActions, true)) {
                $result[] = $state;
            }
        }

        return $result;
    }

    private function processActionResult(WorkflowState $state, string|UnitEnum $actionId, mixed $result): void
    {
        $workflow = $this->registry->get($state->workflowId);
        $currentStep = $workflow->getStep($state->currentStepId);

        if ($currentStep === null) {
            return;
        }

        $actionIdString = is_string($actionId) ? $actionId : $actionId->name;

        $updatedState = $state->addHistoryEntry(
            $state->currentStepId,
            $actionIdString,
            $result,
        );

        if ($currentStep->hasParallelActions()) {
            $updatedState = $updatedState->markActionCompleted($actionIdString);

            if (!$this->areAllParallelActionsCompleted($updatedState, $currentStep)) {
                $this->storage->save($updatedState);
                return;
            }

            $updatedState = $updatedState->clearCompletedActions();
        }

        if ($currentStep->isFinal) {
            $finalState = $updatedState->withStatus(WorkflowStatus::Completed);
            $this->storage->save($finalState);
            return;
        }

        $isSuccess = $this->isSuccessResult($result);

        if (!$isSuccess && $currentStep->retry !== null) {
            $attempt = $updatedState->getRetryAttempt($currentStep->id);

            if ($attempt < $currentStep->retry->maxAttempts) {
                $retryState = $updatedState->incrementRetryAttempt($currentStep->id);
                $delay = $currentStep->retry->calculateDelay($attempt + 1);

                if ($delay > 0) {
                    $scheduledAt = $this->clock->addInterval(new DateInterval("PT{$delay}S"));
                    $delayedState = $retryState->withSchedule($scheduledAt);
                    $this->storage->save($delayedState);
                    return;
                }

                $this->storage->save($retryState);

                foreach ($currentStep->actions as $retryActionId) {
                    $this->executor->scheduleAction($retryActionId);
                }

                return;
            }
        }

        $updatedState = $updatedState->resetRetryAttempt($currentStep->id);

        $nextStepId = null;

        if ($isSuccess && !empty($currentStep->conditions)) {
            $nextStepId = $this->evaluateConditions($currentStep, $result, $updatedState->context);
        }

        if ($nextStepId === null) {
            $nextStepId = $isSuccess ? $currentStep->successStep : $currentStep->failStep;
        }

        if ($nextStepId === null) {
            $finalState = $updatedState->withStatus(
                $isSuccess ? WorkflowStatus::Completed : WorkflowStatus::Failed,
            );
            $this->storage->save($finalState);
            return;
        }

        $nextStep = $workflow->getStep($nextStepId);
        if ($nextStep === null) {
            throw new InvalidStepTransitionException("Next step '{$nextStepId}' not found");
        }

        $transitionedState = $updatedState->withNextStep($nextStepId);

        if ($nextStep->delay !== null) {
            $scheduledAt = $this->clock->addInterval(
                new DateInterval("PT{$nextStep->delay->seconds}S"),
            );
            $delayedState = $transitionedState->withSchedule($scheduledAt);
            $this->storage->save($delayedState);
            return;
        }

        $this->storage->save($transitionedState);

        foreach ($nextStep->actions as $nextActionId) {
            $this->executor->scheduleAction($nextActionId);
        }
    }

    private function isSuccessResult(mixed $result): bool
    {
        if (is_bool($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'isSuccess')) {
            return (bool) call_user_func([$result, 'isSuccess']);
        }

        return true;
    }

    private function checkWorkflowProgress(WorkflowState $state): void
    {
        $workflow = $this->registry->get($state->workflowId);
        $step = $workflow->getStep($state->currentStepId);

        if ($step === null || $step->timeout === null) {
            return;
        }

        $elapsed = $this->clock->now()->getTimestamp() - $state->updatedAt->getTimestamp();

        if ($elapsed > $step->timeout->seconds) {
            $this->handleTimeout($state, $step);
        }
    }

    private function handleTimeout(WorkflowState $state, CompiledStep $step): void
    {
        if ($step->failStep !== null) {
            $workflow = $this->registry->get($state->workflowId);
            $nextStep = $workflow->getStep($step->failStep);

            if ($nextStep !== null) {
                $transitionedState = $state->withNextStep($step->failStep);
                $this->storage->save($transitionedState);

                foreach ($nextStep->actions as $actionId) {
                    $this->executor->scheduleAction($actionId);
                }
                return;
            }
        }

        $failedState = $state->withStatus(WorkflowStatus::Failed);
        $this->storage->save($failedState);
    }

    private function generateInstanceId(): string
    {
        return uniqid('wf_', true);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function evaluateConditions(CompiledStep $step, mixed $result, array $context): ?string
    {
        foreach ($step->conditions as $condition) {
            $variables = [
                'result' => $result,
                'context' => $context,
            ];

            try {
                $evaluated = $this->expressionEvaluator->evaluate($condition->expression, $variables);

                if ($evaluated === true) {
                    return $condition->targetStepId;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
