import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

const teamStrategies = ['simultaneous', 'round_robin', 'priority'] as const;

const teamSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    strategy: z.enum(teamStrategies),
    timeout: z.coerce.number().min(1).max(300),
    schedule_id: z.string().optional(),
    holiday_calendar_id: z.string().optional(),
    is_active: z.boolean(),
});

type TeamFormValues = z.infer<typeof teamSchema>;

export default function TeamFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id && id !== 'new');
    const navigate = useNavigate();
    const { activeOrganization } = useOrganization();

    const form = useForm<TeamFormValues>({
        resolver: zodResolver(teamSchema),
        defaultValues: {
            name: '',
            strategy: 'simultaneous',
            timeout: 30,
            schedule_id: 'none',
            holiday_calendar_id: 'none',
            is_active: true,
        },
    });

    const { data: team, isLoading: isFetching } = useQuery({
        queryKey: ['team', activeOrganization?.id, id],
        queryFn: async () => {
            if (!activeOrganization) return null;
            const response = await api.get(`organizations/${activeOrganization.id}/teams/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeOrganization,
    });

    const { data: schedules = [] } = useQuery({
        queryKey: ['schedules', activeOrganization?.id, 'team-routing-options'],
        queryFn: async () => {
            if (!activeOrganization) return [] as Array<{ id: string; name: string }>;
            const response = await api.get<{ data: Array<{ id: string; name: string }> }>(`organizations/${activeOrganization.id}/schedules`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const { data: holidayCalendars = [] } = useQuery({
        queryKey: ['holiday-calendars', activeOrganization?.id, 'team-routing-options'],
        queryFn: async () => {
            if (!activeOrganization) return [] as Array<{ id: string; name: string }>;
            const response = await api.get<{ data: Array<{ id: string; name: string }> }>(`organizations/${activeOrganization.id}/holiday-calendars`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    useEffect(() => {
        if (team) {
            form.reset({
                name: team.name ?? '',
                strategy: (team.strategy as any) ?? 'simultaneous',
                timeout: team.timeout ?? 30,
                schedule_id: team.schedule_id ?? 'none',
                holiday_calendar_id: team.holiday_calendar_id ?? 'none',
                is_active: team.is_active ?? true,
            });
        }
    }, [team, form]);

    const mutation = useApiMutation({
        mutationFn: async (values: TeamFormValues) => {
            if (!activeOrganization) throw new Error('No active organization');
            const payload = {
                ...values,
                schedule_id: values.schedule_id && values.schedule_id !== 'none' ? values.schedule_id : null,
                holiday_calendar_id: values.holiday_calendar_id && values.holiday_calendar_id !== 'none' ? values.holiday_calendar_id : null,
            };
            if (isEdit) {
                return api.put(`organizations/${activeOrganization.id}/teams/${id}`, payload);
            }
            return api.post(`organizations/${activeOrganization.id}/teams`, payload);
        },
        successMessage: `Team ${isEdit ? 'updated' : 'created'} successfully`,
        invalidateQueries: [['teams', activeOrganization?.id || '']],
        onSuccess: () => navigate('/admin/teams'),
    });

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title={isEdit ? 'Edit Team' : 'Create Team'}
                breadcrumbs="Platform administration"
                actionLabel="Back to Teams"
                actionRoute="/admin/teams"
                actionIcon={null}
            />

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Team Profile</CardTitle>
                    <CardDescription>
                        Configure this team's inbound routing strategy and schedule.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isFetching ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Form {...form}>
                            <form
                                onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Team Name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Sales Team" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="strategy"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Strategy</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (!teamStrategies.includes(value as (typeof teamStrategies)[number])) {
                                                            return;
                                                        }

                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select strategy" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {teamStrategies.map((strategy) => (
                                                            <SelectItem key={strategy} value={strategy}>
                                                                {strategy.replace('_', ' ')}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    How inbound calls to this team are distributed.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="timeout"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Ring Timeout (seconds)</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="1" max="300" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="schedule_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Schedule</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value || 'none'}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select schedule" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="none">No schedule</SelectItem>
                                                        {schedules.map((schedule) => (
                                                            <SelectItem key={schedule.id} value={schedule.id}>
                                                                {schedule.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    Optional inbound schedule for this team.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="holiday_calendar_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Holiday calendar</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value || 'none'}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select holiday calendar" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="none">No holiday calendar</SelectItem>
                                                        {holidayCalendars.map((holidayCalendar) => (
                                                            <SelectItem key={holidayCalendar.id} value={holidayCalendar.id}>
                                                                {holidayCalendar.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    Optional holiday calendar for inbound routing overrides.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="is_active"
                                    render={({ field }) => (
                                        <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                            <FormControl>
                                                <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                                            </FormControl>
                                            <div className="space-y-1 leading-none">
                                                <FormLabel>Active Status</FormLabel>
                                                <FormDescription>
                                                    Inactive teams cannot receive calls.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Team'}
                                    </Button>
                                </div>
                            </form>
                        </Form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
