<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\UpdateWorkflowDraft;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateWorkflowDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_graph_can_be_replaced_atomically(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $oldTrigger = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::Trigger,
        ]);

        $oldEnd = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::End,
        ]);

        $version->edges()->create([
            'source_node_key' => $oldTrigger->node_key,
            'target_node_key' => $oldEnd->node_key,
            'condition' => null,
        ]);

        $newTriggerKey = (string) Str::uuid();
        $newActionKey = (string) Str::uuid();
        $newEndKey = (string) Str::uuid();

        $updated = app(UpdateWorkflowDraft::class)->execute(
            version: $version,
            nodes: [
                [
                    'node_key' => $newTriggerKey,
                    'type' => WorkflowNodeType::Trigger,
                    'configuration' => [],
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'node_key' => $newActionKey,
                    'type' => WorkflowNodeType::Action,
                    'configuration' => [],
                    'position' => ['x' => 100, 'y' => 250],
                ],
                [
                    'node_key' => $newEndKey,
                    'type' => WorkflowNodeType::End,
                    'configuration' => [],
                    'position' => ['x' => 100, 'y' => 400],
                ],
            ],
            edges: [
                [
                    'source_node_key' => $newTriggerKey,
                    'target_node_key' => $newActionKey,
                    'condition' => null,
                ],
                [
                    'source_node_key' => $newActionKey,
                    'target_node_key' => $newEndKey,
                    'condition' => null,
                ],
            ],
        );

        $this->assertTrue($updated);

        $this->assertDatabaseCount('workflow_nodes', 3);
        $this->assertDatabaseCount('workflow_edges', 2);

        $this->assertDatabaseMissing('workflow_nodes', [
            'id' => $oldTrigger->id,
        ]);

        $this->assertDatabaseMissing('workflow_nodes', [
            'id' => $oldEnd->id,
        ]);
    }

    public function test_published_version_cannot_be_updated(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Published,
        ]);

        $node = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::Trigger,
        ]);

        $this->expectException(\LogicException::class);

        app(UpdateWorkflowDraft::class)->execute(
            version: $version,
            nodes: [
                [
                    'node_key' => (string) Str::uuid(),
                    'type' => WorkflowNodeType::End,
                    'configuration' => [],
                    'position' => ['x' => 100, 'y' => 100],
                ],
            ],
            edges: [],
        );

        $this->assertDatabaseHas('workflow_nodes', [
            'id' => $node->id,
        ]);
    }

    public function test_failed_graph_update_rolls_back_the_entire_change(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $oldTrigger = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::Trigger,
        ]);

        $oldEnd = WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => (string) Str::uuid(),
            'type' => WorkflowNodeType::End,
        ]);

        $version->edges()->create([
            'source_node_key' => $oldTrigger->node_key,
            'target_node_key' => $oldEnd->node_key,
            'condition' => null,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        app(UpdateWorkflowDraft::class)->execute(
            version: $version,
            nodes: [
                [
                    'node_key' => (string) Str::uuid(),
                    'type' => WorkflowNodeType::Trigger,
                    'configuration' => [],
                    'position' => ['x' => 100, 'y' => 100],
                ],
            ],
            edges: [
                [
                    'source_node_key' => (string) Str::uuid(),
                    'target_node_key' => (string) Str::uuid(),
                    'condition' => null,
                ],
            ],
        );

        $this->assertDatabaseHas('workflow_nodes', [
            'id' => $oldTrigger->id,
        ]);

        $this->assertDatabaseHas('workflow_nodes', [
            'id' => $oldEnd->id,
        ]);

        $this->assertDatabaseCount('workflow_nodes', 2);
        $this->assertDatabaseCount('workflow_edges', 1);
    }

    public function test_valid_draft_graph_can_be_updated_through_http(): void
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

        $triggerNodeKey = (string) Str::uuid();
        $actionNodeKey = (string) Str::uuid();
        $endNodeKey = (string) Str::uuid();

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => $triggerNodeKey,
            'type' => 'trigger',
            'configuration' => [],
            'position' => [
                'x' => 100,
                'y' => 100,
            ],
        ]);

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => $actionNodeKey,
            'type' => 'action',
            'configuration' => [],
            'position' => [
                'x' => 100,
                'y' => 250,
            ],
        ]);

        WorkflowNode::factory()->create([
            'workflow_version_id' => $version->id,
            'node_key' => $endNodeKey,
            'type' => 'end',
            'configuration' => [],
            'position' => [
                'x' => 100,
                'y' => 400,
            ],
        ]);

        $newTriggerNodeKey = (string) Str::uuid();
        $newActionNodeKey = (string) Str::uuid();

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => $newTriggerNodeKey,
                            'type' => 'trigger',
                            'configuration' => [],
                            'position' => [
                                'x' => 200,
                                'y' => 100,
                            ],
                        ],
                        [
                            'node_key' => $newActionNodeKey,
                            'type' => 'action',
                            'configuration' => [
                                'action' => 'send_email',
                            ],
                            'position' => [
                                'x' => 200,
                                'y' => 250,
                            ],
                        ],
                    ],
                    'edges' => [
                        [
                            'source_node_key' => $newTriggerNodeKey,
                            'target_node_key' => $newActionNodeKey,
                            'condition' => null,
                        ],
                    ],
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseCount('workflow_nodes', 2);
        $this->assertDatabaseCount('workflow_edges', 1);

        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_version_id' => $version->id,
            'node_key' => $newTriggerNodeKey,
            'type' => 'trigger',
        ]);

        $this->assertDatabaseHas('workflow_nodes', [
            'workflow_version_id' => $version->id,
            'node_key' => $newActionNodeKey,
            'type' => 'action',
        ]);

        $this->assertDatabaseHas('workflow_edges', [
            'workflow_version_id' => $version->id,
            'source_node_key' => $newTriggerNodeKey,
            'target_node_key' => $newActionNodeKey,
        ]);
    }

    public function test_workflow_version_from_another_workspace_cannot_be_updated_through_a_different_workspace_url(): void
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

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspaceA->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) Str::uuid(),
                            'type' => WorkflowNodeType::Trigger,
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

        $response->assertNotFound();
    }

    public function test_non_member_cannot_update_a_workflow_draft(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) Str::uuid(),
                            'type' => WorkflowNodeType::Trigger,
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

        $response->assertForbidden();
    }

    public function test_guest_cannot_update_a_workflow_draft(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $response = $this->put(
            "/workspaces/{$workspace->slug}/workflows/{$workflow->slug}/versions/{$version->id}",
            [
                'nodes' => [
                    [
                        'node_key' => (string) Str::uuid(),
                        'type' => WorkflowNodeType::Trigger,
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

        $response->assertRedirect('/login');
    }

    public function test_version_from_another_workflow_cannot_be_updated_through_a_different_workflow_url(): void
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

        $workflowA = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $workflowB = Workflow::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $versionB = WorkflowVersion::factory()->create([
            'workflow_id' => $workflowB->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                "/workspaces/{$workspace->slug}/workflows/{$workflowA->slug}/versions/{$versionB->id}",
                [
                    'nodes' => [
                        [
                            'node_key' => (string) Str::uuid(),
                            'type' => WorkflowNodeType::Trigger,
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

        $response->assertNotFound();
    }
}
