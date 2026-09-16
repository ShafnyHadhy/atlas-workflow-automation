import { Head, Link, useForm } from '@inertiajs/react';
import type { Workflow } from '@/types/workflow';
import type { Workspace } from '@/types/workspace'
import { useState } from 'react';

type Props = {
    workspace: Workspace;
    workflows: Workflow[];
};

export default function Show({ workspace, workflows }: Props) {

    const [isCreating, setIsCreating] = useState(false);

    const form = useForm({
        name: '',
        description: '',
    });

    return (
        <>
            <Head title={workspace.name} />

            <div className="p-10">
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold">
                        Workflows
                    </h2>

                    <button
                        type="button"
                        onClick={() => setIsCreating(true)}
                    >
                        Create Workflow
                    </button>
                </div>

                { isCreating && (
                    <form
                        className="mt-4 space-y-4 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();

                            form.post(
                                `/workspaces/${workspace.slug}/workflows`,
                            );
                        }}
                    >
                        <div>
                            <label
                                htmlFor="workflow-name"
                                className="block text-sm font-medium"
                            >
                                Name
                            </label>

                            <input
                                id="workflow-name"
                                type="text"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                className="mt-1 w-full rounded-md border px-3 py-2"
                            />

                            {form.errors.name && (
                                <p className="mt-1 text-sm text-red-500">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="workflow-description"
                                className="block text-sm font-medium"
                            >
                                Description
                            </label>

                            <textarea
                                id="workflow-description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData('description', event.target.value)
                                }
                                className="mt-1 w-full rounded-md border px-3 py-2"
                                rows={3}
                            />

                            {form.errors.description && (
                                <p className="mt-1 text-sm text-red-500">
                                    {form.errors.description}
                                </p>
                            )}
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Creating...'
                                    : 'Create Workflow'}
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    setIsCreating(false);
                                    form.reset();
                                    form.clearErrors();
                                }}
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                {workflows.length === 0 ? (
                    <p className="mt-4 text-muted-foreground">
                        No workflows yet.
                    </p>
                ) : (
                    <div className="mt-4 space-y-3">
                        {workflows.map((workflow) => (
                            <div
                                key={workflow.id}
                                className="rounded-lg border p-4"
                            >
                                <h3 className="font-medium">
                                    {workflow.name}
                                </h3>

                                {workflow.description && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {workflow.description}
                                    </p>
                                )}

                                <p className="mt-2 text-xs text-muted-foreground">
                                    {workflow.draft_version
                                        ? `Version ${workflow.draft_version.version_number} · ${workflow.draft_version.status}`
                                        : 'No draft version'}
                                </p>

                                <Link
                                    href={`/workspaces/${workspace.slug}/workflows/${workflow.slug}`}
                                    className="text-sm underline"
                                >
                                    Open workflow
                                </Link>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>

    );
}
