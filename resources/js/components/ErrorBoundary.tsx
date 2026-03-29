import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
    errorInfo: ErrorInfo | null;
}

class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
        };
    }

    static getDerivedStateFromError(error: Error): State {
        return {
            hasError: true,
            error,
            errorInfo: null,
        };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('ErrorBoundary caught an error:', error, errorInfo);
        this.setState({
            error,
            errorInfo,
        });
    }

    handleReset = () => {
        this.setState({
            hasError: false,
            error: null,
            errorInfo: null,
        });
    };

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div className="flex min-h-screen items-center justify-center bg-background p-4">
                    <Card className="w-full max-w-2xl">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-destructive/10 p-2">
                                    <AlertCircle className="size-6 text-destructive" />
                                </div>
                                <div>
                                    <CardTitle>Something went wrong</CardTitle>
                                    <CardDescription>
                                        An error occurred while rendering this component
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {this.state.error && (
                                <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                                    <p className="mb-2 text-sm font-medium text-destructive">
                                        Error Message:
                                    </p>
                                    <p className="font-mono text-sm text-destructive">
                                        {this.state.error.toString()}
                                    </p>
                                </div>
                            )}

                            {this.state.errorInfo && (
                                <details className="rounded-lg border p-4">
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Component Stack Trace
                                    </summary>
                                    <pre className="mt-2 overflow-auto text-xs text-muted-foreground">
                                        {this.state.errorInfo.componentStack}
                                    </pre>
                                </details>
                            )}

                            <div className="flex gap-2">
                                <Button onClick={this.handleReset} variant="default">
                                    <RefreshCw className="mr-2 size-4" />
                                    Try Again
                                </Button>
                                <Button
                                    onClick={() => window.location.reload()}
                                    variant="outline"
                                >
                                    Reload Page
                                </Button>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                If this problem persists, please contact support or check the
                                browser console for more details.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
