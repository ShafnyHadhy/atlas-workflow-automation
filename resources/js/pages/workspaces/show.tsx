import { Head } from '@inertiajs/react';

type Workspace = {
    id: number;
    name: string;
    slug: string;
};

type Props = {
    workspace: Workspace;
};

export default function Show({ workspace }: Props) {
    return (
        <>
            <Head title={workspace.name} />

            <div>
                <h1>{workspace.name}</h1>
                <p>{workspace.slug}</p>
            </div>
        </>
    );
}
