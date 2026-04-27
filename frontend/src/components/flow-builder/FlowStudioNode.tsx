import { memo } from 'react';
import { Handle, Position, type NodeProps } from '@xyflow/react';

import { cn } from '@/lib/utils';
import type { FlowNode } from '@/types/models';

import { builderSubtitleForNode, getBuilderNodeDefinition } from './nodeRegistry';

function truncate(value: string, length = 52) {
    return value.length > length ? `${value.slice(0, length - 1)}…` : value;
}

export const FlowStudioNode = memo(({ data, selected }: NodeProps<{ flowNode: FlowNode }>) => {
    const flowNode = data.flowNode;
    const definition = getBuilderNodeDefinition(flowNode.type);
    const subtitle = truncate(builderSubtitleForNode(flowNode));

    return (
        <div
            className={cn(
                'min-w-[260px] rounded-2xl border border-border/70 bg-background/95 px-4 py-4 text-foreground shadow-[var(--communications-shadow-ambient)] transition-all',
                selected && 'border-primary/50 ring-2 ring-primary/20 shadow-lg',
            )}
        >
            <Handle
                type="target"
                position={Position.Left}
                className="!-left-3 !z-20 !h-5 !w-5 !border-[3px] !border-background !bg-primary shadow-sm transition-transform hover:scale-110"
            />

            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-2">
                    <div className={cn('inline-flex max-w-full items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold', definition?.accentClassName)}>
                        <span className="truncate">{definition?.label ?? flowNode.type}</span>
                    </div>
                    <p className="break-words text-sm font-semibold leading-5 text-foreground">
                        {flowNode.name ?? definition?.defaultName ?? 'Unnamed node'}
                    </p>
                </div>
                {definition && (
                    <div className={cn('shrink-0 rounded-xl border p-2', definition.accentClassName)}>
                        <definition.icon className="size-4" />
                    </div>
                )}
            </div>

            <p className="break-words text-xs leading-5 text-muted-foreground">{subtitle}</p>

            <Handle
                type="source"
                position={Position.Right}
                className="!-right-3 !z-20 !h-5 !w-5 !border-[3px] !border-background !bg-primary shadow-sm transition-transform hover:scale-110"
            />
        </div>
    );
});

FlowStudioNode.displayName = 'FlowStudioNode';
