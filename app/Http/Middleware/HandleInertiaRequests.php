<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            'currentWorkspace' => $this->resolveCurrentWorkspace($request),

            'availableWorkspaces' => $this->resolveAvailableWorkspaces($request),
        ];
    }

    private function resolveCurrentWorkspace(Request $request): ?Workspace
    {
        $workspace = $request->route('workspace');

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $activeWorkspaceId = $request->session()->get('active_workspace_id');

        if ($activeWorkspaceId === null || ! $request->user()) {
            return null;
        }

        $workspace = $request->user()
            ->workspaces()
            ->where('workspaces.id', $activeWorkspaceId)
            ->first();

        if ($workspace === null) {
            $request->session()->forget('active_workspace_id');
        }

        return $workspace;
    }

    private function resolveAvailableWorkspaces(Request $request): Collection
    {
        if (! $request->user()) {
            return collect();
        }

        return $request->user()
            ->workspaces()
            ->get();
    }
}
