import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Rocket, Save } from 'lucide-react';
import { type Connection } from '@xyflow/react';

import { FlowCanvas } from '@/components/flow-builder/FlowCanvas';
import { FlowInspector } from '@/components/flow-builder/FlowInspector';
import { FlowNodePalette, type BuilderNodeType } from '@/components/flow-builder/FlowNodePalette';
import { createBuilderNode, getBuilderNodeDefinition } from '@/components/flow-builder/nodeRegistry';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import { getErrorMessage } from '@/lib/api-hooks';
import { cn } from '@/lib/utils';
import type { Flow, FlowEdge, FlowNode, RingGroup } from '@/types/models';

const studioPanelClass = 'rounded-3xl border border-border/70 bg-card/90 p-4 shadow-[var(--communications-shadow-ambient)]';
const panelClass = 'rounded-2xl border border-border/70 bg-background/95 p-4 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const toolbarClass = 'absolute left-4 right-4 top-4 z-10 flex items-center justify-between gap-3 rounded-2xl border border-border/70 bg-background/92 px-4 py-3 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const overlayPanelClass = 'absolute inset-y-20 right-4 z-10 w-[340px] rounded-2xl border border-border/70 bg-background/95 p-4 shadow-[var(--communications-shadow-ambient)] backdrop-blur';
const overlayEmptyClass = 'absolute bottom-4 left-4 z-10 rounded-xl border border-border/70 bg-background/90 px-3 py-2 text-sm text-muted-foreground shadow-sm';
const libraryPanelClass = 'absolute left-4 top-20 z-10 w-[280px]';

const legacyTypeMap: Record<string, string> = {
    ivr: 'menu',
    ring_group: 'ring_team',
    terminal: 'hangup',
};

const productTypeMap: Record<string, BuilderNodeType> = {
    start: 'start',
    menu: 'ivr',
    ivr: 'ivr',
    ring_team: 'ring_group',
    ring_group: 'ring_group',
    queue: 'queue',
    transfer: 'transfer',
    hangup: 'terminal',
    terminal: 'terminal',
};

function createNode(type: BuilderNodeType, index: number): FlowNode {
    return createBuilderNode(type, index);
}

function serializeNodeForApi(node: FlowNode): FlowNode {
    return {
        ...node,
        type: legacyTypeMap[node.type] ?? node.type,
    };
}

function normalizeNodeFromApi(node: FlowNode): FlowNode {
    const normalizedType = productTypeMap[node.type] ?? 'terminal';
    const definition = getBuilderNodeDefinition(normalizedType);

    return {
        ...node,
        type: normalizedType,
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
    const { activeTenant, tenantApiPrefix } = useTenant();

    const [name, setName] = useState('Untitled Flow');
    const [description, setDescription] = useState('');
    const [nodes, setNodes] = useState<FlowNode[]>([createNode('start', 0)]);
    const [edges, setEdges] = useState<FlowEdge[]>([]);
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const [initializedFromServer, setInitializedFromServer] = useState(false);

    const { data: flow, isLoading } = useQuery<Flow | null>({
        queryKey: ['flow', activeTenant?.id, id],
        queryFn: async () => {
            if (!id) return null;
            const response = await api.get<{ data: Flow }>(`${tenantApiPrefix}/flows/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeTenant,
    });

    const { data: teamOptions = [] } = useQuery<Array<{ id: string; name: string }>>({
        queryKey: ['ring-groups', activeTenant?.id],
        queryFn: async () => {
            const response = await api.get<{ data: RingGroup[] }>(`${tenantApiPrefix}/ring-groups`);
            return response.data.data.map((team) => ({ id: team.id, name: team.name }));
        },
        enabled: !!activeTenant,
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
                return api.put(`${tenantApiPrefix}/flows/${id}`, payload);
            }

            return api.post(`${tenantApiPrefix}/flows`, payload);
        },
        onSuccess: async (response, publish) => {
            const savedFlow = response.data.data as Flow;
            await queryClient.invalidateQueries({ queryKey: ['flows', activeTenant?.id] });
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
        mutationFn: async () => api.post(`${tenantApiPrefix}/flows/${id}/publish`),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['flows', activeTenant?.id] });
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

    const selectedNode = useMemo(
        () => nodes.find((node) => String(node.id) === String(selectedNodeId)) ?? null,
        [nodes, selectedNodeId],
    );

    const selectedDefinition = selectedNode ? getBuilderNodeDefinition(selectedNode.type) : null;

    if (!activeTenant) return null;

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
                                onAddNode={(type) => {
                                    const next = createNode(type, nodes.length);
                                    setNodes((current) => [...current, next]);
                                    setSelectedNodeId(String(next.id));
                                }}
                            />
                        </div>

                        <FlowCanvas
                            nodes={nodes}
                            edges={edges}
                            selectedNodeId={selectedNodeId}
                            onNodesChange={setNodes}
                            onEdgesChange={setEdges}
                            onConnect={(connection: Connection) => {
                                if (!connection.source || !connection.target) return;
                                setEdges((current) => [
                                    ...current,
                                    {
                                        id: `edge-${Date.now()}-${current.length}`,
                                        source_node_id: String(connection.source),
                                        target_node_id: String(connection.target),
                                        condition: null,
                                    },
                                ]);
                            }}
                            onSelectNode={setSelectedNodeId}
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
                                    onChange={(updatedNode) => {
                                        setNodes((current) => current.map((node) => (
                                            String(node.id) === String(updatedNode.id) ? updatedNode : node
                                        )));
                                    }}
                                />
                            </div>
                        ) : (
                            <div className={overlayEmptyClass}>Select node to edit properties</div>
                        )}
                    </>
                )}
            </section>
        </div>
    );
}
