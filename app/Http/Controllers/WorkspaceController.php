<?php

namespace App\Http\Controllers;

use App\Actions\Workspace\CreateWorkspace;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{

    public function index(Request $request): Response
    {
        $workspaces = $request->user()
            ->workspaces()
            ->get();

        $activeWorkspaceId = $request->session()->get('active_workspace_id');

        if ($activeWorkspaceId !== null) {
            $hasAccess = $request->user()
                ->workspaces()
                ->where('workspaces.id', $activeWorkspaceId)
                ->exists();

            if( !$hasAccess ){
                $request->session()->forget('active_workspace_id');

                $activeWorkspaceId = null;
            }
        }

        return Inertia::render('workspaces/index', [
            'workspaces' => $workspaces,
            'activeWorkspaceId' => $activeWorkspaceId,
        ]);
    }

    public function store( StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $createWorkspace->execute(
            $request->user(),
            $request->validated('name')
        );

        return redirect()->back();
    }

    public function show(Workspace $workspace): Response
    {
        return Inertia::render('workspaces/show', [
            'workspace' => $workspace,
        ]);
    }

    public function select(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        $request->session()->put('active_workspace_id', $workspace->id);

        return redirect()->route('workspaces.show', $workspace);
    }
}
