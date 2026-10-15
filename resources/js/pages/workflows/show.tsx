import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import {
    addEdge,
    applyEdgeChanges,
    applyNodeChanges,
    Background,
    Controls,
    ReactFlow,
    type Connection,
    type Edge,
    type EdgeChange,
    type Node,
    type NodeChange,
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
    position: { x: number; y: number };
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

type EditorNode = Node<{ label: string }>;

const nodeTypes = {
    trigger: TriggerNode,
    action: ActionNode,
    end: EndNode,
};

export default function Show({ workspace, workflow }: Props) {
    const draftVersion = workflow.draft_version;

    const [nodes, setNodes] = useState<EditorNode[]>(() =>
        draftVersion?.nodes.map((node) => ({
            id: node.node_key,
            type: node.type,
            position: node.position,
            data: {
                label: getNodeLabel(node),
            },
        })) ?? [],
    );

    const [edges, setEdges] = useState<Edge[]>(() =>
        draftVersion?.edges.map((edge) => ({
            id: String(edge.id),
            source: edge.source_node_key,
            target: edge.target_node_key,
        })) ?? [],
    );

    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [validationErrors, setValidationErrors] =
        useState<Record<string, string>>({});

    const currentSnapshot = useMemo(
        () => JSON.stringify(serializeGraph(nodes, edges)),
        [nodes, edges],
    );

    const [savedSnapshot, setSavedSnapshot] =
        useState(currentSnapshot);

    const isDirty = currentSnapshot !== savedSnapshot;

    const onNodesChange = useCallback(
        (changes: NodeChange<EditorNode>[]) => {
            setNodes((currentNodes) =>
                applyNodeChanges(changes, currentNodes),
            );
        },
        [],
    );

    const onEdgesChange = useCallback(
        (changes: EdgeChange[]) => {
            setEdges((currentEdges) =>
                applyEdgeChanges(changes, currentEdges),
            );
        },
        [],
    );

    const onConnect = useCallback((connection: Connection) => {
        setEdges((currentEdges) =>
            addEdge(connection, currentEdges),
        );
    }, []);

    const onNodesDelete = useCallback((deletedNodes: Node[]) => {
        const deletedIds = new Set(
            deletedNodes.map((node) => node.id),
        );

        setEdges((currentEdges) =>
            currentEdges.filter(
                (edge) =>
                    !deletedIds.has(edge.source) &&
                    !deletedIds.has(edge.target),
            ),
        );
    }, []);

    function saveDraft() {
        if (!draftVersion || !isDirty || isSaving) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);
        setValidationErrors({});

        const snapshotBeingSaved = currentSnapshot;
        const graph = serializeGraph(nodes, edges);

        router.put(
            `/workspaces/${workspace.slug}/workflows/${workflow.slug}/versions/${draftVersion.id}`,
            graph,
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSavedSnapshot(snapshotBeingSaved);
                },

                onError: (errors) => {
                    setValidationErrors(
                        errors as Record<string, string>,
                    );
                    setSaveError(
                        'Atlas could not save this graph. Review the errors and try again.',
                    );
                },

                onFinish: () => {
                    setIsSaving(false);
                },
            },
        );
    }

    return (
        <>
            <Head title={workflow.name} />

            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
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
                            Workspace: {workspace.name}
                            {' · '}
                            Version {draftVersion?.version_number ?? '—'}
                        </p>
                    </div>

                    {draftVersion?.status === 'draft' && (
                        <div className="flex items-center gap-3">
                            <span className="text-sm text-muted-foreground">
                                {isSaving
                                    ? 'Saving…'
                                    : isDirty
                                      ? 'Unsaved changes'
                                      : 'All changes saved'}
                            </span>

                            <button
                                type="button"
                                onClick={saveDraft}
                                disabled={!isDirty || isSaving}
                                className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isSaving ? 'Saving…' : 'Save Draft'}
                            </button>
                        </div>
                    )}
                </div>

                {draftVersion && (
                    <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>
                            Status: {draftVersion.status}
                        </span>
                        <span>Nodes: {nodes.length}</span>
                        <span>Edges: {edges.length}</span>
                    </div>
                )}

                {saveError && (
                    <div
                        role="alert"
                        className="rounded-md border border-destructive p-3 text-sm text-destructive"
                    >
                        <p>{saveError}</p>

                        {Object.entries(validationErrors).map(
                            ([field, message]) => (
                                <p key={field} className="mt-1">
                                    {field}: {message}
                                </p>
                            ),
                        )}
                    </div>
                )}

                {draftVersion ? (
                    <div className="h-150 overflow-hidden rounded-xl border">
                        <ReactFlow
                            nodes={nodes}
                            edges={edges}
                            nodeTypes={nodeTypes}
                            onNodesChange={onNodesChange}
                            onEdgesChange={onEdgesChange}
                            onConnect={onConnect}
                            onNodesDelete={onNodesDelete}
                            fitView
                            deleteKeyCode={['Backspace', 'Delete']}
                        >
                            <Background />
                            <Controls />
                        </ReactFlow>
                    </div>
                ) : (
                    <div className="rounded-xl border p-6">
                        <h2 className="font-medium">
                            No editable draft
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            This workflow has no active draft version.
                            Create a new draft before editing it.
                        </p>
                    </div>
                )}
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

function serializeGraph(nodes: EditorNode[], edges: Edge[]) {
    return {
        nodes: nodes.map((node) => ({
            node_key: node.id,
            type: node.type,
            configuration: {
                label: node.data.label,
            },
            position: node.position,
        })),

        edges: edges.map((edge) => ({
            source_node_key: edge.source,
            target_node_key: edge.target,
            condition: null,
        })),
    };
}
