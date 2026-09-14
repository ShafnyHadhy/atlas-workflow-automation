<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
