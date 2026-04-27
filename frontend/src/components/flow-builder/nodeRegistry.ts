import type { LucideIcon } from 'lucide-react';
import {
    ArrowRightLeft,
    CircleDot,
    Clock3,
    GitBranch,
    Headphones,
    PhoneCall,
    PhoneForwarded,
    Play,
    Voicemail,
    Workflow,
} from 'lucide-react';

import type { FlowNode } from '@/types/models';

export type BuilderNodeType =
    | 'start'
    | 'business_hours'
    | 'caller_match'
    | 'number_match'
    | 'ivr'
    | 'ring_group'
    | 'hunt_group'
    | 'queue'
    | 'play_message'
    | 'voicemail'
    | 'transfer'
    | 'terminal';

export interface BuilderNodeTransitionOption {
    value: string;
    label: string;
    description?: string;
}

export interface BuilderNodeDefinition {
    type: BuilderNodeType;
    category: 'Entry' | 'Conditions' | 'Routing' | 'Actions' | 'End';
    label: string;
    title: string;
    description: string;
    icon: LucideIcon;
    accentClassName: string;
    accentColor: string;
    defaultName: string;
    createConfig: () => Record<string, unknown>;
    transitionOptions?: BuilderNodeTransitionOption[];
}

export const builderNodeDefinitions: BuilderNodeDefinition[] = [
    {
        type: 'start',
        category: 'Entry',
        label: 'Start',
        title: 'Start Node',
        description: 'Entry point for inbound call execution.',
        icon: Workflow,
        accentClassName: 'border-sky-200 bg-sky-50 text-sky-700',
        accentColor: '#0ea5e9',
        defaultName: 'Call Start',
        createConfig: () => ({}),
        transitionOptions: [
            { value: 'next', label: 'Next', description: 'Continue to next step.' },
        ],
    },
    {
        type: 'business_hours',
        category: 'Conditions',
        label: 'Business Hours',
        title: 'Business Hours Node',
        description: 'Route by open, closed, or holiday schedule state.',
        icon: Clock3,
        accentClassName: 'border-indigo-200 bg-indigo-50 text-indigo-700',
        accentColor: '#6366f1',
        defaultName: 'Business Hours',
        createConfig: () => ({ schedule_mode: 'organization_default', schedule_id: '' }),
        transitionOptions: [
            { value: 'open', label: 'Open', description: 'Call arrives during open hours.' },
            { value: 'closed', label: 'Closed', description: 'Call arrives outside business hours.' },
            { value: 'holiday', label: 'Holiday', description: 'Call arrives during holiday override.' },
        ],
    },
    {
        type: 'caller_match',
        category: 'Conditions',
        label: 'Caller Match',
        title: 'Caller Match Node',
        description: 'Route callers by number, prefix, or caller class.',
        icon: GitBranch,
        accentClassName: 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700',
        accentColor: '#c026d3',
        defaultName: 'Caller Match',
        createConfig: () => ({ mode: 'exact', numbers: [''], list_id: '' }),
        transitionOptions: [
            { value: 'match', label: 'Match', description: 'Caller matches configured rule.' },
            { value: 'no_match', label: 'No match', description: 'Caller does not match any rule.' },
        ],
    },
    {
        type: 'number_match',
        category: 'Conditions',
        label: 'Number Match',
        title: 'Number Match Node',
        description: 'Route by inbound DID or number group.',
        icon: GitBranch,
        accentClassName: 'border-cyan-200 bg-cyan-50 text-cyan-700',
        accentColor: '#0891b2',
        defaultName: 'Number Match',
        createConfig: () => ({ mode: 'did', numbers: [''], group_id: '' }),
        transitionOptions: [
            { value: 'match', label: 'Match', description: 'Inbound number matches configured target.' },
            { value: 'no_match', label: 'No match', description: 'Inbound number does not match.' },
        ],
    },
    {
        type: 'ivr',
        category: 'Routing',
        label: 'Menu',
        title: 'Menu Node',
        description: 'Collect digits and branch callers by menu choice.',
        icon: CircleDot,
        accentClassName: 'border-violet-200 bg-violet-50 text-violet-700',
        accentColor: '#8b5cf6',
        defaultName: 'IVR Menu',
        createConfig: () => ({ prompt: '', media_id: '', prompt_media_id: '', short_greeting: '', timeout: 5, max_failures: 3, options: [] }),
        transitionOptions: [
            ...Array.from({ length: 10 }, (_, digit) => ({
                value: `digit_${digit}`,
                label: `Digit ${digit}`,
                description: `Route callers who press ${digit}.`,
            })),
            { value: 'timeout', label: 'Timeout', description: 'No digit entered before timeout.' },
            { value: 'invalid', label: 'Invalid', description: 'Caller enters unsupported digit.' },
        ],
    },
    {
        type: 'ring_group',
        category: 'Routing',
        label: 'Ring Team',
        title: 'Ring Team Node',
        description: 'Ring a team with answer, timeout, and failover branches.',
        icon: PhoneCall,
        accentClassName: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        accentColor: '#10b981',
        defaultName: 'Ring Team',
        createConfig: () => ({ team_id: '', timeout: 30, strategy: 'simultaneous', members_text: '' }),
        transitionOptions: [
            { value: 'answered', label: 'Answered', description: 'Team answers call and flow continues.' },
            { value: 'timeout', label: 'No answer', description: 'No one answers before timeout.' },
            { value: 'failed', label: 'Failed', description: 'Team bridge fails or destination unavailable.' },
            { value: 'no_answer', label: 'Missed', description: 'Call rings but nobody answers.' },
        ],
    },
    {
        type: 'hunt_group',
        category: 'Routing',
        label: 'Hunt Group',
        title: 'Hunt Group Node',
        description: 'Route callers across team members with hunt strategy controls.',
        icon: PhoneCall,
        accentClassName: 'border-lime-200 bg-lime-50 text-lime-700',
        accentColor: '#65a30d',
        defaultName: 'Hunt Group',
        createConfig: () => ({ team_id: '', timeout: 30, strategy: 'sequential', members_text: '' }),
        transitionOptions: [
            { value: 'answered', label: 'Answered', description: 'Team answers call and flow continues.' },
            { value: 'timeout', label: 'No answer', description: 'No one answers before timeout.' },
            { value: 'failed', label: 'Failed', description: 'Team bridge fails or destination unavailable.' },
            { value: 'no_answer', label: 'Missed', description: 'Call rings but nobody answers.' },
        ],
    },
    {
        type: 'queue',
        category: 'Routing',
        label: 'Queue',
        title: 'Queue Node',
        description: 'Hold callers for next available agent group.',
        icon: Headphones,
        accentClassName: 'border-amber-200 bg-amber-50 text-amber-700',
        accentColor: '#f59e0b',
        defaultName: 'Support Queue',
        createConfig: () => ({ queue_id: '', queue_name: '', strategy: 'round_robin', max_wait_seconds: 120 }),
    },
    {
        type: 'play_message',
        category: 'Actions',
        label: 'Play Message',
        title: 'Play Message Node',
        description: 'Play audio or text-to-speech before routing onward.',
        icon: Play,
        accentClassName: 'border-rose-200 bg-rose-50 text-rose-700',
        accentColor: '#e11d48',
        defaultName: 'Play Message',
        createConfig: () => ({ prompt: '', media_id: '', message: '', voice: 'default' }),
    },
    {
        type: 'voicemail',
        category: 'Actions',
        label: 'Voicemail',
        title: 'Voicemail Node',
        description: 'Deposit caller into a mailbox or extension voicemail target.',
        icon: Voicemail,
        accentClassName: 'border-purple-200 bg-purple-50 text-purple-700',
        accentColor: '#9333ea',
        defaultName: 'Voicemail',
        createConfig: () => ({ mailbox: '', greeting: '', transcription_enabled: false, email_notification_enabled: false }),
    },
    {
        type: 'transfer',
        category: 'Routing',
        label: 'Transfer',
        title: 'Transfer Node',
        description: 'Transfer caller to extension, number, or target.',
        icon: PhoneForwarded,
        accentClassName: 'border-blue-200 bg-blue-50 text-blue-700',
        accentColor: '#3b82f6',
        defaultName: 'Transfer Call',
        createConfig: () => ({ destination_type: 'extension', destination_value: '', caller_id_mode: 'inherit' }),
    },
    {
        type: 'terminal',
        category: 'End',
        label: 'End Call',
        title: 'End Call Node',
        description: 'End flow with explicit hangup outcome.',
        icon: ArrowRightLeft,
        accentClassName: 'border-slate-200 bg-slate-100 text-slate-700',
        accentColor: '#64748b',
        defaultName: 'End Call',
        createConfig: () => ({ hangup_cause: 'NORMAL_CLEARING', reason: '' }),
    },
];

export const builderNodeDefinitionMap = Object.fromEntries(
    builderNodeDefinitions.map((definition) => [definition.type, definition]),
) as Record<BuilderNodeType, BuilderNodeDefinition>;

const productTypeMap: Record<string, BuilderNodeType> = {
    start: 'start',
    schedule_check: 'business_hours',
    business_hours: 'business_hours',
    caller_match: 'caller_match',
    number_match: 'number_match',
    menu: 'ivr',
    ivr: 'ivr',
    ring_team: 'ring_group',
    ring_group: 'ring_group',
    hunt_group: 'hunt_group',
    queue: 'queue',
    play_message: 'play_message',
    voicemail: 'voicemail',
    transfer: 'transfer',
    hangup: 'terminal',
    end_call: 'terminal',
    terminal: 'terminal',
};

export const legacyTypeMap: Record<string, string> = {
    business_hours: 'schedule_check',
    ivr: 'menu',
    ring_group: 'ring_team',
    terminal: 'hangup',
};

export function getBuilderNodeDefinition(type: string): BuilderNodeDefinition | null {
    const normalizedType = productTypeMap[type] ?? type;
    return builderNodeDefinitionMap[normalizedType as BuilderNodeType] ?? null;
}

export function normalizeBuilderNodeType(type: string): BuilderNodeType {
    return productTypeMap[type] ?? 'terminal';
}

export function serializeBuilderNodeType(type: string): string {
    return legacyTypeMap[type] ?? type;
}

export function createBuilderNode(type: BuilderNodeType, index: number): FlowNode {
    const definition = builderNodeDefinitionMap[type];

    return {
        id: `node-${Date.now()}-${index}`,
        type,
        name: definition.defaultName,
        config: definition.createConfig(),
        position_x: 120 + index * 40,
        position_y: 120 + index * 40,
    };
}

export function getBuilderNodeTransitionOptions(type: string): BuilderNodeTransitionOption[] {
    return getBuilderNodeDefinition(type)?.transitionOptions ?? [];
}

export function getAvailableOutgoingConditions(node: FlowNode, edges: Array<{ source_node_id: string; condition?: string | null }>): BuilderNodeTransitionOption[] {
    const transitionOptions = getBuilderNodeTransitionOptions(node.type);
    if (transitionOptions.length === 0) return [];

    const usedConditions = new Set(
        edges
            .filter((edge) => String(edge.source_node_id) === String(node.id))
            .map((edge) => edge.condition)
            .filter((condition): condition is string => Boolean(condition)),
    );

    return transitionOptions.filter((option) => !usedConditions.has(option.value));
}

export function getDefaultOutgoingCondition(node: FlowNode, edges: Array<{ source_node_id: string; condition?: string | null }>): string | null {
    return getAvailableOutgoingConditions(node, edges)[0]?.value ?? null;
}

export function getEdgeConditionOptions(
    node: FlowNode,
    edges: Array<{ id?: string; source_node_id: string; condition?: string | null }>,
    currentEdgeId?: string | null,
): BuilderNodeTransitionOption[] {
    const transitionOptions = getBuilderNodeTransitionOptions(node.type);
    if (transitionOptions.length === 0) return [];

    const usedConditions = new Set(
        edges
            .filter((edge) => String(edge.source_node_id) === String(node.id) && String(edge.id ?? '') !== String(currentEdgeId ?? ''))
            .map((edge) => edge.condition)
            .filter((condition): condition is string => Boolean(condition)),
    );

    return transitionOptions.filter((option) => !usedConditions.has(option.value));
}

export function builderSubtitleForNode(node: FlowNode): string {
    const config = (node.config as Record<string, unknown>) ?? {};

    switch (normalizeBuilderNodeType(node.type)) {
        case 'start':
            return 'Inbound entry point';
        case 'business_hours':
            return String(config.schedule_mode === 'organization_default' ? 'Organization default schedule' : (config.schedule_id || 'Custom schedule required'));
        case 'caller_match':
            return `${String(config.mode || 'exact')} • ${Array.isArray(config.numbers) ? config.numbers.filter(Boolean).length : 0} rule(s)`;
        case 'number_match':
            return `${String(config.mode || 'did')} • ${Array.isArray(config.numbers) ? config.numbers.filter(Boolean).length : 0} target(s)`;
        case 'ivr':
            return String(config.prompt || config.greeting || 'No greeting set');
        case 'ring_group':
        case 'hunt_group': {
            const teamId = String(config.team_id || 'unassigned');
            const timeout = String(config.timeout || 30);
            const strategy = String(config.strategy || 'simultaneous');
            return `${teamId} • ${strategy} • ${timeout}s`;
        }
        case 'queue': {
            const queueName = String(config.queue_name || 'No queue selected');
            const strategy = String(config.strategy || 'round_robin');
            return `${queueName} • ${strategy}`;
        }
        case 'play_message':
            return String(config.prompt || config.message || 'No message set');
        case 'voicemail':
            return String(config.mailbox || 'Mailbox required');
        case 'transfer': {
            const destinationType = String(config.destination_type || 'extension');
            const destinationValue = String(config.destination_value || 'unset');
            return `${destinationType} • ${destinationValue}`;
        }
        case 'terminal':
            return String(config.reason || config.hangup_cause || 'Flow ends here');
        default:
            return node.type;
    }
}
