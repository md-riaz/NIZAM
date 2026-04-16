import { Button } from '@/components/ui/button';

import { builderNodeDefinitions, type BuilderNodeType } from './nodeRegistry';

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
}: {
    onAddNode: (type: BuilderNodeType) => void;
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
                                className="h-auto w-full justify-start gap-3 rounded-xl border-border/70 bg-background px-3 py-3 text-left shadow-sm transition hover:bg-accent/50"
                                onClick={() => onAddNode(item.type)}
                            >
                                <div className={`rounded-lg border px-2 py-2 ${item.accentClassName}`}>
                                    <item.icon className="size-4 shrink-0" />
                                </div>
                                <div className="min-w-0 space-y-1">
                                    <p className="text-sm font-medium text-foreground">{item.label}</p>
                                    <p className="text-xs leading-5 text-muted-foreground">{item.description}</p>
                                </div>
                            </Button>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
