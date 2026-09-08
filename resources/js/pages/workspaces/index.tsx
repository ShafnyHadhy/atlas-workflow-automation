import { Workspace } from "@/types/workspace";
import { Head, router } from "@inertiajs/react";

type Props = {
    workspaces: Workspace[];
    activeWorkspaceId: number | null;
};

export default function Index({ workspaces, activeWorkspaceId }: Props){
    return(
        <>
            <Head title='Workspaces'/>

             <div className="ml-20">
                <h1>Workspaces</h1>

                {workspaces.map((workspace) => (
                    <div key={workspace.id} className="my-5">
                        <h2 className='text-xl'>{workspace.name}</h2>
                        <p className='text-xs'>{workspace.slug}</p>

                        {workspace.id === activeWorkspaceId ? (
                            <p className="text-green-400">Active workspace</p>
                        ) : (
                            <button
                                type="button"
                                onClick={() => router.post(`/workspaces/${workspace.slug}/select`)}
                            >
                                Select
                            </button>
                        )}
                    </div>
                ))}
             </div>
        </>
    )
}
