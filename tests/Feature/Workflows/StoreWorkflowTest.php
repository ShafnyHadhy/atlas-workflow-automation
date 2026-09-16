<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_create_a_workflow(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace =  Workspace::factory()->create();

        WorkspaceMembership::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'member',
        ]);

        $response = $this
            ->actingAs($user)
            ->post("/workspaces/{$workspace->slug}/workflows", [
                'name' => 'Invoice Processing',
                'description' => 'Process incoming invoices.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('workflows', [
            'workspace_id' => $workspace->id,
            'name' => 'Invoice Processing',
            'description' => 'Process incoming invoices.',
        ]);
    }

    public function test_non_member_cannot_create_a_workflow_in_a_workspace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post("/workspaces/{$workspace->slug}/workflows", [
                'name' => 'Unauthorized Workflow',
                'description' => 'This should not be created.',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('workflows', [
            'workspace_id' => $workspace->id,
            'name' => 'Unauthorized Workflow',
        ]);
    }

    public function test_workflow_name_is_required(): void
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

        $response = $this
            ->actingAs($user)
            ->post("/workspaces/{$workspace->slug}/workflows", [
                'description' => 'A workflow without a name.',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('workflows', 0);
    }

    public function test_guest_cannot_create_a_workflow(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->post(
            "/workspaces/{$workspace->slug}/workflows",
            [
                'name' => 'Guest Workflow',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseMissing('workflows', [
            'workspace_id' => $workspace->id,
            'name' => 'Guest Workflow',
        ]);
    }
}
