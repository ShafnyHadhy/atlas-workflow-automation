<?php

namespace Tests\Feature\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_their_workspaces(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspaceOne = Workspace::create([
            'name' => 'Acme Finance',
            'slug' => 'acme-finance',
        ]);

        $workspaceTwo = Workspace::create([
            'name' => 'GreenTech',
            'slug' => 'greentech',
        ]);

        $user->workspaces()->attach($workspaceOne->id, [
            'role' => 'owner',
        ]);

        $user->workspaces()->attach($workspaceTwo->id, [
            'role' => 'member',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/workspaces');

        $response->assertOk();

        $response->assertInertia(fn ($page) =>
            $page->component('workspaces/index')
        );
    }

    public function test_user_only_sees_workspaces_they_belong_to(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $userWorkspace = Workspace::create([
            'name' => 'Acme Finance',
            'slug' => 'acme-finance',
        ]);

        $otherWorkspace = Workspace::create([
            'name' => 'Secret Company',
            'slug' => 'secret-company',
        ]);

        $user->workspaces()->attach($userWorkspace->id, [
            'role' => 'owner',
        ]);

        $otherUser->workspaces()->attach($otherWorkspace->id, [
            'role' => 'owner',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/workspaces');

        $response->assertOk();

        $response->assertInertia(fn ($page) =>
            $page
                ->component('workspaces/index')
                ->has('workspaces', 1)
                ->where('workspaces.0.id', $userWorkspace->id)
                ->where('workspaces.0.name', 'Acme Finance')
        );
    }
}
