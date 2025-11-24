<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Integration;

use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\Build\WorkflowRegistry;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Runtime\WorkflowManager;
use Duyler\Workflow\State\WorkflowStatus;
use Duyler\Workflow\Test\Fixtures\FakeClock;
use Duyler\Workflow\Test\Fixtures\FakeExecutor;
use Duyler\Workflow\Test\Fixtures\InMemoryStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SimpleWorkflowTest extends TestCase
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
    public function it_executes_simple_two_step_workflow(): void
    {
        $workflow = Workflow::define('SimpleWorkflow')
            ->description('A simple two-step workflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('step2'),
                Step::withId('step2')
                    ->actions(['Action2'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('SimpleWorkflow');

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(1, $scheduled);
        $this->assertEquals('Action1', $scheduled[0]['actionId']);

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Running, $state->status);
        $this->assertEquals('step1', $state->currentStepId);

        $this->executor->completeAction('Action1', true);
        $this->manager->actionReceived('Action1');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('step2', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(2, $scheduled);
        $this->assertEquals('Action2', $scheduled[1]['actionId']);

        $this->executor->completeAction('Action2', true);
        $this->manager->actionReceived('Action2');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Completed, $state->status);
        $this->assertCount(2, $state->history);
    }

    #[Test]
    public function it_handles_failure_transition(): void
    {
        $workflow = Workflow::define('FailureWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('success_step')
                    ->onFail('error_step'),
                Step::withId('success_step')
                    ->actions(['SuccessAction'])
                    ->isFinal(),
                Step::withId('error_step')
                    ->actions(['ErrorAction'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('FailureWorkflow');

        $this->executor->completeAction('Action1', false);
        $this->manager->actionReceived('Action1');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals('error_step', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(2, $scheduled);
        $this->assertEquals('ErrorAction', $scheduled[1]['actionId']);
    }

    #[Test]
    public function it_handles_delayed_step(): void
    {
        $workflow = Workflow::define('DelayedWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('delayed_step'),
                Step::withId('delayed_step')
                    ->delay(30)
                    ->actions(['DelayedAction'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('DelayedWorkflow');

        $this->executor->completeAction('Action1', true);
        $this->manager->actionReceived('Action1');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Waiting, $state->status);
        $this->assertNotNull($state->scheduledAt);
        $this->assertEquals('delayed_step', $state->currentStepId);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(1, $scheduled);

        $this->manager->tick();
        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Waiting, $state->status);

        $this->clock->advanceSeconds(31);

        $this->manager->tick();
        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Running, $state->status);

        $scheduled = $this->executor->getScheduledActions();
        $this->assertCount(2, $scheduled);
        $this->assertEquals('DelayedAction', $scheduled[1]['actionId']);
    }

    #[Test]
    public function it_can_cancel_workflow(): void
    {
        $workflow = Workflow::define('CancellableWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->isFinal(),
            );

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);
        $this->registry->register($compiled);

        $instanceId = $this->manager->start('CancellableWorkflow');

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Running, $state->status);

        $this->manager->cancel($instanceId);

        $state = $this->manager->getState($instanceId);
        $this->assertEquals(WorkflowStatus::Cancelled, $state->status);
    }
}
