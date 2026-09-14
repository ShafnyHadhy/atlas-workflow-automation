<?php

namespace Database\Factories;

use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEdge>
 */
class WorkflowEdgeFactory extends Factory
{
    protected $model = WorkflowEdge::class;

    public function definition(): array
    {
        $version = WorkflowVersion::factory()->create();

        $sourceNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        $targetNode = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
        ]);

        return [
            'workflow_version_id' => $version->id,
            'source_node_key' => $sourceNode->node_key,
            'target_node_key' => $targetNode->node_key,
            'condition' => null,
        ];
    }
}
