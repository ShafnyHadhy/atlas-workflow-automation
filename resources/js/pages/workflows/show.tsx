import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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

type VersionStatus = 'draft' | 'published' | 'archived';

type VersionSummary = {
    id: number;
    version_number: number;
    status: VersionStatus;
    published_at: string | null;
};

type DraftVersion = VersionSummary & {
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
};

type SelectedVersion = VersionSummary & {
    nodes: WorkflowNode[];
    edges: WorkflowEdge[];
};

type Workflow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    draft_version: DraftVersion | null;
    versions: VersionSummary[];
};

type Props = {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    workflow: Workflow;
    selected_version: SelectedVersion | null;
};

type EditorNode = Node<{ label: string }>;

const nodeTypes = {
    trigger: TriggerNode,
    action: ActionNode,
    end: EndNode,
};

export default function Show({
    workspace,
    workflow,
    selected_version: selectedVersion,
}: Props) {
    const draftVersion = workflow.draft_version;

    // A version is editable only when it is the workflow's active draft.
    const isEditable =
        selectedVersion?.status === 'draft' &&
        selectedVersion.id === draftVersion?.id;

    const [nodes, setNodes] = useState<EditorNode[]>(() =>
        selectedVersion ? mapNodes(selectedVersion.nodes) : [],
    );

    const [edges, setEdges] = useState<Edge[]>(() =>
        selectedVersion ? mapEdges(selectedVersion.edges) : [],
    );

    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [isCreatingDraft, setIsCreatingDraft] = useState(false);

    const [saveError, setSaveError] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);

    const [validationErrors, setValidationErrors] = useState<
        Record<string, string>
    >({});

    const currentSnapshot = useMemo(
        () => JSON.stringify(serializeGraph(nodes, edges)),
        [nodes, edges],
    );

    const [savedSnapshot, setSavedSnapshot] = useState(currentSnapshot);

    const isDirty = currentSnapshot !== savedSnapshot;

    // Inertia can change the selected version without remounting this page.
    // Synchronize the canvas whenever the server supplies another version.
    useEffect(() => {
        const nextNodes = selectedVersion
            ? mapNodes(selectedVersion.nodes)
            : [];

        const nextEdges = selectedVersion
            ? mapEdges(selectedVersion.edges)
            : [];

        setNodes(nextNodes);
        setEdges(nextEdges);

        setSavedSnapshot(
            JSON.stringify(serializeGraph(nextNodes, nextEdges)),
        );

        setSaveError(null);
        setActionError(null);
        setValidationErrors({});
    }, [selectedVersion]);

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
        if (
            !selectedVersion ||
            !isEditable ||
            !isDirty ||
            isSaving
        ) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);
        setActionError(null);
        setValidationErrors({});

        const snapshotBeingSaved = currentSnapshot;
        const graph = serializeGraph(nodes, edges);

        router.put(
            `/workspaces/${workspace.slug}/workflows/${workflow.slug}/versions/${selectedVersion.id}`,
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

    function publishDraft() {
        if (
            !draftVersion ||
            draftVersion.status !== 'draft' ||
            !isEditable ||
            isDirty ||
            isSaving ||
            isPublishing
        ) {
            return;
        }

        setIsPublishing(true);
        setActionError(null);
        setSaveError(null);

        router.post(
            `/workspaces/${workspace.slug}/workflows/${workflow.slug}/versions/${draftVersion.id}/publish`,
            {},
            {
                preserveScroll: true,

                onError: () => {
                    setActionError(
                        'Atlas could not publish this version. Check the workflow graph and try again.',
                    );
                },

                onFinish: () => {
                    setIsPublishing(false);
                },
            },
        );
    }

    function createDraft() {
        if (
            draftVersion ||
            !workflow.versions.some(
                (version) => version.status === 'published',
            ) ||
            isCreatingDraft
        ) {
            return;
        }

        setIsCreatingDraft(true);
        setActionError(null);

        router.post(
            `/workspaces/${workspace.slug}/workflows/${workflow.slug}/drafts`,
            {},
            {
                preserveScroll: true,

                onError: () => {
                    setActionError(
                        'Atlas could not create a new draft. Refresh the page and try again.',
                    );
                },

                onFinish: () => {
                    setIsCreatingDraft(false);
                },
            },
        );
    }

    const hasPublishedVersion = workflow.versions.some(
        (version) => version.status === 'published',
    );

    const selectedVersionUrl = (versionId: number) =>
        `/workspaces/${workspace.slug}/workflows/${workflow.slug}/versions/${versionId}`;

    return (
        <>
            <Head title={`${workflow.name} · Atlas`} />

            <div className="flex flex-1 flex-col gap-6 p-6">
                {/* Workflow header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link
                            href={`/workspaces/${workspace.slug}`}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            {workspace.name}
                        </Link>

                        <h1 className="mt-2 text-2xl font-semibold">
                            {workflow.name}
                        </h1>

                        {workflow.description && (
                            <p className="mt-2 text-muted-foreground">
                                {workflow.description}
                            </p>
                        )}

                        <p className="mt-2 text-sm text-muted-foreground">
                            {selectedVersion
                                ? `Viewing version ${selectedVersion.version_number}`
                                : 'No version selected'}
                            {' · '}
                            {selectedVersion?.status ?? 'Unavailable'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {isEditable && (
                            <>
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
                                    disabled={
                                        !isDirty ||
                                        isSaving ||
                                        isPublishing
                                    }
                                    className="rounded-md border px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isSaving ? 'Saving…' : 'Save Draft'}
                                </button>

                                <button
                                    type="button"
                                    onClick={publishDraft}
                                    disabled={
                                        isDirty ||
                                        isSaving ||
                                        isPublishing
                                    }
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isPublishing
                                        ? 'Publishing…'
                                        : 'Publish Draft'}
                                </button>
                            </>
                        )}

                        {!draftVersion && hasPublishedVersion && (
                            <button
                                type="button"
                                onClick={createDraft}
                                disabled={isCreatingDraft}
                                className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isCreatingDraft
                                    ? 'Creating…'
                                    : 'Create New Draft'}
                            </button>
                        )}

                        {draftVersion &&
                            selectedVersion?.id !== draftVersion.id && (
                                <Link
                                    href={selectedVersionUrl(draftVersion.id)}
                                    className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted"
                                >
                                    Open Draft V{draftVersion.version_number}
                                </Link>
                            )}
                    </div>
                </div>

                {/* Version information */}
                {selectedVersion && (
                    <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>
                            Status:{' '}
                            <strong className="font-medium text-foreground">
                                {selectedVersion.status}
                            </strong>
                        </span>

                        <span>Nodes: {nodes.length}</span>
                        <span>Edges: {edges.length}</span>

                        {selectedVersion.published_at && (
                            <span>
                                Published:{' '}
                                {new Date(
                                    selectedVersion.published_at,
                                ).toLocaleString()}
                            </span>
                        )}
                    </div>
                )}

                {isEditable && (
                    <p className="text-sm text-muted-foreground">
                        Draft versions can be edited. Publishing validates
                        the graph and makes this version immutable.
                    </p>
                )}

                {selectedVersion &&
                    selectedVersion.status !== 'draft' && (
                        <p className="text-sm text-muted-foreground">
                            This version is read-only. Create a new draft
                            to make changes.
                        </p>
                    )}

                {/* Errors */}
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

                {actionError && (
                    <div
                        role="alert"
                        className="rounded-md border border-destructive p-3 text-sm text-destructive"
                    >
                        {actionError}
                    </div>
                )}

                {/* Workflow canvas */}
                {selectedVersion ? (
                    <div className="h-150 overflow-hidden rounded-xl border">
                        <ReactFlow
                            key={selectedVersion.id}
                            nodes={nodes}
                            edges={edges}
                            nodeTypes={nodeTypes}
                            onNodesChange={
                                isEditable && !isSaving
                                    ? onNodesChange
                                    : undefined
                            }
                            onEdgesChange={
                                isEditable && !isSaving
                                    ? onEdgesChange
                                    : undefined
                            }
                            onConnect={
                                isEditable && !isSaving
                                    ? onConnect
                                    : undefined
                            }
                            onNodesDelete={
                                isEditable && !isSaving
                                    ? onNodesDelete
                                    : undefined
                            }
                            nodesDraggable={isEditable && !isSaving}
                            nodesConnectable={isEditable && !isSaving}
                            elementsSelectable={isEditable}
                            deleteKeyCode={
                                isEditable && !isSaving
                                    ? ['Backspace', 'Delete']
                                    : null
                            }
                            fitView
                        >
                            <Background />
                            <Controls />
                        </ReactFlow>
                    </div>
                ) : (
                    <div className="rounded-xl border p-6">
                        <h2 className="font-medium">
                            No workflow version available
                        </h2>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Create a workflow version before editing or
                            viewing its graph.
                        </p>
                    </div>
                )}

                {/* Version history */}
                <section className="space-y-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Version history
                        </h2>

                        <p className="text-sm text-muted-foreground">
                            View previous versions or return to the active
                            draft.
                        </p>
                    </div>

                    {workflow.versions.length > 0 ? (
                        <div className="overflow-hidden rounded-xl border">
                            {workflow.versions.map((version) => {
                                const isSelected =
                                    selectedVersion?.id === version.id;

                                return (
                                    <div
                                        key={version.id}
                                        className="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-b-0"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    Version{' '}
                                                    {version.version_number}
                                                </span>

                                                <span className="rounded-full border px-2 py-0.5 text-xs capitalize">
                                                    {version.status}
                                                </span>

                                                {isSelected && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Currently viewing
                                                    </span>
                                                )}
                                            </div>

                                            {version.published_at && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    Published{' '}
                                                    {new Date(
                                                        version.published_at,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                        </div>

                                        {isSelected ? (
                                            <span className="text-sm text-muted-foreground">
                                                Selected
                                            </span>
                                        ) : (
                                            <Link
                                                href={selectedVersionUrl(
                                                    version.id,
                                                )}
                                                className="text-sm font-medium underline underline-offset-4"
                                            >
                                                View version
                                            </Link>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                            No versions are available yet.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

function mapNodes(nodes: WorkflowNode[]): EditorNode[] {
    return nodes.map((node) => ({
        id: node.node_key,
        type: node.type,
        position: node.position,
        data: {
            label: getNodeLabel(node),
        },
    }));
}

function mapEdges(edges: WorkflowEdge[]): Edge[] {
    return edges.map((edge) => ({
        id: String(edge.id),
        source: edge.source_node_key,
        target: edge.target_node_key,
    }));
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
            type: node.type as WorkflowNode['type'],
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
