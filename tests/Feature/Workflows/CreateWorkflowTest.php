<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_creation_creates_a_workflow_with_a_draft_version(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        $this->assertInstanceOf(Workflow::class, $workflow);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'workspace_id' => $workspace->id,
            'name' => 'Invoice Processing',
        ]);

        $this->assertDatabaseHas('workflow_versions', [
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    public function test_new_workflow_starts_with_three_starter_nodes(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        $version = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('version_number', 1)
            ->firstOrFail();

        $this->assertCount(3, $version->nodes);

        $this->assertEqualsCanonicalizing(
            [
                WorkflowNodeType::Trigger,
                WorkflowNodeType::Action,
                WorkflowNodeType::End,
            ],
            $version->nodes->pluck('type')->all()
        );
    }

    public function test_new_workflow_starts_with_two_connected_edges(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        $version = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('version_number', 1)
            ->firstOrFail();

        $this->assertCount(2, $version->edges);

        $nodes = $version->nodes->keyBy('type');

        $this->assertDatabaseHas('workflow_edges', [
            'workflow_version_id' => $version->id,
            'source_node_key' => $nodes[WorkflowNodeType::Trigger->value]->node_key,
            'target_node_key' => $nodes[WorkflowNodeType::Action->value]->node_key,
        ]);

        $this->assertDatabaseHas('workflow_edges', [
            'workflow_version_id' => $version->id,
            'source_node_key' => $nodes[WorkflowNodeType::Action->value]->node_key,
            'target_node_key' => $nodes[WorkflowNodeType::End->value]->node_key,
        ]);
    }

    public function test_workflow_creation_is_scoped_to_the_supplied_workspace(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspaceA,
            name: 'Invoice Processing',
        );

        $this->assertSame($workspaceA->id, $workflow->workspace_id);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'workspace_id' => $workspaceA->id,
        ]);

        $this->assertDatabaseMissing('workflows', [
            'id' => $workflow->id,
            'workspace_id' => $workspaceB->id,
        ]);
    }

    public function test_workflow_description_is_saved_when_provided(): void
    {
        $workspace = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
            description: 'Process incoming invoices automatically.',
        );

        $this->assertSame(
            'Process incoming invoices automatically.',
            $workflow->description
        );
    }

    public function test_workflow_slug_is_unique_within_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $firstWorkflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        $secondWorkflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        $this->assertSame(
            'invoice-processing',
            $firstWorkflow->slug
        );

        $this->assertSame(
            'invoice-processing-2',
            $secondWorkflow->slug
        );

        $this->assertNotSame(
            $firstWorkflow->slug,
            $secondWorkflow->slug
        );
    }
}
