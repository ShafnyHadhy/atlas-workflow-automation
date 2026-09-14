<?php

namespace Tests\Feature;

use App\Enums\WorkflowNodeType;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edge_belongs_to_a_workflow_version(): void
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $edge = WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);

        $this->assertTrue(
            $edge->workflowVersion->is($version)
        );
    }

    public function test_workflow_version_can_have_multiple_edges(): void
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $middleNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $middleNode->node_key,
        ]);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $middleNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);

        $this->assertCount(2, $version->fresh()->edges);
    }

    public function test_edge_can_connect_nodes_within_the_same_version(): void
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $edge = WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);

        $this->assertSame( $sourceNode->node_key, $edge->source_node_key );

        $this->assertSame( $targetNode->node_key, $edge->target_node_key );
    }

    public function test_duplicate_edge_is_not_allowed_within_a_version(): void
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);

        $this->expectException(QueryException::class);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);
    }

    public function test_edge_cannot_reference_source_node_from_another_version(): void
    {
        $versionA = WorkflowVersion::factory()->create();

        $versionB = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionA->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionB->id,
        ]);

        $this->expectException(QueryException::class);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $versionB->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);
    }

    public function test_edge_cannot_reference_target_node_from_another_version(): void
    {
        $versionA = WorkflowVersion::factory()->create();

        $versionB = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionB->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $versionA->id,
        ]);

        $this->expectException(QueryException::class);

        WorkflowEdge::factory()->create([
            'workflow_version_id' => $versionB->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);
    }

    public function test_condition_is_cast_to_array(): void
    {
        $edge = WorkflowEdge::factory()->create([
            'condition' => [
                'field' => 'amount',
                'operator' => 'greater_than',
                'value' => 1000,
            ],
        ]);

        $this->assertSame( [
            'field' => 'amount',
            'operator' => 'greater_than',
            'value' => 1000,
        ],
        $edge->condition );
    }

    public function test_deleting_workflow_version_deletes_its_edges(): void
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $edge = WorkflowEdge::factory()->create([
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
        ]);

        $edgeId = $edge->id;

        $version->delete();

        $this->assertDatabaseMissing('workflow_edges', [
            'id' => $edgeId,
        ]);
    }
}
