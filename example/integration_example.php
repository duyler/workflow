<?php

declare(strict_types=1);

use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\Build\WorkflowRegistry;
use Duyler\Workflow\Build\WorkflowValidator;
use Duyler\Workflow\Expression\ExpressionEvaluator;
use Duyler\Workflow\Integration\WorkflowLoader;
use Duyler\Workflow\Runtime\WorkflowManager;

require_once __DIR__ . '/../vendor/autoload.php';

class FrameworkIntegrationExample
{
    private WorkflowManager $workflowManager;
    private WorkflowRegistry $registry;

    public function bootstrap(): void
    {
        $expressionEvaluator = new ExpressionEvaluator();
        $validator = new WorkflowValidator($expressionEvaluator);
        $builder = new WorkflowBuilder();
        $loader = new WorkflowLoader(
            workflowsPath: __DIR__ . '/workflows',
            builder: $builder,
            validator: $validator,
        );

        $compiledWorkflows = $loader->loadAll();

        $this->registry = new WorkflowRegistry();
        foreach ($compiledWorkflows as $workflow) {
            $this->registry->register($workflow);
        }

        $executor = new YourExecutorImplementation();
        $storage = new YourStorageImplementation();
        $clock = new YourClockImplementation();

        $this->workflowManager = new WorkflowManager(
            executor: $executor,
            storage: $storage,
            clock: $clock,
            registry: $this->registry,
            expressionEvaluator: $expressionEvaluator,
        );
    }

    public function mainLoop(): void
    {
        while (true) {
            $this->workflowManager->tick();

            usleep(100000);
        }
    }

    public function handleActionCompleted(string $actionId, string $result, array $context = []): void
    {
        $this->workflowManager->actionReceived($actionId, $result, $context);
    }

    public function exportWorkflowAsJson(string $workflowId): string
    {
        $workflow = $this->registry->get($workflowId);
        $serializer = new \Duyler\Workflow\Serialization\WorkflowSerializer();

        return $serializer->toJson($workflow);
    }

    public function importWorkflowFromJson(string $json): void
    {
        $deserializer = new \Duyler\Workflow\Serialization\WorkflowDeserializer();
        $workflow = $deserializer->fromJson($json);

        $builder = new WorkflowBuilder();
        $compiled = $builder->build($workflow);

        $this->registry->register($compiled);
    }
}

interface YourExecutorImplementation {}

interface YourStorageImplementation {}

interface YourClockImplementation {}

echo "Example: Framework integration with Workflow package\n";
echo "See workflows/ directory for workflow definitions\n";
echo "This example shows how to:\n";
echo "- Load workflows from files\n";
echo "- Register them in the registry\n";
echo "- Create WorkflowManager with your implementations\n";
echo "- Run the main loop (tick)\n";
echo "- Handle action completions\n";
echo "- Export/Import workflows as JSON\n";
