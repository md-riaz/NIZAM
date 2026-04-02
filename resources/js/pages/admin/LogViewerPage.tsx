import { useQuery } from '@tanstack/react-query';
import {
    AlertCircle,
    ArrowDownZA,
    ArrowUpAZ,
    Download,
    FileText,
    Radio,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import api from '@/lib/api';

interface LogFile {
    name: string;
    path: string;
    size: number;
    modified: number;
    type?: 'laravel' | 'freeswitch';
}

interface ApplicationLogsResponse {
    source: string;
    path: string;
    lines: number;
    logs: string[];
}

interface FreeswitchLogLine {
    number: number;
    text: string;
}

interface FreeswitchLogsResponse {
    source: string;
    path: string;
    size_kb: number;
    filter: string;
    sort: 'asc' | 'desc';
    lines: number;
    logs: FreeswitchLogLine[];
}

const SIZE_OPTIONS = ['32', '64', '128', '256', '512', '1024', '2048', '4096'];

export default function LogViewerPage() {
    const [lineCount, setLineCount] = useState('100');
    const [filter, setFilter] = useState('');
    const [debouncedFilter, setDebouncedFilter] = useState('');
    const [logSizeKb, setLogSizeKb] = useState('256');
    const [sort, setSort] = useState<'asc' | 'desc'>('desc');

    const { data: logFiles } = useQuery({
        queryKey: ['admin-logs'],
        queryFn: async () => {
            const res = await api.get<{ directory: string; files: LogFile[] }>(
                'admin/logs',
            );
            return res.data;
        },
    });

    const {
        data: appLogs,
        isLoading: appLogsLoading,
        refetch: refetchAppLogs,
        error: appLogsError,
    } = useQuery({
        queryKey: ['admin-logs-application', lineCount],
        queryFn: async () => {
            const res = await api.get<ApplicationLogsResponse>(
                `admin/logs/application?lines=${lineCount}`,
            );
            return res.data;
        },
    });

    const {
        data: fsLogs,
        isLoading: fsLogsLoading,
        refetch: refetchFsLogs,
        error: fsLogsError,
    } = useQuery({
        queryKey: ['admin-logs-freeswitch', debouncedFilter, logSizeKb, sort],
        queryFn: async () => {
            const params = new URLSearchParams({
                size_kb: logSizeKb,
                sort,
            });

            if (debouncedFilter.trim() !== '') {
                params.set('filter', debouncedFilter.trim());
            }

            const res = await api.get<FreeswitchLogsResponse>(
                `admin/logs/freeswitch?${params.toString()}`,
            );
            return res.data;
        },
    });

    const formatFileSize = (bytes: number) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const formatDate = (timestamp: number) => {
        return new Date(timestamp * 1000).toLocaleString();
    };

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            setDebouncedFilter(filter);
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [filter]);

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">
                    Platform Admin &rsaquo; System
                </p>
                <h1 className="text-2xl font-bold tracking-tight">Log Viewer</h1>
                <p className="text-muted-foreground leading-relaxed">
                    View FreeSWITCH and Laravel application logs.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="size-5" />
                        Available Log Files
                    </CardTitle>
                    <CardDescription>
                        Log files stored in {logFiles?.directory}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {logFiles && logFiles.files.length > 0 ? (
                        <div className="space-y-2">
                            {logFiles.files.map((file) => (
                                <div
                                    key={file.path}
                                    className="flex items-center justify-between rounded-lg border px-4 py-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className={`rounded-full p-2 ${file.type === 'freeswitch' ? 'bg-blue-500/10 text-blue-500' : 'bg-muted text-muted-foreground'}`}>
                                            {file.type === 'freeswitch' ? <Radio className="size-4" /> : <FileText className="size-4" />}
                                        </div>
                                        <div>
                                            <p className="font-medium">{file.name}</p>
                                            <p className="text-xs text-muted-foreground font-mono">
                                                {file.path}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                Modified: {formatDate(file.modified)}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge variant="secondary">
                                        {formatFileSize(file.size)}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center text-sm text-muted-foreground">
                            No log files found.
                        </div>
                    )}
                </CardContent>
            </Card>

            <Tabs defaultValue="application" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="application">
                        <FileText className="mr-2 size-4" />
                        Laravel Logs
                    </TabsTrigger>
                    <TabsTrigger value="freeswitch">
                        <Radio className="mr-2 size-4" />
                        FreeSWITCH Logs
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="application">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Laravel Application Logs</CardTitle>
                                    <CardDescription>
                                        Recent log entries from {appLogs?.path}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={lineCount}
                                        onValueChange={setLineCount}
                                    >
                                        <SelectTrigger className="w-32">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="50">50 lines</SelectItem>
                                            <SelectItem value="100">100 lines</SelectItem>
                                            <SelectItem value="250">250 lines</SelectItem>
                                            <SelectItem value="500">500 lines</SelectItem>
                                            <SelectItem value="1000">1000 lines</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => refetchAppLogs()}
                                        disabled={appLogsLoading}
                                        aria-label="Refresh application logs"
                                        className="cursor-pointer"
                                    >
                                        <RefreshCw
                                            className={`size-4 ${appLogsLoading ? 'motion-safe:animate-spin' : ''}`}
                                        />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {appLogsError ? (
                                <div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                    <AlertCircle className="size-4" />
                                    Failed to load application logs
                                </div>
                            ) : appLogsLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading application logs" />
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                                        <span>
                                            Showing {appLogs?.lines} most recent lines
                                        </span>
                                        <Button variant="ghost" size="sm" aria-label="Download logs" className="cursor-pointer">
                                            <Download className="mr-2 size-4" />
                                            Download
                                        </Button>
                                    </div>
                                    <div className="max-h-[600px] overflow-auto rounded-lg border bg-muted/30 p-4 font-mono text-xs">
                                        {appLogs?.logs.map((line, idx) => (
                                            <div
                                                key={idx}
                                                className="whitespace-pre-wrap break-all py-0.5 hover:bg-accent/50"
                                            >
                                                {line}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="freeswitch">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle>FreeSWITCH Logs</CardTitle>
                                    <CardDescription>
                                        Reading from {fsLogs?.path ?? 'configured FreeSWITCH log file'}
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="relative min-w-64 flex-1">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={filter}
                                            onChange={(event) => setFilter(event.target.value)}
                                            placeholder="Filter by any string"
                                            className="pl-9"
                                            aria-label="Filter FreeSWITCH logs"
                                        />
                                    </div>
                                    <Select value={logSizeKb} onValueChange={setLogSizeKb}>
                                        <SelectTrigger className="w-36">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SIZE_OPTIONS.map((size) => (
                                                <SelectItem key={size} value={size}>
                                                    {size} KB
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSort(sort === 'desc' ? 'asc' : 'desc')}
                                        aria-label="Toggle FreeSWITCH log sort"
                                        className="cursor-pointer"
                                    >
                                        {sort === 'desc' ? (
                                            <ArrowDownZA className="size-4" />
                                        ) : (
                                            <ArrowUpAZ className="size-4" />
                                        )}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => refetchFsLogs()}
                                        disabled={fsLogsLoading}
                                        aria-label="Refresh FreeSWITCH logs"
                                        className="cursor-pointer"
                                    >
                                        <RefreshCw
                                            className={`size-4 ${fsLogsLoading ? 'motion-safe:animate-spin' : ''}`}
                                        />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {fsLogsError ? (
                                <div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                    <AlertCircle className="size-4" />
                                    Failed to load FreeSWITCH logs
                                </div>
                            ) : fsLogsLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading FreeSWITCH logs" />
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                        <Badge variant="secondary">{fsLogs?.size_kb} KB window</Badge>
                                        <Badge variant="secondary">{fsLogs?.sort === 'desc' ? 'Newest first' : 'Oldest first'}</Badge>
                                        <Badge variant="secondary">{fsLogs?.lines ?? 0} lines</Badge>
                                        {fsLogs?.filter ? (
                                            <Badge variant="outline">Filter: {fsLogs.filter}</Badge>
                                        ) : null}
                                    </div>
                                    <div className="max-h-[600px] overflow-auto rounded-lg border bg-muted/30 font-mono text-xs">
                                        {fsLogs?.logs.length ? (
                                            fsLogs.logs.map((line) => (
                                                <div
                                                    key={`${line.number}-${line.text}`}
                                                    className="grid grid-cols-[96px_minmax(0,1fr)] gap-3 border-b px-4 py-2 last:border-b-0 hover:bg-accent/50"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {line.number}
                                                    </span>
                                                    <span className="whitespace-pre-wrap break-all">
                                                        {line.text}
                                                    </span>
                                                </div>
                                            ))
                                        ) : (
                                            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                                                No matching FreeSWITCH log lines found.
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}
