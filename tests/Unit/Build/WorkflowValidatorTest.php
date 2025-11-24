<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Unit\Build;

use Duyler\Workflow\Build\WorkflowValidator;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowValidatorTest extends TestCase
{
    private WorkflowValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WorkflowValidator();
    }

    #[Test]
    public function it_validates_correct_workflow(): void
    {
        $workflow = Workflow::define('ValidWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('step2'),
                Step::withId('step2')
                    ->actions(['Action2'])
                    ->isFinal(),
            );

        $this->validator->validate($workflow);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_rejects_workflow_without_steps(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('must have at least one step');

        $workflow = Workflow::define('EmptyWorkflow')->sequence();

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_duplicate_step_ids(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('Duplicate step ID');

        $workflow = Workflow::define('DuplicateWorkflow')
            ->sequence(
                Step::withId('step1')->actions(['Action1']),
                Step::withId('step1')->actions(['Action2']),
            );

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_step_without_actions(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('must have at least one action');

        $workflow = Workflow::define('NoActionsWorkflow')
            ->sequence(
                Step::withId('step1')->isFinal(),
            );

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_final_step_with_transitions(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('Final step');

        $workflow = Workflow::define('FinalWithTransitionWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('step2')
                    ->isFinal(),
            );

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_invalid_step_reference(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('non-existent success step');

        $workflow = Workflow::define('InvalidRefWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('non_existent'),
            );

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_workflow_without_final_step(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('must have at least one final step');

        $workflow = Workflow::define('NoFinalWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('step2'),
                Step::withId('step2')
                    ->actions(['Action2'])
                    ->onSuccess('step1'),
            );

        $this->validator->validate($workflow);
    }

    #[Test]
    public function it_rejects_invalid_expression(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('Invalid expression');

        $workflow = Workflow::define('InvalidExpressionWorkflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->when('invalid syntax !@#', 'step2')
                    ->onSuccess('step3'),
                Step::withId('step2')->actions(['Action2'])->isFinal(),
                Step::withId('step3')->actions(['Action3'])->isFinal(),
            );

        $this->validator->validate($workflow);
    }
}
