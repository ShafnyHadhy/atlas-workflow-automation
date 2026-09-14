<?php

namespace Tests\Feature;

use App\Enums\WorkflowNodeType;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowNodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_node_belongs_to_a_workflow_version(): void
    {
        $version = WorkflowVersion::factory()->create();

        $node = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $this->assertTrue(
            $node->workflowVersion->is($version)
        );
    }

    public function test_workflow_version_can_have_multiple_nodes(): void
    {
        $version = WorkflowVersion::factory()->create();

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'type' => WorkflowNodeType::Trigger,
        ]);

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'type' => WorkflowNodeType::Action,
        ]);

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'type' => WorkflowNodeType::End,
        ]);

        $this->assertCount(3, $version->nodes);
    }

    public function test_node_key_must_be_unique_within_a_workflow_version(): void
    {
        $version = WorkflowVersion::factory()->create();

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->expectException(QueryException::class);

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);
    }

    public function test_same_node_key_can_exist_in_different_workflow_versions(): void
    {
        $versionA = WorkflowVersion::factory()->create();
        $versionB = WorkflowVersion::factory()->create();

        $nodeA = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionA->id,
            'node_key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $nodeB = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionB->id,
            'node_key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->assertSame($nodeA->node_key, $nodeB->node_key);
        $this->assertNotSame(
            $nodeA->workflow_version_id,
            $nodeB->workflow_version_id
        );
    }

    public function test_node_type_is_cast_to_enum(): void
    {
        $node = WorkflowNode::factory()->create([
            'type' => WorkflowNodeType::Trigger,
        ]);

        $this->assertSame(
            WorkflowNodeType::Trigger,
            $node->type
        );
    }

    public function test_configuration_is_cast_to_array(): void
    {
        $node = WorkflowNode::factory()->create([
            'configuration' => [
                'event' => 'invoice.received',
            ],
        ]);

        $this->assertSame(
            ['event' => 'invoice.received'],
            $node->configuration
        );
    }

    public function test_position_is_cast_to_array(): void
    {
        $node = WorkflowNode::factory()->create([
            'position' => [
                'x' => 250,
                'y' => 120,
            ],
        ]);

        $this->assertSame(
            [
                'x' => 250,
                'y' => 120,
            ],
            $node->position
        );
    }
}
