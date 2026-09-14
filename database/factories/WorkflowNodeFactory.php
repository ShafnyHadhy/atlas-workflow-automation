<?php

namespace Database\Factories;

use App\Enums\WorkflowNodeType;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowNode>
 */
class WorkflowNodeFactory extends Factory
{
    protected $model = WorkflowNode::class;

    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::Action,
            'configuration' => [],
            'position' => [
                'x' => 0,
                'y' => 0,
            ],
        ];
    }
}
