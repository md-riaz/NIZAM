import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Rocket, Save } from 'lucide-react';
import { type Connection, type XYPosition } from '@xyflow/react';

import { FlowCanvas } from '@/components/flow-builder/FlowCanvas';
import { FlowInspector } from '@/components/flow-builder/FlowInspector';
import { FlowNodePalette, type BuilderNodeType } from '@/components/flow-builder/FlowNodePalette';
import {
    createBuilderNode,
    getBuilderNodeDefinition,
    getDefaultOutgoingCondition,
    getEdgeConditionOptions,
    normalizeBuilderNodeType,
    normalizeTeamStrategy,
    serializeBuilderNodeType,
} from '@/components/flow-builder/nodeRegistry';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { getErrorMessage } from '@/lib/api-hooks';
import { cn } from '@/lib/utils';
import type { Flow, FlowEdge, FlowNode, SystemMedia, Team } from '@/types/models';

const studioPanelClass = 'rounded-3xl border border-border/70 bg-card/90 p-4 shadow-[var(--communications-shadow-ambient)]';
const panelClass = 'rounded-2xl border border-border/70 bg-background/95 p-4 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const toolbarClass = 'absolute left-4 right-4 top-4 z-10 flex items-center justify-between gap-3 rounded-2xl border border-border/70 bg-background/92 px-4 py-3 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const overlayPanelClass = 'absolute inset-y-20 right-4 z-10 w-[340px] rounded-2xl border border-border/70 bg-background/95 p-4 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const overlayEmptyClass = 'absolute bottom-4 left-4 z-10 rounded-xl border border-border/70 bg-background/90 px-3 py-2 text-sm text-muted-foreground shadow-sm';
const libraryPanelClass = 'absolute left-4 top-20 z-10 w-[280px]';

function createNode(type: BuilderNodeType, index: number): FlowNode {
    return createBuilderNode(type, index);
}

function serializeNodeForApi(node: FlowNode): FlowNode {
    return {
        ...node,
        type: serializeBuilderNodeType(node.type),
    };
}

function normalizeNodeFromApi(node: FlowNode): FlowNode {
    const normalizedType = normalizeBuilderNodeType(node.type);
    const definition = getBuilderNodeDefinition(normalizedType);
    const normalizedConfig = { ...((node.config as Record<string, unknown>) ?? {}) };

    if (normalizedType === 'ring_team' || normalizedType === 'hunt_group') {
        normalizedConfig.strategy = normalizeTeamStrategy(normalizedConfig.strategy);
    }

    return {
        ...node,
        type: normalizedType,
        config: normalizedConfig,
        name: node.name ?? definition?.defaultName ?? 'Unnamed node',
    };
}

function toEditorState(flow: Flow | null) {
    const version = flow?.latest_version ?? flow?.active_version;
    const nodes = version?.nodes?.length
        ? version.nodes.map(normalizeNodeFromApi)
        : [createNode('start', 0)];

    return {
        name: flow?.name ?? 'Untitled Flow',
        description: flow?.description ?? '',
        nodes,
        edges: version?.edges ?? [],
    };
}

function sanitizeNodes(nodes: FlowNode[]) {
    return nodes.length > 0 ? nodes : [createNode('start', 0)];
}

function sanitizeEdges(edges: FlowEdge[], nodes: FlowNode[]) {
    const validNodeIds = new Set(nodes.map((node) => String(node.id)));
    return edges.filter((edge) => (
        validNodeIds.has(String(edge.source_node_id)) && validNodeIds.has(String(edge.target_node_id))
    ));
}

export default function FlowEditorPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const [name, setName] = useState('Untitled Flow');
    const [description, setDescription] = useState('');
    const [nodes, setNodes] = useState<FlowNode[]>([createNode('start', 0)]);
    const [edges, setEdges] = useState<FlowEdge[]>([]);
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null);
    const [draggedNodeType, setDraggedNodeType] = useState<BuilderNodeType | null>(null);
    const [initializedFromServer, setInitializedFromServer] = useState(false);

    const { data: flow, isLoading } = useQuery<Flow | null>({
        queryKey: ['flow', activeOrganization?.id, id],
        queryFn: async () => {
            if (!id) return null;
            const response = await api.get<{ data: Flow }>(`${organizationApiPrefix}/flows/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeOrganization,
    });

    const { data: teamOptions = [] } = useQuery<Array<{ id: string; name: string }>>({
        queryKey: ['teams', activeOrganization?.id],
        queryFn: async () => {
            const response = await api.get<{ data: Team[] }>(`${organizationApiPrefix}/teams`);
            return response.data.data.map((team) => ({ id: team.id, name: team.name }));
        },
        enabled: !!activeOrganization,
    });

    const { data: mediaOptions = [] } = useQuery<SystemMedia[]>({
        queryKey: ['system-media', activeOrganization?.id],
        queryFn: async () => {
            const response = await api.get<{ data: SystemMedia[] }>(`${organizationApiPrefix}/system-media`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const saveMutation = useMutation({
        mutationFn: async (publish = false) => {
            const payload = {
                name: name.trim() || 'Untitled Flow',
                description: description.trim() || null,
                version: {
                    definition: {
                        nodes: nodes.map(serializeNodeForApi),
                        edges,
                    },
                },
                publish,
            };

            if (isEdit) {
                return api.put(`${organizationApiPrefix}/flows/${id}`, payload);
            }

            return api.post(`${organizationApiPrefix}/flows`, payload);
        },
        onSuccess: async (response, publish) => {
            const savedFlow = response.data.data as Flow;
            await queryClient.invalidateQueries({ queryKey: ['flows', activeOrganization?.id] });
            await queryClient.invalidateQueries({ queryKey: ['flow'] });

            if (!isEdit) {
                navigate(`/admin/flows/${savedFlow.id}/edit`);
                return;
            }

            if (publish) {
                navigate('/admin/flows');
            }
        },
    });

    const publishMutation = useMutation({
        mutationFn: async () => api.post(`${organizationApiPrefix}/flows/${id}/publish`),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['flows', activeOrganization?.id] });
            await queryClient.invalidateQueries({ queryKey: ['flow'] });
            navigate('/admin/flows');
        },
        onError: (error) => {
            window.alert(getErrorMessage(error));
        },
    });

    useEffect(() => {
        if (isEdit) return;
        if (initializedFromServer) return;

        const draftNodes = [createNode('start', 0)];
        setName('Untitled Flow');
        setDescription('');
        setNodes(draftNodes);
        setEdges([]);
        setSelectedNodeId(String(draftNodes[0].id));
        setInitializedFromServer(true);
    }, [isEdit, initializedFromServer]);

    useEffect(() => {
        if (!flow || initializedFromServer) return;

        const next = toEditorState(flow);
        setName(next.name);
        setDescription(next.description);
        setNodes(next.nodes);
        setEdges(sanitizeEdges(next.edges, next.nodes));
        setSelectedNodeId(String(next.nodes[0]?.id ?? ''));
        setInitializedFromServer(true);
    }, [flow, initializedFromServer]);

    useEffect(() => {
        setNodes((current) => sanitizeNodes(current));
    }, []);

    useEffect(() => {
        setEdges((current) => sanitizeEdges(current, nodes));

        if (!selectedNodeId || !nodes.some((node) => String(node.id) === String(selectedNodeId))) {
            setSelectedNodeId(nodes[0] ? String(nodes[0].id) : null);
        }
    }, [nodes, selectedNodeId]);

    useEffect(() => {
        if (!selectedEdgeId) return;
        if (edges.some((edge) => String(edge.id) === String(selectedEdgeId))) return;
        setSelectedEdgeId(null);
    }, [edges, selectedEdgeId]);

    const selectedNode = useMemo(
        () => nodes.find((node) => String(node.id) === String(selectedNodeId)) ?? null,
        [nodes, selectedNodeId],
    );

    const selectedEdge = useMemo(
        () => edges.find((edge) => String(edge.id) === String(selectedEdgeId)) ?? null,
        [edges, selectedEdgeId],
    );

    const selectedEdgeSourceNode = useMemo(
        () => nodes.find((node) => String(node.id) === String(selectedEdge?.source_node_id)) ?? null,
        [nodes, selectedEdge],
    );

    const selectedEdgeConditionOptions = useMemo(() => {
        if (!selectedEdge || !selectedEdgeSourceNode) return [];

        return getEdgeConditionOptions(selectedEdgeSourceNode, edges, selectedEdge.id);
    }, [selectedEdge, selectedEdgeSourceNode, edges]);

    const selectedDefinition = selectedNode ? getBuilderNodeDefinition(selectedNode.type) : null;

    function addNode(type: BuilderNodeType, position?: XYPosition) {
        const next = createNode(type, nodes.length);
        const positionedNode = position
            ? {
                ...next,
                position_x: position.x,
                position_y: position.y,
            }
            : next;

        setNodes((current) => [...current, positionedNode]);
        setSelectedNodeId(String(positionedNode.id));
        setSelectedEdgeId(null);
        setDraggedNodeType(null);
    }

    function handleConnect(connection: Connection) {
        if (!connection.source || !connection.target) return;

        const sourceNode = nodes.find((node) => String(node.id) === String(connection.source));
        const nextCondition = sourceNode ? getDefaultOutgoingCondition(sourceNode, edges) : null;

        if (sourceNode && getBuilderNodeDefinition(sourceNode.type)?.transitionOptions?.length && !nextCondition) {
            window.alert('All available transitions for this node are already used. Remove or change an existing edge first.');
            return;
        }

        setSelectedNodeId(null);
        setEdges((current) => {
            const nextEdge: FlowEdge = {
                id: `edge-${Date.now()}-${current.length}`,
                source_node_id: String(connection.source),
                target_node_id: String(connection.target),
                condition: nextCondition,
            };

            setSelectedEdgeId(String(nextEdge.id));

            return [...current, nextEdge];
        });
    }

    function handleEdgeConditionChange(edgeId: string, condition: string) {
        setEdges((current) => current.map((edge) => (
            String(edge.id) === String(edgeId)
                ? { ...edge, condition }
                : edge
        )));
    }

    function handleSelectNode(nodeId: string | null) {
        setSelectedNodeId(nodeId);
        if (nodeId) setSelectedEdgeId(null);
    }

    function handleSelectEdge(edgeId: string | null) {
        setSelectedEdgeId(edgeId);
        if (edgeId) setSelectedNodeId(null);
    }

    function handleRemoveSelectedEdge() {
        if (!selectedEdgeId) return;
        setEdges((current) => current.filter((edge) => String(edge.id) !== String(selectedEdgeId)));
        setSelectedEdgeId(null);
    }

    if (!activeOrganization) return null;

    return (
        <div className="min-h-screen bg-background p-4 lg:p-6">
            <section className={cn(studioPanelClass, 'relative overflow-hidden p-3')}>
                {isLoading ? (
                    <div className="flex h-[82vh] items-center justify-center rounded-2xl bg-background/70 text-sm text-muted-foreground">
                        Loading flow definition...
                    </div>
                ) : (
                    <>
                        <div className={toolbarClass}>
                            <div className="flex items-center gap-3">
                                <Button variant="outline" size="icon" onClick={() => navigate('/admin/flows')}>
                                    <ArrowLeft className="size-4" />
                                </Button>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-foreground">{name.trim() || 'Untitled Flow'}</p>
                                    <p className="text-xs text-muted-foreground">{nodes.length} nodes • {edges.length} edges</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => saveMutation.mutate(false)}
                                    disabled={saveMutation.isPending || publishMutation.isPending}
                                >
                                    <Save className="mr-2 size-4" />
                                    {saveMutation.isPending ? 'Saving...' : 'Save Draft'}
                                </Button>
                                <Button
                                    onClick={() => (isEdit ? publishMutation.mutate() : saveMutation.mutate(true))}
                                    disabled={saveMutation.isPending || publishMutation.isPending}
                                >
                                    <Rocket className="mr-2 size-4" />
                                    {publishMutation.isPending || (saveMutation.isPending && !isEdit) ? 'Publishing...' : 'Publish Flow'}
                                </Button>
                            </div>
                        </div>

                        <div className={cn(panelClass, libraryPanelClass)}>
                            <FlowNodePalette
                                onAddNode={addNode}
                                onDragNodeStart={setDraggedNodeType}
                            />
                        </div>

                        <FlowCanvas
                            nodes={nodes}
                            edges={edges}
                            selectedNodeId={selectedNodeId}
                            selectedEdgeId={selectedEdgeId}
                            onNodesChange={setNodes}
                            onEdgesChange={setEdges}
                            onConnect={handleConnect}
                            draggedNodeType={draggedNodeType}
                            onDropNode={addNode}
                            onSelectNode={handleSelectNode}
                            onSelectEdge={handleSelectEdge}
                            onRemoveSelectedEdge={handleRemoveSelectedEdge}
                        />

                        {selectedNode ? (
                            <div className={overlayPanelClass}>
                                <div className="mb-4 space-y-3">
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
                                            Node Properties
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                            {selectedDefinition && (
                                                <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${selectedDefinition.accentClassName}`}>
                                                    {selectedDefinition.label}
                                                </span>
                                            )}
                                            <span className="text-sm font-medium text-foreground">
                                                {selectedNode.name}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <FlowInspector
                                    selectedNode={selectedNode}
                                    teamOptions={teamOptions}
                                    mediaOptions={mediaOptions}
                                    onNodeChange={(updatedNode) => {
                                        setNodes((current) => current.map((node) => (
                                            String(node.id) === String(updatedNode.id) ? updatedNode : node
                                        )));
                                    }}
                                />
                            </div>
                        ) : selectedEdge && selectedEdgeSourceNode ? (
                            <div className={overlayPanelClass}>
                                <div className="mb-4 space-y-3">
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
                                            Edge Properties
                                        </p>
                                        <div className="mt-2 space-y-1">
                                            <p className="text-sm font-medium text-foreground">
                                                {selectedEdgeSourceNode.name ?? 'Connection'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {selectedEdge.source_node_id} → {selectedEdge.target_node_id}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Transition</Label>
                                        <Select
                                            value={selectedEdge.condition ?? ''}
                                            onValueChange={(value) => handleEdgeConditionChange(selectedEdge.id, value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select transition" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {selectedEdgeConditionOptions.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                                {selectedEdge.condition && !selectedEdgeConditionOptions.some((option) => option.value === selectedEdge.condition) && (
                                                    <SelectItem value={selectedEdge.condition}>{selectedEdge.condition}</SelectItem>
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedEdgeConditionOptions.find((option) => option.value === selectedEdge.condition)?.description
                                                ?? 'Choose how this node should branch on this connection.'}
                                        </p>
                                    </div>
                                    <Button type="button" variant="outline" onClick={handleRemoveSelectedEdge}>
                                        Remove edge
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className={overlayEmptyClass}>Select node or edge to edit properties</div>
                        )}
                    </>
                )}
            </section>
        </div>
    );
}
