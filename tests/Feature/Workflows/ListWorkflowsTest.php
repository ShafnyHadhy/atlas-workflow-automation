<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_page_only_contains_workflows_belonging_to_that_workspace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();

        WorkspaceMembership::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaceA->id,
            'role' => 'member',
        ]);

        WorkspaceMembership::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaceB->id,
            'role' => 'member',
        ]);

        $createWorkflow = app(CreateWorkflow::class);

        $workflowA = $createWorkflow->execute(
            workspace: $workspaceA,
            name: 'Workspace A Workflow',
        );

        $workflowB = $createWorkflow->execute(
            workspace: $workspaceB,
            name: 'Workspace B Workflow',
        );

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspaceA->slug}");

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workspace.id', $workspaceA->id)
            ->has('workflows', 1)
            ->where('workflows.0.id', $workflowA->id)
            ->where('workflows.0.name', 'Workspace A Workflow')
        );
    }
}
