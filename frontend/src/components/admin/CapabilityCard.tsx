import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export interface Capability {
    id: string;
    name: string;
    description: string;
    status: 'active' | 'inactive';
    category: string;
}

interface CapabilityCardProps {
    capability: Capability;
}

export default function CapabilityCard({ capability }: CapabilityCardProps) {
    const isActive = capability.status === 'active';

    return (
        <Card className="h-full border-border/70 transition-colors hover:border-primary/40">
            <CardHeader className="space-y-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                        <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
                            {capability.category}
                        </Badge>
                        <CardTitle className="text-lg leading-snug">{capability.name}</CardTitle>
                    </div>
                    <Badge variant={isActive ? 'success' : 'secondary'}>
                        {isActive ? 'Active' : 'Inactive'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <CardDescription className="text-sm leading-6 text-muted-foreground">
                    {capability.description}
                </CardDescription>
            </CardContent>
        </Card>
    );
}
