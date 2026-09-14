<?php

namespace Database\Factories;

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVersion>
 */
class WorkflowVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = WorkflowVersion::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
            'published_at' => null,
        ];
    }
}
