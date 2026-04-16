import type { LucideIcon } from 'lucide-react';
import { ArrowRightLeft, CircleDot, Headphones, PhoneCall, PhoneForwarded, Workflow } from 'lucide-react';

import type { FlowNode } from '@/types/models';

export type BuilderNodeType = 'start' | 'ivr' | 'ring_group' | 'queue' | 'transfer' | 'terminal';

interface BuilderNodeDefinition {
    type: BuilderNodeType;
    label: string;
    title: string;
    description: string;
    icon: LucideIcon;
    accentClassName: string;
    accentColor: string;
    defaultName: string;
    createConfig: () => Record<string, unknown>;
}

export const builderNodeDefinitions: BuilderNodeDefinition[] = [
    {
        type: 'start',
        label: 'Start',
        title: 'Start Node',
        description: 'Entry point for inbound call execution.',
        icon: Workflow,
        accentClassName: 'border-sky-200 bg-sky-50 text-sky-700',
        accentColor: '#0ea5e9',
        defaultName: 'Call Start',
        createConfig: () => ({}),
    },
    {
        type: 'ivr',
        label: 'IVR',
        title: 'IVR Node',
        description: 'Collect digits and branch callers by menu choice.',
        icon: CircleDot,
        accentClassName: 'border-violet-200 bg-violet-50 text-violet-700',
        accentColor: '#8b5cf6',
        defaultName: 'IVR Menu',
        createConfig: () => ({ greeting: '', short_greeting: '', timeout: 5, max_failures: 3, options: [] }),
    },
    {
        type: 'ring_group',
        label: 'Ring Group',
        title: 'Ring Group Node',
        description: 'Ring a team or hunt sequence.',
        icon: PhoneCall,
        accentClassName: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        accentColor: '#10b981',
        defaultName: 'Ring Group',
        createConfig: () => ({ team_id: '', timeout: 30, strategy: 'simultaneous', members_text: '' }),
    },
    {
        type: 'queue',
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
        type: 'transfer',
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
        label: 'Terminal',
        title: 'Terminal Node',
        description: 'End flow with final outcome or hangup.',
        icon: ArrowRightLeft,
        accentClassName: 'border-slate-200 bg-slate-100 text-slate-700',
        accentColor: '#64748b',
        defaultName: 'End Flow',
        createConfig: () => ({ outcome: 'hangup', reason: '' }),
    },
];

export const builderNodeDefinitionMap = Object.fromEntries(
    builderNodeDefinitions.map((definition) => [definition.type, definition]),
) as Record<BuilderNodeType, BuilderNodeDefinition>;

export function getBuilderNodeDefinition(type: string): BuilderNodeDefinition | null {
    return builderNodeDefinitionMap[type as BuilderNodeType] ?? null;
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

export function builderSubtitleForNode(node: FlowNode): string {
    const config = (node.config as Record<string, unknown>) ?? {};

    switch (node.type) {
        case 'start':
            return 'Inbound entry point';
        case 'ivr':
            return String(config.greeting || 'No greeting set');
        case 'ring_group': {
            const teamId = String(config.team_id || 'unassigned');
            const timeout = String(config.timeout || 30);
            return `Group ${teamId} • ${timeout}s timeout`;
        }
        case 'queue': {
            const queueName = String(config.queue_name || 'No queue selected');
            const strategy = String(config.strategy || 'round_robin');
            return `${queueName} • ${strategy}`;
        }
        case 'transfer': {
            const destinationType = String(config.destination_type || 'extension');
            const destinationValue = String(config.destination_value || 'unset');
            return `${destinationType} • ${destinationValue}`;
        }
        case 'terminal':
            return String(config.reason || config.outcome || 'Flow ends here');
        default:
            return node.type;
    }
}
