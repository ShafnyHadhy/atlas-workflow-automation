<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\CreateWorkflowDraft;
use App\Actions\Workflows\PublishWorkflowVersion;
use App\Actions\Workflows\UpdateWorkflowDraft;
use App\Enums\WorkflowVersionStatus;
use App\Http\Requests\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Workflows\UpdateWorkflowDraftRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function store(
        StoreWorkflowRequest $request,
        Workspace $workspace,
        CreateWorkflow $createWorkflow
    ): RedirectResponse
    {
        $workflow = $createWorkflow->execute(
            workspace: $workspace,
            name: $request->string('name')->toString(),
            description: $request->input('description'),
        );

        return redirect()->route(
            'workspaces.workflows.show',
            [
                'workspace' => $workspace,
                'workflow' => $workflow,
            ]
        );
    }

    public function show(
        Workspace $workspace,
        Workflow $workflow,
    ): Response {

        $workflow->load([
            'draftVersion.nodes',
            'draftVersion.edges',
            'versions',
        ]);

        // Prefer the active draft when one exists.
        $selectedVersion = $workflow->draftVersion;

        // Otherwise, display the currently published version.
        if (! $selectedVersion) {
            $selectedVersion = $workflow->versions()
                ->where(
                    'status',
                    WorkflowVersionStatus::Published->value
                )
                ->with(['nodes', 'edges'])
                ->first();
        }

        return $this->renderWorkflow(
            $workspace,
            $workflow,
            $selectedVersion,
        );
    }

    public function update(
        UpdateWorkflowDraftRequest $request,
        Workspace $workspace,
        Workflow $workflow,
        WorkflowVersion $version,
        UpdateWorkflowDraft $updateWorkflowDraft,
    ): RedirectResponse {

        $updateWorkflowDraft->execute(
            version: $version,
            nodes: $request->validated('nodes'),
            edges: $request->validated('edges')
        );

        return redirect()->back();
    }

    public function createDraft(
        Workspace $workspace,
        Workflow $workflow,
        CreateWorkflowDraft $createWorkflowDraft,
    ): RedirectResponse {
        $createWorkflowDraft->execute($workflow);

        return redirect()->route(
            'workspaces.workflows.show',
            [
                'workspace' => $workspace,
                'workflow' => $workflow,
            ]
        );
    }

    public function publish(
        Workspace $workspace,
        Workflow $workflow,
        WorkflowVersion $version,
        PublishWorkflowVersion $publishWorkflowVersion,
    ): RedirectResponse {
        $publishWorkflowVersion->execute($version);

        return redirect()->route(
            'workspaces.workflows.show',
            [
                'workspace' => $workspace,
                'workflow' => $workflow,
            ]
        );
    }

    public function showVersion(
        Workspace $workspace,
        Workflow $workflow,
        WorkflowVersion $version,
    ): Response {
        $workflow->load([
            'draftVersion.nodes',
            'draftVersion.edges',
            'versions',
        ]);

        // Load the requested version's graph for the read-only canvas.
        $version->load(['nodes', 'edges']);

        return $this->renderWorkflow(
            $workspace,
            $workflow,
            $version,
        );
    }

    private function renderWorkflow(
        Workspace $workspace,
        Workflow $workflow,
        ?WorkflowVersion $version,
    ): Response {
        return Inertia::render('workflows/show', [
            'workspace' => $workspace,
            'workflow' => (new WorkflowResource($workflow))->resolve(),
            'selected_version' => $version ? [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'status' => $version->status->value,
                'published_at' => $version->published_at?->toISOString(),

                'nodes' => $version->nodes->map(fn ($node) => [
                    'id' => $node->id,
                    'node_key' => $node->node_key,
                    'type' => $node->type->value,
                    'configuration' => $node->configuration,
                    'position' => $node->position,
                ])->values(),

                'edges' => $version->edges->map(fn ($edge) => [
                    'id' => $edge->id,
                    'source_node_key' => $edge->source_node_key,
                    'target_node_key' => $edge->target_node_key,
                    'condition' => $edge->condition,
                ])->values(),
            ] : null,
        ]);
    }
}
