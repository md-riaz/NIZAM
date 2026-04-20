import type { DragEvent } from 'react';

import { Button } from '@/components/ui/button';

import { builderNodeDefinitions, type BuilderNodeType } from './nodeRegistry';

const FLOW_NODE_DRAG_MIME = 'application/x-flow-node-type';

export function readDraggedNodeType(event: Pick<DragEvent, 'dataTransfer'>) {
    return event.dataTransfer.getData(FLOW_NODE_DRAG_MIME) as BuilderNodeType | '';
}

function handlePaletteDragStart(event: DragEvent<HTMLButtonElement>, type: BuilderNodeType) {
    event.dataTransfer.setData(FLOW_NODE_DRAG_MIME, type);
    event.dataTransfer.effectAllowed = 'move';
}

const paletteGroups = [
    {
        label: 'Entry',
        items: builderNodeDefinitions.filter((item) => item.type === 'start'),
    },
    {
        label: 'Routing',
        items: builderNodeDefinitions.filter((item) => ['ivr', 'ring_group', 'queue', 'transfer'].includes(item.type)),
    },
    {
        label: 'End',
        items: builderNodeDefinitions.filter((item) => item.type === 'terminal'),
    },
];

export type { BuilderNodeType };

export function FlowNodePalette({
    onAddNode,
    onDragNodeStart,
}: {
    onAddNode: (type: BuilderNodeType) => void;
    onDragNodeStart: (type: BuilderNodeType) => void;
}) {
    return (
        <div className="space-y-4">
            <div>
                <h3 className="text-sm font-semibold">Node Library</h3>
                <p className="text-xs text-muted-foreground">
                    Add routing blocks used in real company call flows.
                </p>
            </div>

            {paletteGroups.map((group) => (
                <div key={group.label} className="space-y-2">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                        {group.label}
                    </p>
                    <div className="space-y-2">
                        {group.items.map((item) => (
                            <Button
                                key={item.type}
                                type="button"
                                variant="outline"
                                draggable
                                className="h-auto w-full cursor-grab items-start justify-start gap-3 overflow-hidden rounded-xl border-border/70 bg-background px-3 py-3 text-left shadow-sm transition hover:bg-accent/50 active:cursor-grabbing"
                                onClick={() => onAddNode(item.type)}
                                onDragStart={(event) => {
                                    onDragNodeStart(item.type);
                                    handlePaletteDragStart(event, item.type);
                                }}
                            >
                                <div className={`shrink-0 rounded-lg border px-2 py-2 ${item.accentClassName}`}>
                                    <item.icon className="size-4 shrink-0" />
                                </div>
                                <div className="min-w-0 flex-1 space-y-1 overflow-hidden">
                                    <p className="truncate text-sm font-medium text-foreground">{item.label}</p>
                                    <p className="break-words text-xs leading-5 text-muted-foreground">{item.description}</p>
                                </div>
                            </Button>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
