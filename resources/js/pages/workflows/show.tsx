import { Head } from '@inertiajs/react';
import {
    Background,
    Controls,
    ReactFlow,
    type Edge,
    type Node,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import TriggerNode from '@/components/workflow/trigger-node';
import ActionNode from '@/components/workflow/action-node';
import EndNode from '@/components/workflow/end-node';

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
    id: number;
    version_number: number;
    status: 'draft' | 'published' | 'archived';
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
};

type Workflow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    draft_version: DraftVersion | null;
};

type Props = {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    workflow: Workflow;
};

const nodeTypes = {
    trigger: TriggerNode,
    action: ActionNode,
    end: EndNode,
};

export default function Show({ workflow }: Props) {
    const draftVersion = workflow.draft_version;

    const nodes: Node[] =
        draftVersion?.nodes.map((node) => ({
            id: node.node_key,
            type: node.type,
            position: node.position,
            data: {
                label: getNodeLabel(node),
            },
        })) ?? [];

    const edges: Edge[] =
        draftVersion?.edges.map((edge) => ({
            id: String(edge.id),
            source: edge.source_node_key,
            target: edge.target_node_key,
        })) ?? [];

    return (
        <>
            <Head title={workflow.name} />

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

                {draftVersion ? (
                    <div className="rounded-xl border p-4">
                        <h2 className="text-lg font-medium">
                            Workflow version
                        </h2>

                        <div className="mt-3 space-y-2">
                            <p>
                                Version:{' '}
                                {draftVersion.version_number}
                            </p>

                            <p>
                                Status:{' '}
                                {draftVersion.status}
                            </p>

                            <p>
                                Nodes:{' '}
                                {draftVersion.nodes.length}
                            </p>

                            <p>
                                Edges:{' '}
                                {draftVersion.edges.length}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground">
                            This workflow does not have a draft version.
                        </p>
                    </div>
                )}

                <div className="h-150 overflow-hidden rounded-xl border">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={nodeTypes}
                        fitView
                        nodesDraggable={false}
                        nodesConnectable={false}
                    >
                        <Background />
                        <Controls />
                    </ReactFlow>
                </div>
            </div>
        </>
    );
}

function getNodeLabel(node: WorkflowNode): string {
    if (typeof node.configuration.label === 'string') {
        return node.configuration.label;
    }

    return node.type.charAt(0).toUpperCase() + node.type.slice(1);
}
