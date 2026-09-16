import { Head } from "@inertiajs/react";

type WorkflowNode = {
    id: number;
    node_key: string;
    type: 'trigger' | 'action' | 'end';
    configuration: Record<string, unknown>;
    position: {
        x: number;
        y: number;
    };
};

type WorkflowEdge = {
    id: number;
    source_node_key: string;
    target_node_key: string;
    condition: Record<string, unknown> | null;
};

type DraftVersion = {
    id: number,
    version_number: number;
    status: 'draft' | 'published' | 'archived';
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
}

type Workflow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    draft_version: DraftVersion | null;
};

type Props = {
    workspace: {
        id: number,
        name: string,
        slug: string,
    };
    workflow: Workflow;
};

export default function Show({ workflow }: Props) {
    return(
        <>
            <Head title={workflow.name}/>

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {workflow.name}
                    </h1>

                    {workflow.description && (
                        <p className="mt-2 text-muted-foreground">
                            {workflow.description}
                        </p>
                    )}

                    <p className="mt-2 text-sm text-muted-foreground">
                        Slug: {workflow.slug}
                    </p>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-lg font-medium">
                        Workflow version
                    </h2>

                     {workflow.draft_version ? (
                        <div className="mt-3 space-y-2">
                            <p>
                                Version:{' '}
                                {workflow.draft_version.version_number}
                            </p>

                            <p>
                                Status:{' '}
                                {workflow.draft_version.status}
                            </p>

                            <p>
                                Nodes:{' '}
                                {workflow.draft_version.nodes.length}
                            </p>

                            <p>
                                Edges:{' '}
                                {workflow.draft_version.edges.length}
                            </p>
                        </div>
                    ) : (
                        <p className="mt-3 text-muted-foreground">
                            This workflow does not have a draft version.
                        </p>
                    )}
                </div>

                <div className="flex min-h-100 items-center justify-center rounded-xl border border-dashed">
                    <p className="text-muted-foreground">
                        Workflow canvas will appear here.
                    </p>
                </div>
            </div>
        </>
    );
}
