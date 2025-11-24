<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Integration;

use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\Build\WorkflowRegistry;
use Duyler\Workflow\DSL\RetryBackoff;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Runtime\WorkflowManager;
use Duyler\Workflow\State\WorkflowStatus;
use Duyler\Workflow\Test\Fixtures\FakeClock;
use Duyler\Workflow\Test\Fixtures\FakeExecutor;
use Duyler\Workflow\Test\Fixtures\InMemoryStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtendedFeaturesTest extends TestCase
{
    private WorkflowManager $manager;
    private FakeExecutor $executor;
    private InMemoryStorage $storage;
    private FakeClock $clock;
    private WorkflowRegistry $registry;

    protected function setUp(): void
    {
        $this->executor = new FakeExecutor();
        $this->storage = new InMemoryStorage();
        $this->clock = new FakeClock();
        $this->registry = new WorkflowRegistry();

        $this->manager = new WorkflowManager(
            executor: $this->executor,
            storage: $this->storage,
            clock: $this->clock,
            registry: $this->registry,
        );
    }

    #[Test]
    public function it_handles_timeout(): void
    {
        $workflow = Workflow::define('TimeoutWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['SlowAction'])
                    ->timeout(10)
                    ->onSuccess('success')
                    ->onFail('timeout_handler'),
                Step::withId('success')
                    ->actions(['SuccessAction'])
                    ->isFinal(),
                Step::withId('timeout_handler')
                    ->actions(['TimeoutAction'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('TimeoutWorkflow');

        $this->clock->advanceSeconds(11);

        $this->manager->tick();

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('timeout_handler', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertEquals('TimeoutAction', $scheduled[1]['actionId']);
    }

    #[Test]
    public function it_retries_with_exponential_backoff(): void
    {
        $workflow = Workflow::define('RetryWorkflow')
            ->sequence(
                Step::withId('unstable')
                    ->actions(['UnstableAction'])
                    ->retry(3, 1, RetryBackoff::Exponential)
                    ->onSuccess('success')
                    ->onFail('failed'),
                Step::withId('success')
                    ->actions(['SuccessAction'])
                    ->isFinal(),
                Step::withId('failed')
                    ->actions(['FailedAction'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('RetryWorkflow');

        $this->executor->completeAction('UnstableAction', false);
        $this->manager->actionReceived('UnstableAction');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Waiting, $state->status);
        $this->assertEquals(1, $state->getRetryAttempt('unstable'));

        $this->clock->advanceSeconds(2);
        $this->manager->tick();

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(2, $scheduled);
        $this->assertEquals('UnstableAction', $scheduled[1]['actionId']);
    }

    #[Test]
    public function it_executes_parallel_actions(): void
    {
        $workflow = Workflow::define('ParallelWorkflow')
            ->sequence(
                Step::withId('parallel_step')
                    ->parallel(['Action1', 'Action2', 'Action3'])
                    ->onSuccess('final'),
                Step::withId('final')
                    ->actions(['FinalAction'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('ParallelWorkflow');

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(3, $scheduled);

        $this->executor->completeAction('Action1', true);
        $this->manager->actionReceived('Action1');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('parallel_step', $state->currentStepId);
        $this->assertContains('Action1', $state->getCompletedActions());

        $this->executor->completeAction('Action2', true);
        $this->manager->actionReceived('Action2');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('parallel_step', $state->currentStepId);

        $this->executor->completeAction('Action3', true);
        $this->manager->actionReceived('Action3');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('final', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(4, $scheduled);
        $this->assertEquals('FinalAction', $scheduled[3]['actionId']);
    }

    #[Test]
    public function it_evaluates_conditional_transitions(): void
    {
        $workflow = Workflow::define('ConditionalWorkflow')
            ->sequence(
                Step::withId('check')
                    ->actions(['CheckAmount'])
                    ->when('result > 1000', 'manual_review')
                    ->when('result > 500', 'supervisor_review')
                    ->onSuccess('auto_approve')
                    ->onFail('reject'),
                Step::withId('manual_review')
                    ->actions(['ManualReview'])
                    ->isFinal(),
                Step::withId('supervisor_review')
                    ->actions(['SupervisorReview'])
                    ->isFinal(),
                Step::withId('auto_approve')
                    ->actions(['AutoApprove'])
                    ->isFinal(),
                Step::withId('reject')
                    ->actions(['Reject'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('ConditionalWorkflow');

        $this->executor->completeAction('CheckAmount', 1500);
        $this->manager->actionReceived('CheckAmount');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('manual_review', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertEquals('ManualReview', $scheduled[1]['actionId']);
    }
}
