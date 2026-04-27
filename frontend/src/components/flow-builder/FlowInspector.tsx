import { Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { FlowNode, SystemMedia } from '@/types/models';

import { getBuilderNodeDefinition, normalizeBuilderNodeType } from './nodeRegistry';
import { IvrNodeEditor } from './nodes/IvrNodeEditor';
import { PromptMediaInput } from './nodes/PromptMediaInput';
import { RingGroupNodeEditor } from './nodes/RingGroupNodeEditor';

interface TeamOption {
    id: string;
    name: string;
}

function BusinessHoursNodeEditor({
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
    const scheduleMode = String(config.schedule_mode ?? 'organization_default');

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="business-hours-name">Node Name</Label>
                <Input id="business-hours-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="space-y-2">
                <Label>Schedule Source</Label>
                <Select
                    value={scheduleMode}
                    onValueChange={(value) => onConfigChange({ ...config, schedule_mode: value })}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select schedule source" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="organization_default">Organization default</SelectItem>
                        <SelectItem value="custom">Custom schedule</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            {scheduleMode === 'custom' && (
                <div className="space-y-2">
                    <Label htmlFor="business-hours-schedule-id">Schedule ID</Label>
                    <Input
                        id="business-hours-schedule-id"
                        value={String(config.schedule_id ?? '')}
                        onChange={(event) => onConfigChange({ ...config, schedule_id: event.target.value })}
                        placeholder="schedule-uuid"
                    />
                </div>
            )}
        </div>
    );
}

function MatchValueListEditor({
    values,
    label,
    onChange,
}: {
    values: string[];
    label: string;
    onChange: (values: string[]) => void;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Label>{label}</Label>
                <Button type="button" variant="outline" size="sm" onClick={() => onChange([...values, ''])}>
                    <Plus className="mr-2 size-4" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {values.length === 0 && <p className="text-sm text-muted-foreground">No values configured.</p>}
                {values.map((value, index) => (
                    <div key={`${label}-${index}`} className="flex items-center gap-2">
                        <Input
                            value={value}
                            onChange={(event) => {
                                const nextValues = [...values];
                                nextValues[index] = event.target.value;
                                onChange(nextValues);
                            }}
                            placeholder={label}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange(values.filter((_, currentIndex) => currentIndex !== index))}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
}

function CallerMatchNodeEditor({
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
    const mode = String(config.mode ?? 'exact');
    const numbers = Array.isArray(config.numbers) ? config.numbers.map((value) => String(value ?? '')) : [''];

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="caller-match-name">Node Name</Label>
                <Input id="caller-match-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="space-y-2">
                <Label>Match Mode</Label>
                <Select value={mode} onValueChange={(value) => onConfigChange({ ...config, mode: value })}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="exact">Exact number</SelectItem>
                        <SelectItem value="prefix">Prefix</SelectItem>
                        <SelectItem value="anonymous">Anonymous</SelectItem>
                        <SelectItem value="vip_list">VIP list</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            {(mode === 'exact' || mode === 'prefix' || mode === 'vip_list') && (
                <MatchValueListEditor
                    values={numbers}
                    label={mode === 'prefix' ? 'Prefixes' : 'Numbers'}
                    onChange={(values) => onConfigChange({ ...config, numbers: values })}
                />
            )}
            {mode === 'vip_list' && (
                <div className="space-y-2">
                    <Label htmlFor="caller-match-list-id">List ID</Label>
                    <Input
                        id="caller-match-list-id"
                        value={String(config.list_id ?? '')}
                        onChange={(event) => onConfigChange({ ...config, list_id: event.target.value })}
                        placeholder="vip-list-id"
                    />
                </div>
            )}
        </div>
    );
}

function NumberMatchNodeEditor({
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
    const mode = String(config.mode ?? 'did');
    const numbers = Array.isArray(config.numbers) ? config.numbers.map((value) => String(value ?? '')) : [''];

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="number-match-name">Node Name</Label>
                <Input id="number-match-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="space-y-2">
                <Label>Match Mode</Label>
                <Select value={mode} onValueChange={(value) => onConfigChange({ ...config, mode: value })}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="did">Specific numbers</SelectItem>
                        <SelectItem value="number_group">Number group</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            {mode === 'did' && (
                <MatchValueListEditor
                    values={numbers}
                    label="Numbers"
                    onChange={(values) => onConfigChange({ ...config, numbers: values })}
                />
            )}
            {mode === 'number_group' && (
                <div className="space-y-2">
                    <Label htmlFor="number-match-group-id">Group ID</Label>
                    <Input
                        id="number-match-group-id"
                        value={String(config.group_id ?? '')}
                        onChange={(event) => onConfigChange({ ...config, group_id: event.target.value })}
                        placeholder="number-group-id"
                    />
                </div>
            )}
        </div>
    );
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
                        placeholder="queue-support"
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
                            <SelectItem value="least_recent">Least Recent</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="queue-wait">Max Wait (seconds)</Label>
                    <Input
                        id="queue-wait"
                        type="number"
                        min={0}
                        value={String(config.max_wait_seconds ?? 120)}
                        onChange={(event) => onConfigChange({ ...config, max_wait_seconds: Number(event.target.value) || 0 })}
                    />
                </div>
            </div>
        </div>
    );
}

function PlayMessageNodeEditor({
    name,
    config,
    mediaOptions,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: Record<string, unknown>;
    mediaOptions: SystemMedia[];
    onNameChange: (value: string) => void;
    onConfigChange: (config: Record<string, unknown>) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="play-message-name">Node Name</Label>
                <Input id="play-message-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <PromptMediaInput
                promptId="play-message-prompt"
                mediaId="play-message-media-id"
                promptValue={String(config.prompt ?? config.message ?? '')}
                selectedMediaId={String(config.media_id ?? '')}
                mediaOptions={mediaOptions}
                promptPlaceholder="recordings/123/please_hold.wav or message text"
                onPromptChange={(value) => onConfigChange({ ...config, prompt: value, message: value })}
                onMediaChange={(mediaId, resolvedPrompt) => onConfigChange({
                    ...config,
                    media_id: mediaId,
                    prompt: resolvedPrompt,
                    message: resolvedPrompt,
                })}
            />
            <div className="space-y-2">
                <Label htmlFor="play-message-voice">Voice</Label>
                <Input
                    id="play-message-voice"
                    value={String(config.voice ?? 'default')}
                    onChange={(event) => onConfigChange({ ...config, voice: event.target.value })}
                    placeholder="default"
                />
            </div>
        </div>
    );
}

function VoicemailNodeEditor({
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
                <Label htmlFor="voicemail-name">Node Name</Label>
                <Input id="voicemail-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>
            <div className="space-y-2">
                <Label htmlFor="voicemail-mailbox">Mailbox</Label>
                <Input
                    id="voicemail-mailbox"
                    value={String(config.mailbox ?? '')}
                    onChange={(event) => onConfigChange({ ...config, mailbox: event.target.value })}
                    placeholder="1001"
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="voicemail-greeting">Greeting</Label>
                <Textarea
                    id="voicemail-greeting"
                    value={String(config.greeting ?? '')}
                    onChange={(event) => onConfigChange({ ...config, greeting: event.target.value })}
                    placeholder="Leave a message after the tone."
                />
            </div>
            <div className="flex items-center justify-between rounded-xl border border-border/70 px-3 py-2">
                <div className="space-y-1">
                    <p className="text-sm font-medium">Email notification</p>
                    <p className="text-xs text-muted-foreground">Send new voicemail alerts by email.</p>
                </div>
                <Checkbox
                    checked={Boolean(config.email_notification_enabled)}
                    onCheckedChange={(checked) => onConfigChange({ ...config, email_notification_enabled: Boolean(checked) })}
                />
            </div>
            <div className="flex items-center justify-between rounded-xl border border-border/70 px-3 py-2">
                <div className="space-y-1">
                    <p className="text-sm font-medium">Transcription</p>
                    <p className="text-xs text-muted-foreground">Request voicemail transcription when available.</p>
                </div>
                <Checkbox
                    checked={Boolean(config.transcription_enabled)}
                    onCheckedChange={(checked) => onConfigChange({ ...config, transcription_enabled: Boolean(checked) })}
                />
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
                            <SelectValue placeholder="Destination type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="extension">Extension</SelectItem>
                            <SelectItem value="number">External Number</SelectItem>
                            <SelectItem value="sip_uri">SIP URI</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="transfer-target">Destination</Label>
                    <Input
                        id="transfer-target"
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
                        <SelectValue placeholder="Caller ID mode" />
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
            <div className="space-y-2">
                <Label>Hangup Cause</Label>
                <Select
                    value={String(config.hangup_cause ?? 'NORMAL_CLEARING')}
                    onValueChange={(value) => onConfigChange({ ...config, hangup_cause: value })}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select hangup cause" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="NORMAL_CLEARING">Normal clearing</SelectItem>
                        <SelectItem value="USER_BUSY">User busy</SelectItem>
                        <SelectItem value="NO_ANSWER">No answer</SelectItem>
                        <SelectItem value="CALL_REJECTED">Call rejected</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="terminal-reason">Reason</Label>
                <Input
                    id="terminal-reason"
                    value={String(config.reason ?? '')}
                    onChange={(event) => onConfigChange({ ...config, reason: event.target.value })}
                    placeholder="Closed for holiday"
                />
            </div>
        </div>
    );
}

export function FlowInspector({
    selectedNode,
    teamOptions,
    mediaOptions,
    onNodeChange,
}: {
    selectedNode: FlowNode | null;
    teamOptions: TeamOption[];
    mediaOptions: SystemMedia[];
    onNodeChange: (node: FlowNode) => void;
}) {
    if (!selectedNode) {
        return (
            <div className="flex h-full items-center justify-center rounded-2xl border border-dashed border-border/70 bg-background/40 p-6 text-center text-sm text-muted-foreground">
                Select a node to edit its configuration.
            </div>
        );
    }

    const normalizedType = normalizeBuilderNodeType(selectedNode.type);
    const definition = getBuilderNodeDefinition(selectedNode.type);
    const config = (selectedNode.config as Record<string, unknown>) ?? {};

    const updateNode = (partial: Partial<FlowNode>) => {
        onNodeChange({
            ...selectedNode,
            ...partial,
            config: partial.config ?? selectedNode.config ?? {},
        });
    };

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

            {normalizedType === 'start' && (
                <div className="space-y-2">
                    <Label htmlFor="start-name">Node Name</Label>
                    <Input
                        id="start-name"
                        value={selectedNode.name ?? 'Call Start'}
                        onChange={(event) => updateNode({ name: event.target.value })}
                    />
                </div>
            )}

            {normalizedType === 'business_hours' && (
                <BusinessHoursNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'caller_match' && (
                <CallerMatchNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'number_match' && (
                <NumberMatchNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'ivr' && (
                <IvrNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    mediaOptions={mediaOptions}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {(normalizedType === 'ring_group' || normalizedType === 'hunt_group') && (
                <RingGroupNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    teamOptions={teamOptions}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'queue' && (
                <QueueNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'play_message' && (
                <PlayMessageNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    mediaOptions={mediaOptions}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'voicemail' && (
                <VoicemailNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'transfer' && (
                <TransferNodeEditor
                    name={selectedNode.name ?? ''}
                    config={config}
                    onNameChange={(name) => updateNode({ name })}
                    onConfigChange={(nextConfig) => updateNode({ config: nextConfig })}
                />
            )}

            {normalizedType === 'terminal' && (
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
