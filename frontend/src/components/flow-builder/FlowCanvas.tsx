import { useEffect, useMemo, useState, type DragEvent, type MouseEvent } from 'react';
import { Trash2 } from 'lucide-react';
import {
    Background,
    Controls,
    MiniMap,
    ReactFlow,
    ReactFlowProvider,
    applyEdgeChanges,
    applyNodeChanges,
    useReactFlow,
    type Connection,
    type Edge,
    type EdgeChange,
    type Node,
    type NodeChange,
    type XYPosition,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { Button } from '@/components/ui/button';
import type { FlowEdge, FlowNode } from '@/types/models';

import { FlowStudioNode } from './FlowStudioNode';
import { readDraggedNodeType, type BuilderNodeType } from './FlowNodePalette';
import { getBuilderNodeDefinition } from './nodeRegistry';

const NODE_TYPES = {
    studio: FlowStudioNode,
};

const STUDIO_NODE_WIDTH = 260;
const STUDIO_NODE_HEIGHT = 120;

function toReactFlowNodes(nodes: FlowNode[], selectedNodeId: string | null): Node[] {
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
        selected: String(node.id) === String(selectedNodeId),
        draggable: true,
        initialWidth: STUDIO_NODE_WIDTH,
        initialHeight: STUDIO_NODE_HEIGHT,
    }));
}

function toReactFlowEdges(edges: FlowEdge[], selectedEdgeId: string | null): Edge[] {
    return edges.map((edge) => ({
        id: String(edge.id),
        source: String(edge.source_node_id),
        target: String(edge.target_node_id),
        label: edge.condition ?? '',
        selected: String(edge.id) === String(selectedEdgeId),
        animated: false,
        style: {
            stroke: String(edge.id) === String(selectedEdgeId) ? '#2563eb' : '#94a3b8',
            strokeWidth: String(edge.id) === String(selectedEdgeId) ? 2.4 : 1.75,
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

function projectNodes(sourceNodes: FlowNode[], nextVisualNodes: Node[]) {
    return sourceNodes
        .filter((node) => nextVisualNodes.some((candidate) => candidate.id === String(node.id)))
        .map((node) => {
            const changed = nextVisualNodes.find((candidate) => candidate.id === String(node.id));
            if (!changed) return node;

            return {
                ...node,
                position_x: changed.position.x,
                position_y: changed.position.y,
            };
        });
}

function projectEdges(nextVisualEdges: Edge[]): FlowEdge[] {
    return nextVisualEdges.map((edge) => ({
        id: String(edge.id),
        source_node_id: String(edge.source),
        target_node_id: String(edge.target),
        condition: typeof edge.label === 'string' ? edge.label : null,
    }));
}

type FlowCanvasProps = {
    nodes: FlowNode[];
    edges: FlowEdge[];
    selectedNodeId: string | null;
    draggedNodeType: BuilderNodeType | null;
    onNodesChange: (nodes: FlowNode[]) => void;
    onEdgesChange: (edges: FlowEdge[]) => void;
    onConnect: (connection: Connection) => void;
    onDropNode: (type: BuilderNodeType, position: XYPosition) => void;
    onSelectNode: (nodeId: string | null) => void;
};

function FlowCanvasInner({
    nodes,
    edges,
    selectedNodeId,
    draggedNodeType,
    onNodesChange,
    onEdgesChange,
    onConnect,
    onDropNode,
    onSelectNode,
}: FlowCanvasProps) {
    const { screenToFlowPosition } = useReactFlow();
    const [flowNodes, setFlowNodes] = useState<Node[]>(() => toReactFlowNodes(nodes, selectedNodeId));
    const [flowEdges, setFlowEdges] = useState<Edge[]>(() => toReactFlowEdges(edges, null));
    const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null);

    const summary = useMemo(() => selectionSummary(selectedNodeId, nodes, edges), [selectedNodeId, nodes, edges]);
    const selectedEdge = selectedEdgeId
        ? flowEdges.find((edge) => edge.id === selectedEdgeId) ?? null
        : null;

    useEffect(() => {
        setFlowNodes((current) => {
            const nextNodes = toReactFlowNodes(nodes, selectedNodeId);

            return nextNodes.map((nextNode) => {
                const existingNode = current.find((node) => node.id === nextNode.id);
                if (!existingNode) return nextNode;

                return {
                    ...existingNode,
                    position: nextNode.position,
                    data: nextNode.data,
                    selected: nextNode.selected,
                };
            });
        });
    }, [nodes, selectedNodeId]);

    useEffect(() => {
        setSelectedEdgeId((current) => (
            edges.some((edge) => String(edge.id) === String(current)) ? current : null
        ));
    }, [edges]);

    useEffect(() => {
        setFlowEdges(toReactFlowEdges(edges, selectedEdgeId));
    }, [edges, selectedEdgeId]);

    function handleNodesChange(changes: NodeChange[]) {
        const nextVisualNodes = applyNodeChanges(changes, flowNodes);
        setFlowNodes(nextVisualNodes);

        if (changes.some((change) => change.type === 'remove')) {
            onNodesChange(projectNodes(nodes, nextVisualNodes));
        }
    }

    function handleNodeDragStop(_: MouseEvent, node: Node) {
        const nextVisualNodes = flowNodes.map((current) => (
            current.id === node.id ? { ...current, position: node.position } : current
        ));

        setFlowNodes(nextVisualNodes);
        onNodesChange(projectNodes(nodes, nextVisualNodes));
    }

    function handleEdgesChange(changes: EdgeChange[]) {
        const nextVisualEdges = applyEdgeChanges(changes, flowEdges);
        setFlowEdges(nextVisualEdges);

        if (changes.some((change) => change.type === 'remove')) {
            onEdgesChange(projectEdges(nextVisualEdges));
        }

        if (changes.some((change) => change.type === 'select')) {
            const selected = nextVisualEdges.find((edge) => edge.selected);
            setSelectedEdgeId(selected?.id ?? null);
        }
    }

    function handleConnect(connection: Connection) {
        setSelectedEdgeId(null);
        onConnect(connection);
    }

    function handleNodeClick(_: MouseEvent, node: Node) {
        setSelectedEdgeId(null);
        onSelectNode(node.id);
    }

    function handleEdgeClick(_: MouseEvent, edge: Edge) {
        setSelectedEdgeId(edge.id);
        onSelectNode(null);
    }

    function handlePaneClick() {
        setSelectedEdgeId(null);
        onSelectNode(null);
    }

    function removeSelectedEdge() {
        if (!selectedEdgeId) return;

        const nextVisualEdges = flowEdges.filter((edge) => edge.id !== selectedEdgeId);
        setFlowEdges(nextVisualEdges);
        setSelectedEdgeId(null);
        onEdgesChange(projectEdges(nextVisualEdges));
    }

    function handleDragOver(event: DragEvent<HTMLDivElement>) {
        if (!draggedNodeType && !readDraggedNodeType(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();

        const nodeType = draggedNodeType ?? readDraggedNodeType(event);
        if (!nodeType) return;

        const position = screenToFlowPosition({
            x: event.clientX,
            y: event.clientY,
        });

        onDropNode(nodeType, position);
    }

    return (
        <div className="relative h-[70vh] overflow-hidden rounded-3xl border border-border/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.98))] shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]">
            <ReactFlow
                nodes={flowNodes}
                edges={flowEdges}
                nodeTypes={NODE_TYPES}
                fitView
                nodesDraggable
                nodesConnectable
                edgesFocusable
                elementsSelectable
                connectionMode="loose"
                deleteKeyCode={['Backspace', 'Delete']}
                onNodesChange={handleNodesChange}
                onNodeDragStop={handleNodeDragStop}
                onEdgesChange={handleEdgesChange}
                onConnect={handleConnect}
                onNodeClick={handleNodeClick}
                onEdgeClick={handleEdgeClick}
                onPaneClick={handlePaneClick}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
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

            {selectedEdge && (
                <div className="absolute right-4 top-4 z-20 flex items-center gap-3 rounded-2xl border border-border/70 bg-background/95 px-3 py-2 shadow-sm backdrop-blur">
                    <div className="text-xs text-muted-foreground">
                        <p className="font-semibold text-foreground">{typeof selectedEdge.label === 'string' && selectedEdge.label ? selectedEdge.label : 'Connection selected'}</p>
                        <p>{selectedEdge.source} → {selectedEdge.target}</p>
                    </div>
                    <Button type="button" variant="outline" size="sm" onClick={removeSelectedEdge}>
                        <Trash2 className="mr-2 size-4" />
                        Remove edge
                    </Button>
                </div>
            )}

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

export function FlowCanvas(props: FlowCanvasProps) {
    return (
        <ReactFlowProvider>
            <FlowCanvasInner {...props} />
        </ReactFlowProvider>
    );
}
