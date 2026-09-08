import { router, usePage } from '@inertiajs/react';
import type { Workspace } from '@/types/workspace';

export function WorkspaceSwitcher() {
    const { currentWorkspace, availableWorkspaces } = usePage().props;

    return (
        <div>
            <p>Current workspace:</p>

            <strong>
                {currentWorkspace?.name ?? 'Select a workspace'}
            </strong>

            <div>
                {availableWorkspaces.map((workspace: Workspace) => (
                    <button
                        key={workspace.id}
                        type="button"
                        disabled={workspace.id === currentWorkspace?.id}
                        onClick={() =>
                            router.post(
                                `/workspaces/${workspace.slug}/select`,
                            )
                        }
                    >
                        {workspace.name}
                    </button>
                ))}
            </div>
        </div>
    );
}
