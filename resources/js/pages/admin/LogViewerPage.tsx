import { useQuery } from '@tanstack/react-query';
import {
    AlertCircle,
    Download,
    FileText,
    Radio,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';

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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import api from '@/lib/api';

// ─── Types ───────────────────────────────────────────────────

interface LogFile {
    name: string;
    path: string;
    size: number;
    modified: number;
}

interface ApplicationLogsResponse {
    source: string;
    path: string;
    lines: number;
    logs: string[];
}

interface FreeswitchLogsResponse {
    source: string;
    level: string;
    current_log_level: string;
    status: string;
    note: string;
}

// ─── Log Viewer Page ─────────────────────────────────────────

export default function LogViewerPage() {
    const [lineCount, setLineCount] = useState('100');
    const [logLevel, setLogLevel] = useState('info');

    // List available log files
    const { data: logFiles } = useQuery({
        queryKey: ['admin-logs'],
        queryFn: async () => {
            const res = await api.get<{ directory: string; files: LogFile[] }>(
                'admin/logs',
            );
            return res.data;
        },
    });

    // Fetch Laravel application logs
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

    // Fetch FreeSWITCH log status
    const {
        data: fsLogs,
        isLoading: fsLogsLoading,
        refetch: refetchFsLogs,
        error: fsLogsError,
    } = useQuery({
        queryKey: ['admin-logs-freeswitch', logLevel],
        queryFn: async () => {
            const res = await api.get<FreeswitchLogsResponse>(
                `admin/logs/freeswitch?level=${logLevel}`,
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

    return (
        <div className="space-y-6 p-6 lg:p-8">
            {/* Page Header */}
            <div>
                <p className="text-sm text-muted-foreground">
                    Platform Admin &rsaquo; System
                </p>
                <h1 className="text-2xl font-bold tracking-tight">Log Viewer</h1>
                <p className="text-muted-foreground">
                    View FreeSWITCH and Laravel application logs in real-time.
                </p>
            </div>

            {/* Log Files Overview */}
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
                                    <div>
                                        <p className="font-medium">{file.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            Modified: {formatDate(file.modified)}
                                        </p>
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

            {/* Log Viewers */}
            <Tabs defaultValue="application" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="application">
                        <FileText className="mr-2 size-4" />
                        Laravel Logs
                    </TabsTrigger>
                    <TabsTrigger value="freeswitch">
                        <Radio className="mr-2 size-4" />
                        FreeSWITCH Status
                    </TabsTrigger>
                </TabsList>

                {/* Laravel Application Logs */}
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
                                    >
                                        <RefreshCw
                                            className={`size-4 ${appLogsLoading ? 'animate-spin' : ''}`}
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
                                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                                        <span>
                                            Showing {appLogs?.lines} most recent lines
                                        </span>
                                        <Button variant="ghost" size="sm">
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

                {/* FreeSWITCH Logs */}
                <TabsContent value="freeswitch">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>FreeSWITCH Status</CardTitle>
                                    <CardDescription>
                                        Current log level and system status via ESL
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Select value={logLevel} onValueChange={setLogLevel}>
                                        <SelectTrigger className="w-32">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="console">Console</SelectItem>
                                            <SelectItem value="alert">Alert</SelectItem>
                                            <SelectItem value="crit">Critical</SelectItem>
                                            <SelectItem value="err">Error</SelectItem>
                                            <SelectItem value="warning">Warning</SelectItem>
                                            <SelectItem value="notice">Notice</SelectItem>
                                            <SelectItem value="info">Info</SelectItem>
                                            <SelectItem value="debug">Debug</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => refetchFsLogs()}
                                        disabled={fsLogsLoading}
                                    >
                                        <RefreshCw
                                            className={`size-4 ${fsLogsLoading ? 'animate-spin' : ''}`}
                                        />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {fsLogsError ? (
                                <div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                    <AlertCircle className="size-4" />
                                    Failed to connect to FreeSWITCH ESL
                                </div>
                            ) : fsLogsLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="rounded-lg border p-4">
                                            <p className="text-sm text-muted-foreground">
                                                Current Log Level
                                            </p>
                                            <p className="mt-1 text-2xl font-bold">
                                                {fsLogs?.current_log_level}
                                            </p>
                                        </div>
                                        <div className="rounded-lg border p-4">
                                            <p className="text-sm text-muted-foreground">
                                                Query Level
                                            </p>
                                            <p className="mt-1 text-2xl font-bold capitalize">
                                                {fsLogs?.level}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="rounded-lg border bg-muted/30 p-4">
                                        <p className="mb-2 text-sm font-medium">
                                            System Status
                                        </p>
                                        <div className="font-mono text-xs whitespace-pre-wrap">
                                            {fsLogs?.status}
                                        </div>
                                    </div>

                                    <div className="rounded-lg border border-blue-500/50 bg-blue-500/10 p-4 text-sm text-blue-700 dark:text-blue-300">
                                        <p className="font-medium">Note:</p>
                                        <p className="mt-1">{fsLogs?.note}</p>
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
