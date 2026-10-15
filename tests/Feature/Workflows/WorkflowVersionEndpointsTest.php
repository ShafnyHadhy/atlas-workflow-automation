<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowVersionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_page_includes_the_active_draft_as_selected_version(): void
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

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
            description: 'Process incoming invoices',
        );

        $draft = $workflow->draftVersion;

        $this->actingAs($user)
            ->get("/workspaces/{$workspace->slug}/workflows/{$workflow->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('workflows/show')
                ->where('selected_version.id', $draft->id)
                ->where('selected_version.status', 'draft')
                ->has('selected_version.nodes')
                ->has('selected_version.edges')
            );
    }

    public function test_version_page_includes_the_requested_version_graph(): void
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

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
            description: 'Process incoming invoices',
        );

        $version = $workflow->draftVersion;

        $this->actingAs($user)
            ->get(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}"
            )
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('workflows/show')
                ->where('selected_version.id', $version->id)
                ->where('selected_version.version_number', $version->version_number)
                ->has('selected_version.nodes')
                ->has('selected_version.edges')
            );
    }
}
