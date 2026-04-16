import { useMemo } from 'react';
import {
    Background,
    Controls,
    MiniMap,
    ReactFlow,
    applyEdgeChanges,
    applyNodeChanges,
    type Connection,
    type Edge,
    type EdgeChange,
    type Node,
    type NodeChange,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import type { FlowEdge, FlowNode } from '@/types/models';

import { FlowStudioNode } from './FlowStudioNode';
import { getBuilderNodeDefinition } from './nodeRegistry';

const NODE_TYPES = {
    studio: FlowStudioNode,
};

function toReactFlowNodes(nodes: FlowNode[]): Node[] {
    return nodes.map((node) => ({
        id: String(node.id),
        type: 'studio',
        position: {
            x: node.position_x ?? 0,
            y: node.position_y ?? 0,
        },
        data: {
            flowNode: node,
        },
        draggable: true,
    }));
}

function toReactFlowEdges(edges: FlowEdge[]): Edge[] {
    return edges.map((edge) => ({
        id: String(edge.id),
        source: String(edge.source_node_id),
        target: String(edge.target_node_id),
        label: edge.condition ?? '',
        animated: false,
        style: {
            stroke: '#94a3b8',
            strokeWidth: 1.75,
        },
        labelStyle: {
            fill: '#475569',
            fontSize: 11,
            fontWeight: 600,
        },
        labelBgStyle: {
            fill: '#ffffff',
            opacity: 0.98,
            stroke: '#e2e8f0',
            strokeWidth: 1,
        },
    }));
}

function selectionSummary(selectedNodeId: string | null, nodes: FlowNode[], edges: FlowEdge[]) {
    if (!selectedNodeId) return null;

    const selectedNode = nodes.find((node) => String(node.id) === selectedNodeId);
    if (!selectedNode) return null;

    return {
        name: selectedNode.name ?? 'Unnamed node',
        type: getBuilderNodeDefinition(selectedNode.type)?.label ?? selectedNode.type,
        incoming: edges.filter((edge) => String(edge.target_node_id) === selectedNodeId).length,
        outgoing: edges.filter((edge) => String(edge.source_node_id) === selectedNodeId).length,
    };
}

export function FlowCanvas({
    nodes,
    edges,
    selectedNodeId,
    onNodesChange,
    onEdgesChange,
    onConnect,
    onSelectNode,
}: {
    nodes: FlowNode[];
    edges: FlowEdge[];
    selectedNodeId: string | null;
    onNodesChange: (nodes: FlowNode[]) => void;
    onEdgesChange: (edges: FlowEdge[]) => void;
    onConnect: (connection: Connection) => void;
    onSelectNode: (nodeId: string | null) => void;
}) {
    const flowNodes = useMemo(() => toReactFlowNodes(nodes), [nodes]);
    const flowEdges = useMemo(() => toReactFlowEdges(edges), [edges]);
    const summary = useMemo(() => selectionSummary(selectedNodeId, nodes, edges), [selectedNodeId, nodes, edges]);

    return (
        <div className="h-[70vh] overflow-hidden rounded-3xl border border-border/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.98))] shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">
            <ReactFlow
                nodes={flowNodes}
                edges={flowEdges}
                nodeTypes={NODE_TYPES}
                fitView
                nodesDraggable
                deleteKeyCode={['Backspace', 'Delete']}
                onNodesChange={(changes: NodeChange[]) => {
                    const nextVisualNodes = applyNodeChanges(changes, flowNodes);
                    const removedIds = new Set(
                        changes
                            .filter((change) => change.type === 'remove')
                            .map((change) => change.id),
                    );

                    const updatedNodes = nodes
                        .filter((node) => !removedIds.has(String(node.id)))
                        .map((node) => {
                            const changed = nextVisualNodes.find((candidate) => candidate.id === String(node.id));
                            if (!changed) return node;

                            return {
                                ...node,
                                position_x: changed.position.x,
                                position_y: changed.position.y,
                            };
                        });

                    onNodesChange(updatedNodes);
                }}
                onEdgesChange={(changes: EdgeChange[]) => {
                    const nextVisualEdges = applyEdgeChanges(changes, flowEdges);
                    onEdgesChange(
                        nextVisualEdges.map((edge) => ({
                            id: String(edge.id),
                            source_node_id: String(edge.source),
                            target_node_id: String(edge.target),
                            condition: typeof edge.label === 'string' ? edge.label : null,
                        })),
                    );
                }}
                onConnect={onConnect}
                onNodeClick={(_, node) => onSelectNode(node.id)}
                onPaneClick={() => onSelectNode(null)}
            >
                <MiniMap
                    pannable
                    zoomable
                    nodeColor={(node) => getBuilderNodeDefinition(nodes.find((item) => String(item.id) === node.id)?.type ?? '')?.accentColor ?? '#94a3b8'}
                    maskColor="rgba(226,232,240,0.7)"
                    className="!bg-white !border !border-slate-200 !rounded-2xl !shadow-sm"
                />
                <Controls className="!rounded-2xl !border !border-slate-200 !bg-white !text-slate-700 !shadow-sm" />
                <Background gap={24} size={1} color="rgba(148,163,184,0.18)" />
            </ReactFlow>

            {summary && (
                <div className="border-t border-border/70 bg-background/90 px-4 py-3 text-xs text-muted-foreground">
                    <span className="font-semibold text-foreground">{summary.name}</span>
                    <span className="mx-2 text-slate-300">•</span>
                    <span>{summary.type}</span>
                    <span className="mx-2 text-slate-300">•</span>
                    <span>{summary.incoming} incoming</span>
                    <span className="mx-2 text-slate-300">•</span>
                    <span>{summary.outgoing} outgoing</span>
                </div>
            )}
        </div>
    );
}
