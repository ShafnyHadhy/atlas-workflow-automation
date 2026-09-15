<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkflow
{
    public function execute( Workspace $workspace, string $name, ?string $description = null ): Workflow
    {
        return DB::transaction(function () use ($workspace, $name, $description) {

            $workflow = Workflow::create([
                'workspace_id' => $workspace->id,
                'name' => $name,
                'slug' => $this->generateUniqueSlug($workspace, $name),
                'description' => $description
            ]);

            $version = $workflow->versions()->create([
                'version_number' => 1,
                'status' => WorkflowVersionStatus::Draft,
                'published_at' => null,
            ]);

            $triggerNode = $version->nodes()->create([
                'node_key' => (string) Str::uuid(),
                'type' => WorkflowNodeType::Trigger,
                'configuration' => [],
                'position' => [
                    'x' => 100,
                    'y' => 100,
                ],
            ]);

            $actionNode = $version->nodes()->create([
                'node_key' => (string) Str::uuid(),
                'type' => WorkflowNodeType::Action,
                'configuration' => [],
                'position' => [
                    'x' => 100,
                    'y' => 250,
                ],
            ]);

            $endNode = $version->nodes()->create([
                'node_key' => (string) Str::uuid(),
                'type' => WorkflowNodeType::End,
                'configuration' => [],
                'position' => [
                    'x' => 100,
                    'y' => 400,
                ],
            ]);

            $version->edges()->create([
                'source_node_key' => $triggerNode->node_key,
                'target_node_key' => $actionNode->node_key,
                'condition' => null,
            ]);

            $version->edges()->create([
                'source_node_key' => $actionNode->node_key,
                'target_node_key' => $endNode->node_key,
                'condition' => null,
            ]);

            return $workflow;
        });
    }

    private function generateUniqueSlug( Workspace $workspace, string $name ): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (
            $workspace->workflows()
                ->where('slug', $slug)
                ->exists()
        ){
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
