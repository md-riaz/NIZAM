import { zodResolver } from '@hookform/resolvers/zod';
import { type AxiosError } from 'axios';
import { Loader2, Shield } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/context/AuthContext';
import { branding } from '@/lib/branding';
import { LoginRequestSchema, type ApiError, type LoginRequest } from '@/types/auth';

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [serverError, setServerError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<LoginRequest>({
        resolver: zodResolver(LoginRequestSchema),
    });

    const onSubmit = async (data: LoginRequest) => {
        setServerError(null);
        try {
            await login(data);
            navigate('/admin', { replace: true });
        } catch (err) {
            const axiosError = err as AxiosError<ApiError>;
            setServerError(
                axiosError.response?.data?.message ?? 'An unexpected error occurred.',
            );
        }
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-linear-to-br from-background via-background to-primary/5">
            {/* Ambient Glow */}
            <div className="pointer-events-none absolute -top-40 -right-40 h-125 w-125 rounded-full bg-primary/10 blur-[120px]" />
            <div className="pointer-events-none absolute -bottom-40 -left-40 h-100 w-100 rounded-full bg-primary/5 blur-[100px]" />

            <div className="relative z-10 w-full max-w-md px-4">
                {/* Logo & Brand */}
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-primary/10 shadow-lg ring-1 ring-primary/20">
                        <Shield className="size-8 text-primary" />
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground">
                        {branding.appName}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {branding.appTagline}
                    </p>
                </div>

                {/* Login Card */}
                <Card className="border-border/50 shadow-xl backdrop-blur-sm">
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">Sign In</CardTitle>
                        <CardDescription>
                            Enter your credentials to access the dashboard
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                            {serverError && (
                                <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                    {serverError}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder={branding.loginEmailPlaceholder}
                                    autoComplete="email"
                                    autoFocus
                                    aria-invalid={!!errors.email}
                                    {...register('email')}
                                />
                                {errors.email && (
                                    <p className="text-xs text-destructive">
                                        {errors.email.message}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    aria-invalid={!!errors.password}
                                    {...register('password')}
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">
                                        {errors.password.message}
                                    </p>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                size="lg"
                                disabled={isSubmitting}
                            >
                                {isSubmitting ? (
                                    <>
                                        <Loader2 className="animate-spin" />
                                        Signing in…
                                    </>
                                ) : (
                                    'Sign In'
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <p className="mt-6 text-center text-xs text-muted-foreground">
                    {branding.appName} &middot; {branding.appDescription}
                </p>
            </div>
        </div>
    );
}
