<?php

namespace Tests\Feature\Workflows;

use Illuminate\Support\Str;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_view_a_workflow(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        WorkspaceMembership::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'member',
        ]);

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/workflows/{$workflow->slug}");

        $response->assertOk();
    }

    public function test_non_member_cannot_view_a_workflow(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/workflows/{$workflow->slug}");

        $response->assertForbidden();
    }

    public function test_workflow_from_another_workspace_cannot_be_accessed_through_a_different_workspace_url(): void
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

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspaceB->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspaceA->slug}/workflows/{$workflow->slug}");

        $response->assertNotFound();
    }

    public function test_workflow_view_includes_the_current_draft_with_its_graph(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        WorkspaceMembership::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'member',
        ]);

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = $workflow->versions()->create([
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $triggerNode = $version->nodes()->create([
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::Trigger,
            'configuration' => [],
            'position' => ['x' => 100, 'y' => 100],
        ]);

        $endNode = $version->nodes()->create([
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::End,
            'configuration' => [],
            'position' => ['x' => 100, 'y' => 250],
        ]);

        $version->edges()->create([
            'source_node_key' => $triggerNode->node_key,
            'target_node_key' => $endNode->node_key,
            'condition' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/workflows/{$workflow->slug}");

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workflow.id', $workflow->id)
                ->where('workflow.name', $workflow->name)
                ->where('workflow.slug', $workflow->slug)
                ->where('workflow.description', $workflow->description)
                ->where('workflow.draft_version.id', $version->id)
                ->where('workflow.draft_version.version_number', 1)
                ->where('workflow.draft_version.status', 'draft')
                ->where('workflow.draft_version.nodes', fn ($nodes) => count($nodes) === 2)
                ->where('workflow.draft_version.edges', fn ($edges) => count($edges) === 1)
            );
    }
}
