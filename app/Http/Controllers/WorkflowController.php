<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\UpdateWorkflowDraft;
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

    public function show( Workspace $workspace, Workflow $workflow ): Response
    {
        $workflow->load([
            'draftVersion.nodes',
            'draftVersion.edges',
        ]);

        return Inertia::render('workflows/show', [
            'workspace' => $workspace,
            'workflow' => (new WorkflowResource($workflow))->resolve(),
        ]);
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
}
