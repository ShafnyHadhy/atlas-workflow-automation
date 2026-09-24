import { Handle, Position } from '@xyflow/react';

type Props = {
    data: {
        label?: string;
    };
};

export default function TriggerNode({ data }: Props) {
    return (
        <div className="min-w-48 rounded-lg border bg-background p-4 shadow-sm">
            <div className="text-sm font-medium">
                Trigger
            </div>

            <div className="mt-1 text-sm text-muted-foreground">
                {data.label ?? 'Trigger'}
            </div>

            <Handle
                type="source"
                position={Position.Bottom}
            />
        </div>
    );
}
