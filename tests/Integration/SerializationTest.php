<?php

declare(strict_types=1);

namespace Duyler\Workflow\Test\Integration;

use Duyler\Workflow\Build\WorkflowBuilder;
use Duyler\Workflow\DSL\RetryBackoff;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;
use Duyler\Workflow\Serialization\JsonSchemaValidator;
use Duyler\Workflow\Serialization\WorkflowDeserializer;
use Duyler\Workflow\Serialization\WorkflowSerializer;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SerializationTest extends TestCase
{
    private WorkflowSerializer $serializer;
    private WorkflowDeserializer $deserializer;
    private JsonSchemaValidator $validator;
    private WorkflowBuilder $builder;

    protected function setUp(): void
    {
        $this->serializer = new WorkflowSerializer();
        $this->deserializer = new WorkflowDeserializer();
        $this->validator = new JsonSchemaValidator();
        $this->builder = new WorkflowBuilder();
    }

    #[Test]
    public function it_serializes_simple_workflow_to_json(): void
    {
        $workflow = Workflow::define('TestWorkflow')
            ->description('A test workflow')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->onSuccess('step2'),
                Step::withId('step2')
                    ->actions(['Action2'])
                    ->isFinal(),
            );

        $compiled = $this->builder->build($workflow);
        $json = $this->serializer->toJson($compiled);

        $this->assertJson($json);

        $data = json_decode($json, true);
        assert(is_array($data));
        assert(is_string($data['id']));
        assert(is_string($data['description']));
        assert(is_array($data['steps']));
        assert(is_array($data['steps'][0]));
        assert(is_array($data['steps'][1]));
        assert(is_string($data['steps'][0]['id']));
        assert(is_string($data['steps'][1]['id']));

        $this->assertEquals('TestWorkflow', $data['id']);
        $this->assertEquals('A test workflow', $data['description']);
        $this->assertCount(2, $data['steps']);
        $this->assertEquals('step1', $data['steps'][0]['id']);
        $this->assertEquals('step2', $data['steps'][1]['id']);
    }

    #[Test]
    public function it_serializes_complex_workflow_with_all_features(): void
    {
        $workflow = Workflow::define('ComplexWorkflow')
            ->sequence(
                Step::withId('start')
                    ->actions(['Action1'])
                    ->when('result > 100', 'branch1')
                    ->onSuccess('normal')
                    ->onFail('error'),
                Step::withId('branch1')
                    ->parallel(['Action2', 'Action3'])
                    ->timeout(30)
                    ->onSuccess('final'),
                Step::withId('normal')
                    ->actions(['Action4'])
                    ->delay(10)
                    ->retry(3, 5, RetryBackoff::Exponential)
                    ->onSuccess('final'),
                Step::withId('error')
                    ->actions(['ErrorAction'])
                    ->isFinal(),
                Step::withId('final')
                    ->actions(['FinalAction'])
                    ->isFinal(),
            );

        $compiled = $this->builder->build($workflow);
        $data = $this->serializer->serialize($compiled);

        $this->validator->validate($data);

        $this->assertArrayHasKey('id', $data);
        assert(is_array($data['steps']));
        $this->assertCount(5, $data['steps']);

        assert(is_array($data['steps'][0]));
        $startStep = $data['steps'][0];
        assert(is_array($startStep['conditions']));
        $this->assertNotEmpty($startStep['conditions']);
        assert(is_array($startStep['conditions'][0]));
        assert(is_string($startStep['conditions'][0]['expression']));
        $this->assertEquals('result > 100', $startStep['conditions'][0]['expression']);

        assert(is_array($data['steps'][1]));
        $branch1 = $data['steps'][1];
        assert(is_array($branch1['parallel_actions']));
        assert(is_int($branch1['timeout']));
        $this->assertCount(2, $branch1['parallel_actions']);
        $this->assertEquals(30, $branch1['timeout']);

        assert(is_array($data['steps'][2]));
        $normalStep = $data['steps'][2];
        assert(is_int($normalStep['delay']));
        assert(is_array($normalStep['retry']));
        assert(is_int($normalStep['retry']['max_attempts']));
        assert(is_string($normalStep['retry']['backoff']));
        $this->assertEquals(10, $normalStep['delay']);
        $this->assertNotNull($normalStep['retry']);
        $this->assertEquals(3, $normalStep['retry']['max_attempts']);
        $this->assertEquals('exponential', $normalStep['retry']['backoff']);
    }

    #[Test]
    public function it_deserializes_json_to_workflow(): void
    {
        $json = <<<'JSON'
{
    "id": "DeserializedWorkflow",
    "description": "Test deserialization",
    "first_step": "step1",
    "steps": [
        {
            "id": "step1",
            "actions": ["Action1"],
            "parallel_actions": [],
            "conditions": [],
            "transitions": {
                "success": "step2",
                "fail": null
            },
            "delay": null,
            "timeout": null,
            "retry": null,
            "is_final": false
        },
        {
            "id": "step2",
            "actions": ["Action2"],
            "parallel_actions": [],
            "conditions": [],
            "transitions": {
                "success": null,
                "fail": null
            },
            "delay": null,
            "timeout": null,
            "retry": null,
            "is_final": true
        }
    ]
}
JSON;

        $workflow = $this->deserializer->fromJson($json);

        $this->assertEquals('DeserializedWorkflow', $workflow->getId());
        $this->assertEquals('Test deserialization', $workflow->getDescription());
        $this->assertCount(2, $workflow->getSteps());
    }

    #[Test]
    public function it_performs_roundtrip_serialization(): void
    {
        $original = Workflow::define('RoundtripWorkflow')
            ->description('Testing roundtrip')
            ->sequence(
                Step::withId('step1')
                    ->actions(['Action1'])
                    ->delay(5)
                    ->timeout(30)
                    ->when('result == true', 'step3')
                    ->onSuccess('step2')
                    ->onFail('error'),
                Step::withId('step2')
                    ->parallel(['Action2', 'Action3'])
                    ->retry(3, 1, RetryBackoff::Linear)
                    ->onSuccess('step3'),
                Step::withId('step3')
                    ->actions(['FinalAction'])
                    ->isFinal(),
                Step::withId('error')
                    ->actions(['ErrorAction'])
                    ->isFinal(),
            );

        $compiled = $this->builder->build($original);
        $json = $this->serializer->toJson($compiled);
        $deserialized = $this->deserializer->fromJson($json);

        $this->assertEquals($original->getId(), $deserialized->getId());
        $this->assertEquals($original->getDescription(), $deserialized->getDescription());
        $this->assertCount(count($original->getSteps()), $deserialized->getSteps());

        $recompiled = $this->builder->build($deserialized);
        $rejson = $this->serializer->toJson($recompiled);

        $this->assertEquals(
            json_decode($json, true),
            json_decode($rejson, true),
        );
    }

    #[Test]
    public function it_validates_json_structure(): void
    {
        $validData = [
            'id' => 'ValidWorkflow',
            'steps' => [
                [
                    'id' => 'step1',
                    'actions' => ['Action1'],
                    'is_final' => true,
                ],
            ],
        ];

        $this->validator->validate($validData);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_rejects_invalid_json_structure(): void
    {
        $this->expectException(Exception::class);

        $invalidData = [
            'steps' => [],
        ];

        $this->validator->validate($invalidData);
    }
}
