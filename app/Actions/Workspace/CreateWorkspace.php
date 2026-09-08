<?php

namespace App\Actions\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function execute(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            return $workspace;
        });
    }
}
