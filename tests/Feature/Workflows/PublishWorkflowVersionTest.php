<?php

namespace Tests\Feature\Workflows;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\CreateWorkflowDraft;
use App\Actions\Workflows\PublishWorkflowVersion;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublishWorkflowVersionTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(): Workflow
    {
        return app(CreateWorkflow::class)->execute(
            Workspace::factory()->create(),
            'Invoice Processing',
        );
    }

    public function test_it_publishes_a_valid_draft(): void
    {
        $workflow = $this->createWorkflow();

        $draft = $workflow->versions()->firstOrFail();

        $published = app(PublishWorkflowVersion::class)
            ->execute($draft);

        $this->assertSame(
            WorkflowVersionStatus::Published,
            $published->status,
        );

        $this->assertNotNull($published->published_at);

        $this->assertDatabaseHas('workflow_versions', [
            'id' => $draft->id,
            'status' => 'published',
        ]);
    }

    public function test_it_archives_the_previous_published_version(): void
    {
        $workflow = $this->createWorkflow();

        $publishAction = app(PublishWorkflowVersion::class);

        $versionOne = $publishAction->execute(
            $workflow->versions()->firstOrFail(),
        );

        $versionOnePublishedAt = $versionOne->published_at;

        $draftTwo = app(CreateWorkflowDraft::class)
            ->execute($workflow);

        $versionTwo = $publishAction->execute($draftTwo);

        $this->assertSame(
            WorkflowVersionStatus::Archived,
            $versionOne->fresh()->status,
        );

        $this->assertSame(
            WorkflowVersionStatus::Published,
            $versionTwo->status,
        );

        $this->assertSame(
            1,
            $workflow->versions()
                ->where('status', 'published')
                ->count(),
        );

        // Archiving preserves the historical publication timestamp.
        $this->assertEquals(
            $versionOnePublishedAt,
            $versionOne->fresh()->published_at,
        );
    }

    public function test_it_rejects_a_graph_without_a_trigger(): void
    {
        $workflow = $this->createWorkflow();

        $draft = $workflow->versions()
            ->with('nodes')
            ->firstOrFail();

        $trigger = $draft->nodes->firstWhere(
            'type',
            WorkflowNodeType::Trigger,
        );

        $this->assertNotNull($trigger);

        $trigger->delete();

        try {
            app(PublishWorkflowVersion::class)->execute($draft);

            $this->fail('An invalid graph must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'graph',
                $exception->errors(),
            );
        }

        $this->assertSame(
            WorkflowVersionStatus::Draft,
            $draft->fresh()->status,
        );
    }

    public function test_it_rejects_a_graph_containing_a_cycle(): void
    {
        $workflow = $this->createWorkflow();

        $draft = $workflow->versions()
            ->with('nodes')
            ->firstOrFail();

        $trigger = $draft->nodes->firstWhere(
            'type',
            WorkflowNodeType::Trigger,
        );

        $end = $draft->nodes->firstWhere(
            'type',
            WorkflowNodeType::End,
        );

        $this->assertNotNull($trigger);
        $this->assertNotNull($end);

        // The starter graph already has Trigger -> Action -> End.
        // Adding End -> Trigger creates a directed cycle.
        $draft->edges()->create([
            'source_node_key' => $end->node_key,
            'target_node_key' => $trigger->node_key,
            'condition' => null,
        ]);

        try {
            app(PublishWorkflowVersion::class)->execute($draft);

            $this->fail('A cyclic graph must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'graph',
                $exception->errors(),
            );
        }

        $this->assertSame(
            WorkflowVersionStatus::Draft,
            $draft->fresh()->status,
        );
    }

    public function test_failed_publication_preserves_the_current_published_version(): void
    {
        $workflow = $this->createWorkflow();

        $publishAction = app(PublishWorkflowVersion::class);

        $versionOne = $publishAction->execute(
            $workflow->versions()->firstOrFail(),
        );

        $draftTwo = app(CreateWorkflowDraft::class)
            ->execute($workflow);

        // Make the second version invalid before attempting publication.
        $trigger = $draftTwo->nodes()
            ->where('type', WorkflowNodeType::Trigger->value)
            ->firstOrFail();

        $trigger->delete();

        try {
            $publishAction->execute($draftTwo);

            $this->fail('Invalid publication should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'graph',
                $exception->errors(),
            );
        }

        $this->assertSame(
            WorkflowVersionStatus::Published,
            $versionOne->fresh()->status,
        );

        $this->assertSame(
            WorkflowVersionStatus::Draft,
            $draftTwo->fresh()->status,
        );

        $this->assertSame(
            1,
            $workflow->versions()
                ->where('status', 'published')
                ->count(),
        );
    }

    public function test_it_rejects_publishing_an_already_published_version(): void
    {
        $workflow = $this->createWorkflow();

        $published = app(PublishWorkflowVersion::class)
            ->execute($workflow->versions()->firstOrFail());

        try {
            app(PublishWorkflowVersion::class)->execute($published);

            $this->fail('An already published version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'version',
                $exception->errors(),
            );
        }

        $this->assertSame(
            WorkflowVersionStatus::Published,
            $published->fresh()->status,
        );
    }
}
