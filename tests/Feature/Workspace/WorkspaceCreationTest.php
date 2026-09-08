<?php

namespace Tests\Feature\Workspace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class WorkspaceCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_workspace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/workspaces', [
                'name' => 'Acme Finance',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Acme Finance',
            'slug' => 'acme-finance',
        ]);

        $workspace = Workspace::where('slug', 'acme-finance')->firstOrFail();

        $this->assertDatabaseHas('workspace_memberships', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
    }

    public function test_workspace_name_is_required(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/workspaces', []);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('workspaces', 0);
    }

    // public function test_unverified_user_cannot_create_a_workspace(): void
    // {
    //     $user = User::factory()->create([
    //         'email_verified_at' => null,
    //     ]);

    //     $response = $this
    //         ->actingAs($user)
    //         ->post('/workspaces', [
    //             'name' => 'Acme Finance',
    //         ]);

    //     $response->assertForbidden();

    //     $this->assertDatabaseCount('workspaces', 0);
    // }

    public function test_guest_cannot_create_a_workspace(): void
    {
        $response = $this->post('/workspaces', [
            'name' => 'Acme Finance',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_workspace_name_cannot_exceed_100_characters(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/workspaces', [
                'name' => str_repeat('A', 101),
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('workspaces', 0);
    }
}
