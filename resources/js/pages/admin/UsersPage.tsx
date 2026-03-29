import { useQuery } from '@tanstack/react-query';
import { UserPlus, Shield, User as UserIcon } from 'lucide-react';

import api from '@/lib/api';
import type { User } from '@/types/models';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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

export default function UsersPage() {
    const { data: users = [], isLoading } = useQuery({
        queryKey: ['users'],
        queryFn: async () => {
            const res = await api.get<{ data: User[] }>('users');
            return res.data.data;
        },
    });

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
                        Manage platform users and their permissions.
                    </p>
                </div>
                <Button>
                    <UserPlus className="size-4" />
                    Create User
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Users</CardTitle>
                    <CardDescription>
                        Users across the platform with their role assignments.
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
                                    <TableHead>Tenant</TableHead>
                                    <TableHead>Created</TableHead>
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
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>{roleBadge(user.role)}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.tenant_id ?? 'Global'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No users found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
