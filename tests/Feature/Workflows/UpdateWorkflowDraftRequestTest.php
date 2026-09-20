<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateWorkflowDraftRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_nodes_are_required(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'edges' => [],
                ]
            );

        $response->assertSessionHasErrors('nodes');
    }

    public function test_node_key_must_be_a_uuid(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => 'not-a-uuid',
                            'type' => 'trigger',
                            'configuration' => [],
                            'position' => [
                                'x' => 100,
                                'y' => 100,
                            ],
                        ],
                    ],
                    'edges' => [],
                ]
            );

        $response->assertSessionHasErrors('nodes.0.node_key');
    }

    public function test_node_type_must_be_supported(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) \Illuminate\Support\Str::uuid(),
                            'type' => 'email',
                            'configuration' => [],
                            'position' => [
                                'x' => 100,
                                'y' => 100,
                            ],
                        ],
                    ],
                    'edges' => [],
                ]
            );

        $response->assertSessionHasErrors('nodes.0.type');
    }

    public function test_node_configuration_is_required(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) \Illuminate\Support\Str::uuid(),
                            'type' => 'trigger',
                            'position' => [
                                'x' => 100,
                                'y' => 100,
                            ],
                        ],
                    ],
                    'edges' => [],
                ]
            );

        $response->assertSessionHasErrors('nodes.0.configuration');
    }

    public function test_node_position_requires_x_and_y(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) \Illuminate\Support\Str::uuid(),
                            'type' => 'trigger',
                            'configuration' => [],
                            'position' => [],
                        ],
                    ],
                    'edges' => [],
                ]
            );

        $response->assertSessionHasErrors([
            'nodes.0.position.x',
            'nodes.0.position.y',
        ]);
    }

    public function test_edge_requires_source_and_target_node_keys(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [],
                    'edges' => [
                        [],
                    ],
                ]
            );

        $response->assertSessionHasErrors([
            'edges.0.source_node_key',
            'edges.0.target_node_key',
        ]);
    }

    public function test_edge_node_keys_must_be_uuids(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [],
                    'edges' => [
                        [
                            'source_node_key' => 'not-a-uuid',
                            'target_node_key' => 'also-not-a-uuid',
                        ],
                    ],
                ]
            );

        $response->assertSessionHasErrors([
            'edges.0.source_node_key',
            'edges.0.target_node_key',
        ]);
    }

    public function test_edge_target_node_must_exist_in_submitted_nodes(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $sourceNodeKey = (string) \Illuminate\Support\Str::uuid();
        $missingNodeKey = (string) \Illuminate\Support\Str::uuid();

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => $sourceNodeKey,
                            'type' => 'trigger',
                            'configuration' => [],
                            'position' => [
                                'x' => 100,
                                'y' => 100,
                            ],
                        ],
                    ],
                    'edges' => [
                        [
                            'source_node_key' => $sourceNodeKey,
                            'target_node_key' => $missingNodeKey,
                        ],
                    ],
                ]
            );

        $response->assertSessionHasErrors('edges.0.target_node_key');
    }

    public function test_edge_source_node_must_exist_in_submitted_nodes(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $missingNodeKey = (string) \Illuminate\Support\Str::uuid();
        $targetNodeKey = (string) \Illuminate\Support\Str::uuid();

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => $targetNodeKey,
                            'type' => 'end',
                            'configuration' => [],
                            'position' => [
                                'x' => 100,
                                'y' => 100,
                            ],
                        ],
                    ],
                    'edges' => [
                        [
                            'source_node_key' => $missingNodeKey,
                            'target_node_key' => $targetNodeKey,
                        ],
                    ],
                ]
            );

        $response->assertSessionHasErrors('edges.0.source_node_key');
    }
}
