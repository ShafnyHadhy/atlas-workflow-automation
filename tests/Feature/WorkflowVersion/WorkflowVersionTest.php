<?php

namespace Tests\Feature;

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WorkflowVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_belongs_to_a_workflow(): void
    {
        $workflow = Workflow::factory()->create();

        $version = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
        ]);

        $this->assertTrue(
            $version->workflow->is($workflow)
        );
    }

    public function test_workflow_can_have_multiple_versions(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Archived,
        ]);

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 2,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $this->assertCount(2, $workflow->versions);
    }

     public function test_version_number_must_be_unique_within_a_workflow(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
        ]);
    }

    public function test_same_version_number_can_exist_on_different_workflows(): void
    {
        $workflowA = Workflow::factory()->create();
        $workflowB = Workflow::factory()->create();

        $versionA = WorkflowVersion::factory()->create([
            'workflow_id' => $workflowA->id,
            'version_number' => 1,
        ]);

        $versionB = WorkflowVersion::factory()->create([
            'workflow_id' => $workflowB->id,
            'version_number' => 1,
        ]);

        $this->assertSame(1, $versionA->version_number);
        $this->assertSame(1, $versionB->version_number);
        $this->assertNotSame(
            $versionA->workflow_id,
            $versionB->workflow_id
        );
    }

    public function test_workflow_cannot_have_two_draft_versions(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $this->expectException(QueryException::class);

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 2,
            'status' => WorkflowVersionStatus::Draft,
        ]);
    }

    public function test_workflow_cannot_have_two_published_versions(): void
    {
        $workflow = Workflow::factory()->create();

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Published,
        ]);

        $this->expectException(QueryException::class);

        WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 2,
            'status' => WorkflowVersionStatus::Published,
        ]);
    }

    public function test_workflow_can_have_one_draft_and_one_published_version(): void
    {
        $workflow = Workflow::factory()->create();

        $draft = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Draft,
        ]);

        $published = WorkflowVersion::factory()->create([
            'workflow_id' => $workflow->id,
            'version_number' => 2,
            'status' => WorkflowVersionStatus::Published,
        ]);

        $this->assertSame(
            WorkflowVersionStatus::Draft,
            $draft->status
        );

        $this->assertSame(
            WorkflowVersionStatus::Published,
            $published->status
        );

        $this->assertCount(2, $workflow->versions);
    }

    public function test_draft_version_can_have_null_published_at(): void
    {
        $version = WorkflowVersion::factory()->create([
            'status' => WorkflowVersionStatus::Draft,
            'published_at' => null,
        ]);

        $this->assertNull($version->published_at);
    }
}
