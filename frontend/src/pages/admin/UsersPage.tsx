import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { KeyRound, Shield, SquarePen, Trash2, User as UserIcon, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { useAuth } from '@/context/AuthContext';

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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import api from '@/lib/api';
import type { User } from '@/types/models';

export default function UsersPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { user: authUser } = useAuth();
    const [userToDelete, setUserToDelete] = useState<User | null>(null);

    const { data: users = [], isLoading } = useQuery({
        queryKey: ['users'],
        queryFn: async () => {
            const res = await api.get<{ data: User[] }>('users');
            return res.data.data;
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`users/${id}`);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['users'] });
            setUserToDelete(null);
        },
    });

    const isSuperadmin = authUser?.role === 'superadmin';

    const roleBadge = (role: string) => {
        switch (role) {
            case 'admin':
            case 'superadmin':
                return <Badge variant="default">{role}</Badge>;
            default:
                return <Badge variant="secondary">{role}</Badge>;
        }
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Users</h1>
                    <p className="text-muted-foreground">
                        {isSuperadmin
                            ? 'Manage platform users and their permissions.'
                            : 'Manage users in your organization and their permissions.'}
                    </p>
                </div>
                <Button onClick={() => navigate('/admin/users/create')}>
                    <UserPlus className="size-4" />
                    Create User
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Users</CardTitle>
                    <CardDescription>
                        {isSuperadmin
                            ? 'Users across the platform with their role assignments.'
                            : 'Users in your organization with their role assignments.'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Organization</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <div className="flex size-8 items-center justify-center rounded-full bg-primary/10">
                                                    {user.role === 'superadmin' ? (
                                                        <Shield className="size-4 text-primary" />
                                                    ) : (
                                                        <UserIcon className="size-4 text-primary" />
                                                    )}
                                                </div>
                                                {user.name}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{user.email}</TableCell>
                                        <TableCell>{roleBadge(user.role)}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.organization?.name ?? (isSuperadmin ? 'Global' : '—')}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/users/${user.id}/edit`)}
                                                >
                                                    <SquarePen className="size-4" />
                                                    <span className="sr-only">Edit user</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/users/${user.id}/permissions`)}
                                                >
                                                    <KeyRound className="size-4" />
                                                    <span className="sr-only">Manage user permissions</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setUserToDelete(user)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete user</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No users found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!userToDelete} onOpenChange={(open) => !open && setUserToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete user account?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove the user &quot;{userToDelete?.name}&quot; and revoke active tokens.
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => userToDelete && deleteMutation.mutate(String(userToDelete.id))}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete user'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
