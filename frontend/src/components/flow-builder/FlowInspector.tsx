import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { FlowNode } from '@/types/models';

import { getBuilderNodeDefinition } from './nodeRegistry';
import { IvrNodeEditor } from './nodes/IvrNodeEditor';
import { RingGroupNodeEditor } from './nodes/RingGroupNodeEditor';

interface TeamOption {
    id: string;
    name: string;
}

function QueueNodeEditor({
    name,
    config,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: Record<string, unknown>;
    onNameChange: (value: string) => void;
    onConfigChange: (config: Record<string, unknown>) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="queue-name">Node Name</Label>
                <Input id="queue-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="queue-label">Queue Name</Label>
                    <Input
                        id="queue-label"
                        value={String(config.queue_name ?? '')}
                        onChange={(event) => onConfigChange({ ...config, queue_name: event.target.value })}
                        placeholder="Support Queue"
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="queue-id">Queue ID</Label>
                    <Input
                        id="queue-id"
                        value={String(config.queue_id ?? '')}
                        onChange={(event) => onConfigChange({ ...config, queue_id: event.target.value })}
                        placeholder="queue-uuid"
                    />
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label>Strategy</Label>
                    <Select
                        value={String(config.strategy ?? 'round_robin')}
                        onValueChange={(value) => onConfigChange({ ...config, strategy: value })}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select strategy" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="round_robin">Round Robin</SelectItem>
                            <SelectItem value="longest_idle">Longest Idle</SelectItem>
                            <SelectItem value="skills_based">Skills Based</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="queue-wait">Max Wait (seconds)</Label>
                    <Input
                        id="queue-wait"
                        type="number"
                        min="1"
                        value={String(config.max_wait_seconds ?? 120)}
                        onChange={(event) => onConfigChange({ ...config, max_wait_seconds: Number(event.target.value) })}
                    />
                </div>
            </div>
        </div>
    );
}

function TransferNodeEditor({
    name,
    config,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: Record<string, unknown>;
    onNameChange: (value: string) => void;
    onConfigChange: (config: Record<string, unknown>) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="transfer-name">Node Name</Label>
                <Input id="transfer-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label>Destination Type</Label>
                    <Select
                        value={String(config.destination_type ?? 'extension')}
                        onValueChange={(value) => onConfigChange({ ...config, destination_type: value })}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select destination type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="extension">Extension</SelectItem>
                            <SelectItem value="ring_group">Ring Group</SelectItem>
                            <SelectItem value="queue">Queue</SelectItem>
                            <SelectItem value="external_number">External Number</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="transfer-value">Destination</Label>
                    <Input
                        id="transfer-value"
                        value={String(config.destination_value ?? '')}
                        onChange={(event) => onConfigChange({ ...config, destination_value: event.target.value })}
                        placeholder="1001 or +15551234567"
                    />
                </div>
            </div>
            <div className="space-y-2">
                <Label>Caller ID Mode</Label>
                <Select
                    value={String(config.caller_id_mode ?? 'inherit')}
                    onValueChange={(value) => onConfigChange({ ...config, caller_id_mode: value })}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select caller ID mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="inherit">Inherit</SelectItem>
                        <SelectItem value="preserve_original">Preserve Original</SelectItem>
                        <SelectItem value="use_system_default">Use System Default</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}

function TerminalNodeEditor({
    name,
    config,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: Record<string, unknown>;
    onNameChange: (value: string) => void;
    onConfigChange: (config: Record<string, unknown>) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="terminal-name">Node Name</Label>
                <Input id="terminal-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label>Outcome</Label>
                    <Select
                        value={String(config.outcome ?? 'hangup')}
                        onValueChange={(value) => onConfigChange({ ...config, outcome: value })}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select outcome" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="hangup">Hangup</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="terminal-reason">Reason</Label>
                    <Input
                        id="terminal-reason"
                        value={String(config.reason ?? '')}
                        onChange={(event) => onConfigChange({ ...config, reason: event.target.value })}
                        placeholder="Caller disconnected"
                    />
                </div>
            </div>
            <p className="text-xs text-muted-foreground">
                Terminal nodes close the visible call journey. Keep them simple and explicit.
            </p>
        </div>
    );
}

export function FlowInspector({
    selectedNode,
    teamOptions,
    onChange,
}: {
    selectedNode: FlowNode | null;
    teamOptions: TeamOption[];
    onChange: (node: FlowNode) => void;
}) {
    if (!selectedNode) {
        return (
            <div className="rounded-2xl border border-dashed border-border/70 bg-background/70 p-5 text-sm text-muted-foreground">
                Select a node to configure its behavior.
            </div>
        );
    }

    const definition = getBuilderNodeDefinition(selectedNode.type);
    const updateNode = (patch: Partial<FlowNode>) => onChange({ ...selectedNode, ...patch });
    const config = (selectedNode.config as Record<string, unknown>) ?? {};

    return (
        <div className="space-y-4">
            <div className="rounded-2xl border border-border/70 bg-background/80 p-4 shadow-sm">
                <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-foreground">{definition?.title ?? 'Node'}</p>
                        <p className="text-sm text-muted-foreground">{definition?.description ?? 'Configure node behavior.'}</p>
                    </div>
                    {definition && (
                        <div className={`rounded-xl border p-2 ${definition.accentClassName}`}>
                            <definition.icon className="size-4" />
                        </div>
                    )}
                </div>
            </div>

            {selectedNode.type === 'start' && (
                <div className="space-y-2">
                    <Label htmlFor="start-name">Node Name</Label>
                    <Input
                        id="start-name"
                        value={selectedNode.name ?? 'Call Start'}
                        onChange={(event) => updateNode({ name: event.target.value })}
                    />
                </div>
            )}

            {selectedNode.type === 'ivr' && (
                <IvrNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {selectedNode.type === 'ring_group' && (
                <RingGroupNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    teamOptions={teamOptions}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {selectedNode.type === 'queue' && (
                <QueueNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {selectedNode.type === 'transfer' && (
                <TransferNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {selectedNode.type === 'terminal' && (
                <TerminalNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}
        </div>
    );
}
