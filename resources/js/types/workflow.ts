export type WorkflowVersion = {
    id: number;
    version_number: number;
    status: 'draft' | 'published' | 'archived';
};

export type Workflow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    draft_version: WorkflowVersion | null;
};
