import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { KeyRound, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import api from '@/lib/api';

type RawToken = {
    id: string | number;
    name: string;
    abilities?: string[];
    last_used_at?: string | null;
    created_at?: string;
};

type Token = {
    id: string;
    name: string;
    abilities: string[];
    lastUsedAt: string | null;
    createdAt: string | null;
};

type TokenCreateResponse = {
    data?: {
        id: string | number;
        name?: string;
        abilities?: string[];
        created_at?: string;
    };
    plainTextToken?: string;
};

const AVAILABLE_ABILITIES = ['*', 'read', 'write', 'admin'] as const;

function normalizeToken(raw: RawToken): Token {
    return {
        id: String(raw.id),
        name: raw.name,
        abilities: Array.isArray(raw.abilities) ? raw.abilities : [],
        lastUsedAt: raw.last_used_at ?? null,
        createdAt: raw.created_at ?? null,
    };
}

export default function AuthTokensPage() {
    const queryClient = useQueryClient();
    const [tokenName, setTokenName] = useState('');
    const [selectedAbilities, setSelectedAbilities] = useState<string[]>(['*']);
    const [tokenToDelete, setTokenToDelete] = useState<Token | null>(null);
    const [createError, setCreateError] = useState<string | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [createdTokenSecret, setCreatedTokenSecret] = useState<string | null>(null);
    const tokenSecretRef = useRef<HTMLParagraphElement>(null);

    const { data: tokens = [], isLoading } = useQuery({
        queryKey: ['auth-tokens'],
        queryFn: async () => {
            const res = await api.get<{ data: RawToken[] }>('auth/tokens');
            return (res.data.data ?? []).map(normalizeToken);
        },
    });

    const orderedTokens = useMemo(
        () => [...tokens].sort((a, b) => (b.createdAt ?? '').localeCompare(a.createdAt ?? '')),
        [tokens],
    );

    const createMutation = useMutation({
        mutationFn: async () => {
            const payload = {
                name: tokenName.trim(),
                abilities: selectedAbilities,
            };
            const res = await api.post<TokenCreateResponse>('auth/tokens', payload);
            return res.data;
        },
        onSuccess: async (data) => {
            setTokenName('');
            setSelectedAbilities(['*']);
            setCreateError(null);
            setDeleteError(null);
            setCreatedTokenSecret(data.plainTextToken ?? null);
            await queryClient.invalidateQueries({ queryKey: ['auth-tokens'] });
            window.setTimeout(() => tokenSecretRef.current?.focus(), 0);
        },
        onError: (error: any) => {
            const message =
                error?.response?.data?.message ??
                error?.message ??
                'Failed to create API token.';
            setCreateError(message);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (tokenId: string) => {
            await api.delete(`auth/tokens/${tokenId}`);
        },
        onSuccess: async () => {
            setTokenToDelete(null);
            setDeleteError(null);
            await queryClient.invalidateQueries({ queryKey: ['auth-tokens'] });
        },
        onError: (error: any) => {
            const message =
                error?.response?.data?.message ??
                error?.message ??
                'Failed to delete API token.';
            setDeleteError(message);
        },
    });

    const toggleAbility = (ability: string, checked: boolean) => {
        setSelectedAbilities((current) => {
            if (ability === '*') {
                return checked ? ['*'] : [];
            }

            const next = checked
                ? [...current.filter((item) => item !== '*'), ability]
                : current.filter((item) => item !== ability);

            return next;
        });
    };

    const canCreate = tokenName.trim().length > 0 && selectedAbilities.length > 0;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; Authentication</p>
                <h1 className="text-2xl font-bold tracking-tight">API Tokens</h1>
                <p className="text-muted-foreground">
                    Issue and revoke personal API tokens for your signed-in account.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Create token</CardTitle>
                    <CardDescription>
                        Generate a personal access token and copy it immediately.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="token-name">Token name *</Label>
                        <Input
                            id="token-name"
                            value={tokenName}
                            onChange={(event) => setTokenName(event.target.value)}
                            placeholder="e.g. CI integration"
                            aria-required={true}
                            aria-invalid={createError ? true : undefined}
                            aria-describedby={createError ? 'token-create-error' : undefined}
                        />
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Abilities *</legend>
                        <p className="text-sm text-muted-foreground">
                            Choose what this token can access.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {AVAILABLE_ABILITIES.map((ability) => {
                                const fieldId = `ability-${ability.replace(/[^a-z0-9*]/gi, '-')}`;
                                const checked = selectedAbilities.includes(ability);
                                return (
                                    <div key={ability} className="flex items-start gap-3 rounded-md border p-3">
                                        <Checkbox
                                            id={fieldId}
                                            checked={checked}
                                            onCheckedChange={(nextChecked) =>
                                                toggleAbility(ability, nextChecked === true)
                                            }
                                            aria-label={`Grant ${ability} ability`}
                                        />
                                        <Label htmlFor={fieldId} className="cursor-pointer">
                                            {ability === '*' ? 'Full access (*)' : ability}
                                        </Label>
                                    </div>
                                );
                            })}
                        </div>
                    </fieldset>

                    {createError && (
                        <div
                            id="token-create-error"
                            className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            {createError}
                        </div>
                    )}

                    {createdTokenSecret && (
                        <div className="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4">
                            <p className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                Copy token now
                            </p>
                            <p
                                ref={tokenSecretRef}
                                tabIndex={-1}
                                className="break-all rounded-md bg-background px-3 py-2 font-mono text-xs"
                                aria-live="polite"
                            >
                                {createdTokenSecret}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                This value is shown once. Store it securely.
                            </p>
                        </div>
                    )}

                    <div className="flex justify-end">
                        <Button
                            onClick={() => createMutation.mutate()}
                            disabled={createMutation.isPending || !canCreate}
                        >
                            <Plus className="size-4" />
                            {createMutation.isPending ? 'Creating…' : 'Create token'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Issued tokens</CardTitle>
                    <CardDescription>
                        {orderedTokens.length} active token{orderedTokens.length === 1 ? '' : 's'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-24 items-center justify-center">
                            <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Abilities</TableHead>
                                    <TableHead>Last used</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orderedTokens.map((token) => (
                                    <TableRow key={token.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <KeyRound className="size-4 text-muted-foreground" />
                                                {token.name}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {(token.abilities.length ? token.abilities : ['(none)']).map((ability) => (
                                                    <Badge key={`${token.id}-${ability}`} variant={ability === '*' ? 'default' : 'secondary'}>
                                                        {ability}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {token.lastUsedAt ? new Date(token.lastUsedAt).toLocaleString() : 'Never'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {token.createdAt ? new Date(token.createdAt).toLocaleDateString() : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                onClick={() => {
                                                    setDeleteError(null);
                                                    setTokenToDelete(token);
                                                }}
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete token</span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {orderedTokens.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-20 text-center text-muted-foreground">
                                            No API tokens issued yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}

                    {deleteError && (
                        <div className="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            {deleteError}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <ShieldCheck className="size-4" />
                        Token handling guidance
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>Create separate tokens per integration or environment.</li>
                        <li>Use only required abilities and revoke unused tokens.</li>
                        <li>Store token values in secure secret storage.</li>
                    </ul>
                </CardContent>
            </Card>

            <AlertDialog open={!!tokenToDelete} onOpenChange={(open) => !open && setTokenToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete API token?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will revoke token &quot;{tokenToDelete?.name}&quot; immediately.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => tokenToDelete && deleteMutation.mutate(tokenToDelete.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete token'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
