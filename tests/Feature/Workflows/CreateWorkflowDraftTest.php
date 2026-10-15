<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\CreateWorkflowDraft;
use App\Actions\Workflows\PublishWorkflowVersion;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateWorkflowDraftTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflowWithPublishedVersion(): Workflow
    {
        $workspace = Workspace::factory()->create();

        $workflow = app(CreateWorkflow::class)->execute(
            workspace: $workspace,
            name: 'Invoice Processing',
        );

        app(PublishWorkflowVersion::class)->execute(
            $workflow->versions()->firstOrFail(),
        );

        return $workflow->fresh();
    }

    public function test_it_creates_the_next_draft_version(): void
    {
        $workflow = $this->createWorkflowWithPublishedVersion();

        $draft = app(CreateWorkflowDraft::class)->execute($workflow);

        $this->assertSame(2, $draft->version_number);
        $this->assertSame(
            WorkflowVersionStatus::Draft,
            $draft->status,
        );
        $this->assertNull($draft->published_at);

        $this->assertDatabaseHas('workflow_versions', [
            'workflow_id' => $workflow->id,
            'version_number' => 2,
            'status' => 'draft',
        ]);
    }

    public function test_it_copies_nodes_and_edges_into_independent_records(): void
    {
        $workflow = $this->createWorkflowWithPublishedVersion();

        $published = $workflow->versions()
            ->where('status', 'published')
            ->firstOrFail();

        $published->load(['nodes', 'edges']);

        $draft = app(CreateWorkflowDraft::class)->execute($workflow);

        $draft->load(['nodes', 'edges']);

        $this->assertCount(
            $published->nodes->count(),
            $draft->nodes,
        );

        $this->assertCount(
            $published->edges->count(),
            $draft->edges,
        );

        $this->assertSame(
            $published->nodes->pluck('type')->sort()->values()->all(),
            $draft->nodes->pluck('type')->sort()->values()->all(),
        );

        $this->assertSame(
            $published->nodes->pluck('position')->sortKeys()->values()->all(),
            $draft->nodes->pluck('position')->sortKeys()->values()->all(),
        );

        $this->assertSame(
            $published->nodes->pluck('configuration')->sortKeys()->values()->all(),
            $draft->nodes->pluck('configuration')->sortKeys()->values()->all(),
        );

        $publishedKeys = $published->nodes
            ->pluck('node_key')
            ->all();

        $draftKeys = $draft->nodes
            ->pluck('node_key')
            ->all();

        foreach ($draftKeys as $key) {
            $this->assertNotContains($key, $publishedKeys);
        }

        $draftKeySet = array_flip($draftKeys);

        foreach ($draft->edges as $edge) {
            $this->assertArrayHasKey(
                $edge->source_node_key,
                $draftKeySet,
            );

            $this->assertArrayHasKey(
                $edge->target_node_key,
                $draftKeySet,
            );
        }

        $this->assertSame(
            $published->edges->pluck('condition')->sortKeys()->values()->all(),
            $draft->edges->pluck('condition')->sortKeys()->values()->all(),
        );
    }

    public function test_it_cannot_create_a_draft_without_a_published_version(): void
    {
        $workflow = app(CreateWorkflow::class)->execute(
            Workspace::factory()->create(),
            'Invoice Processing',
        );

        try {
            app(CreateWorkflowDraft::class)->execute($workflow);

            $this->fail('Expected draft creation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'version',
                $exception->errors(),
            );
        }

        $this->assertSame(
            1,
            $workflow->versions()->count(),
        );
    }

    public function test_it_prevents_a_second_active_draft(): void
    {
        $workflow = $this->createWorkflowWithPublishedVersion();

        $action = app(CreateWorkflowDraft::class);

        $firstDraft = $action->execute($workflow);

        try {
            $action->execute($workflow);

            $this->fail('Expected duplicate draft creation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'version',
                $exception->errors(),
            );
        }

        $this->assertSame(
            1,
            $workflow->versions()
                ->where('status', 'draft')
                ->count(),
        );

        $this->assertDatabaseHas('workflow_versions', [
            'id' => $firstDraft->id,
            'status' => 'draft',
        ]);
    }
}
