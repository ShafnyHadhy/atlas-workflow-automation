<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => 'pass1234',
            'email_verified_at' => now(),
        ]);

        $alice = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'pass1234',
            'email_verified_at' => now(),
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'pass1234',
            'email_verified_at' => now(),
        ]);

        $atlasLabs = Workspace::factory()->create([
            'name' => 'Atlas Labs',
            'slug' => 'atlas-labs',
        ]);

        $financeCo = Workspace::factory()->create([
            'name' => 'Finance Co',
            'slug' => 'finance-co',
        ]);

        $acmeSolutions = Workspace::factory()->create([
            'name' => 'Acme Solutions',
            'slug' => 'acme-solutions',
        ]);

        $demoWorkspace = Workspace::factory()->create([
            'name' => 'Demo Workspace',
            'slug' => 'demo-workspace',
        ]);

        WorkspaceMembership::create([
            'user_id' => $admin->id,
            'workspace_id' => $atlasLabs->id,
            'role' => WorkspaceRole::Owner,
        ]);

        WorkspaceMembership::create([
            'user_id' => $admin->id,
            'workspace_id' => $financeCo->id,
            'role' => WorkspaceRole::Admin,
        ]);

        WorkspaceMembership::create([
            'user_id' => $admin->id,
            'workspace_id' => $acmeSolutions->id,
            'role' => WorkspaceRole::Member,
        ]);

        WorkspaceMembership::create([
            'user_id' => $admin->id,
            'workspace_id' => $demoWorkspace->id,
            'role' => WorkspaceRole::Viewer,
        ]);

        WorkspaceMembership::create([
            'user_id' => $alice->id,
            'workspace_id' => $atlasLabs->id,
            'role' => WorkspaceRole::Member,
        ]);

        WorkspaceMembership::create([
            'user_id' => $alice->id,
            'workspace_id' => $financeCo->id,
            'role' => WorkspaceRole::Owner,
        ]);

        WorkspaceMembership::create([
            'user_id' => $bob->id,
            'workspace_id' => $acmeSolutions->id,
            'role' => WorkspaceRole::Admin,
        ]);
    }
}
