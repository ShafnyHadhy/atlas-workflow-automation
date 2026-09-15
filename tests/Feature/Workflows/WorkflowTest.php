<?php

namespace Tests\Feature;

use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_belongs_to_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->assertTrue(
            $workflow->workspace->is($workspace)
        );
    }

    public function test_workspace_can_have_multiple_workflows(): void
    {
        $workspace = Workspace::factory()->create();

        Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->assertCount(2, $workspace->workflows);
    }

    public function test_workflow_slug_must_be_unique_within_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        Workflow::factory()->create([
            'workspace_id' => $workspace->id,
            'slug' => 'invoice-processing',
        ]);

        $this->expectException(QueryException::class);

        Workflow::factory()->create([
            'workspace_id' => $workspace->id,
            'slug' => 'invoice-processing',
        ]);
    }

    public function test_same_workflow_slug_can_exist_in_different_workspaces(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();

        $workflowA = Workflow::factory()->create([
            'workspace_id' => $workspaceA->id,
            'slug' => 'invoice-processing',
        ]);

        $workflowB = Workflow::factory()->create([
            'workspace_id' => $workspaceB->id,
            'slug' => 'invoice-processing',
        ]);

        $this->assertSame('invoice-processing', $workflowA->slug);
        $this->assertSame('invoice-processing', $workflowB->slug);
        $this->assertNotSame($workflowA->workspace_id, $workflowB->workspace_id);
    }
}
