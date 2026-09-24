import { Handle, Position } from '@xyflow/react';

type Props = {
    data: {
        label?: string;
    };
};

export default function EndNode({ data }: Props) {
    return (
        <div className="min-w-48 rounded-lg border bg-background p-4 shadow-sm">
            <Handle
                type="target"
                position={Position.Top}
            />

            <div className="text-sm font-medium">
                End
            </div>

            <div className="mt-1 text-sm text-muted-foreground">
                {data.label ?? 'End'}
            </div>
        </div>
    );
}
