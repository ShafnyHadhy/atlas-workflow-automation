<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

Class WorkspaceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_select_a_workspace_they_belong_to(): void
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
            ->post("/workspaces/{$workspace->slug}/select");

        $response->assertRedirect();

        $response->assertSessionHas(
            'active_workspace_id', $workspace->id
        );
    }

    public function test_user_cannot_select_a_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()
            ->create();

        $response = $this
            ->actingAs($user)
            ->post("/workspaces/{$workspace->slug}/select");

        $response->assertForbidden();

        $response->assertSessionMissing('active_workspace_id');
    }

    public function test_guest_cannot_select_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this
            ->post( "/workspaces/{$workspace->slug}/select" );

        $response->assertRedirect(route('login'));
    }

    public function test_workspace_url_is_authoritative_over_active_workspace_session(): void
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

        $response = $this
            ->actingAs($user)
            ->withSession([
                'active_workspace_id' => $workspaceA->id,
            ])
            ->get("/workspaces/{$workspaceB->slug}");

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('workspace.id', $workspaceB->id) );
    }

    public function test_removed_membership_prevents_access_even_if_workspace_is_active_in_session(): void
    {
        $user = User::factory()
            ->create([
                'email_verified_at' => now(),
            ]);

        $workspace = Workspace::factory()->create();

        $membership = WorkspaceMembership::factory()
            ->create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role' => 'member',
            ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'active_workspace_id' => $workspace->id,
            ])
            ->get("/workspaces/{$workspace->slug}");

        $response->assertOk();

        $membership->delete();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'active_workspace_id' => $workspace->id,
            ])
            ->get("/workspaces/{$workspace->slug}");

        $response->assertForbidden();
    }

    public function test_switching_workspace_updates_the_active_workspace_in_session(): void
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

        $this->actingAs($user)
            ->withSession([ 'active_workspace_id' => $workspaceA->id, ])
            ->post("/workspaces/{$workspaceB->slug}/select")
            ->assertRedirect();

        $this->assertEquals(
            $workspaceB->id,
            session('active_workspace_id')
        );
    }

    public function test_workspaces_page_returns_the_active_workspace(): void
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

        $response = $this
            ->actingAs($user)
            ->withSession([
                'active_workspace_id' => $workspaceB->id,
            ])
            ->get('/workspaces');

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('activeWorkspaceId', $workspaceB->id)
        );
    }

    public function test_workspaces_page_ignores_stale_active_workspace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'active_workspace_id' => $workspace->id,
            ])
            ->get('/workspaces');

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('activeWorkspaceId', null)
        );

        $response->assertSessionMissing('active_workspace_id');
    }
}
