import { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { Link } from 'react-router-dom';

interface PageHeaderProps {
    title: string;
    description?: string;
    breadcrumbs?: string;
    actionLabel?: string;
    actionRoute?: string;
    actionIcon?: ReactNode;
    onAction?: () => void;
}

export function PageHeader({
    title,
    description,
    breadcrumbs,
    actionLabel,
    actionRoute,
    actionIcon,
    onAction,
}: PageHeaderProps) {
    return (
        <div className="flex items-center justify-between">
            <div>
                {breadcrumbs && (
                    <p className="text-sm text-muted-foreground">{breadcrumbs}</p>
                )}
                <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                {description && (
                    <p className="text-muted-foreground">{description}</p>
                )}
            </div>
            {actionLabel && (actionRoute || onAction) && (
                <div className="flex items-center gap-2">
                    {actionRoute ? (
                        <Button asChild>
                            <Link to={actionRoute}>
                                {actionIcon || <Plus className="size-4 mr-2" />}
                                {actionLabel}
                            </Link>
                        </Button>
                    ) : (
                        <Button type="button" onClick={onAction}>
                            {actionIcon || <Plus className="size-4 mr-2" />}
                            {actionLabel}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}
