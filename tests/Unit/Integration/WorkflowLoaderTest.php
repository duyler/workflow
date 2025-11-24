<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Unit\Integration;

use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\Build\WorkflowValidator;
use Duyler\Workflow\Exception\InvalidWorkflowDefinitionException;
use Duyler\Workflow\Integration\WorkflowLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/workflow_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function it_loads_workflow_from_file(): void
    {
        $workflowFile = $this->tempDir . '/test_workflow.php';
        file_put_contents(
            $workflowFile,
            <<<'PHP'
<?php
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\DSL\Step;

return Workflow::define('TestWorkflow')
    ->description('Test workflow')
    ->sequence(
        Step::withId('step1')
            ->actions(['Action1'])
            ->isFinal()
    );
PHP,
        );

        $loader = new WorkflowLoader(
            $this->tempDir,
            new WorkflowBuilder(),
            new WorkflowValidator(),
        );

        $workflow = $loader->loadOne($workflowFile);

        $this->assertEquals('TestWorkflow', $workflow->id);
        $this->assertEquals('Test workflow', $workflow->description);
        $this->assertCount(1, $workflow->getAllSteps());
    }

    #[Test]
    public function it_loads_all_workflows_from_directory(): void
    {
        file_put_contents(
            $this->tempDir . '/workflow1.php',
            <<<'PHP'
<?php
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\DSL\Step;

return Workflow::define('Workflow1')
    ->sequence(
        Step::withId('step1')->actions(['Action1'])->isFinal()
    );
PHP,
        );

        file_put_contents(
            $this->tempDir . '/workflow2.php',
            <<<'PHP'
<?php
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\DSL\Step;

return Workflow::define('Workflow2')
    ->sequence(
        Step::withId('step1')->actions(['Action1'])->isFinal()
    );
PHP,
        );

        $loader = new WorkflowLoader(
            $this->tempDir,
            new WorkflowBuilder(),
            new WorkflowValidator(),
        );

        $workflows = $loader->loadAll();

        $this->assertCount(2, $workflows);
        $this->assertEquals('Workflow1', $workflows[0]->id);
        $this->assertEquals('Workflow2', $workflows[1]->id);
    }

    #[Test]
    public function it_rejects_non_workflow_file(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('must return Workflow instance');

        $badFile = $this->tempDir . '/bad.php';
        file_put_contents($badFile, '<?php return "not a workflow";');

        $loader = new WorkflowLoader(
            $this->tempDir,
            new WorkflowBuilder(),
            new WorkflowValidator(),
        );

        $loader->loadOne($badFile);
    }

    #[Test]
    public function it_rejects_nonexistent_file(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('does not exist');

        $loader = new WorkflowLoader(
            $this->tempDir,
            new WorkflowBuilder(),
            new WorkflowValidator(),
        );

        $loader->loadOne($this->tempDir . '/nonexistent.php');
    }

    #[Test]
    public function it_rejects_nonexistent_directory(): void
    {
        $this->expectException(InvalidWorkflowDefinitionException::class);
        $this->expectExceptionMessage('does not exist');

        $loader = new WorkflowLoader(
            '/nonexistent/directory',
            new WorkflowBuilder(),
            new WorkflowValidator(),
        );

        $loader->loadAll();
    }
}
