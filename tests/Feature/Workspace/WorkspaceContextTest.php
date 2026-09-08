<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

Class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_access_workspace_context(): void
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
            ->get("/workspaces/{$workspace->slug}");

        $response->assertOk();
    }

    public function test_non_member_cannot_access_workspace_context(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get("/workspaces/{$workspace->slug}");

        $response->assertForbidden();
    }
}
