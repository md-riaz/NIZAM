import { useQuery } from '@tanstack/react-query';
import { Save, SlidersHorizontal } from 'lucide-react';
import { useEffect, useState } from 'react';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { OfficeFeatures } from '@/types/models';

const EMPTY_FEATURES: OfficeFeatures = {
    parking_enabled: false,
    pickup_enabled: false,
    paging_enabled: false,
    intercom_enabled: false,
    directory_enabled: false,
};

const FEATURE_FIELDS: Array<{
    key: keyof OfficeFeatures;
    label: string;
    description: string;
}> = [
    {
        key: 'parking_enabled',
        label: 'Call parking',
        description: 'Allow shared parking slots for parked calls.',
    },
    {
        key: 'pickup_enabled',
        label: 'Call pickup',
        description: 'Allow users to pick up ringing calls for other extensions.',
    },
    {
        key: 'paging_enabled',
        label: 'Paging',
        description: 'Enable organization-wide paging entrypoints.',
    },
    {
        key: 'intercom_enabled',
        label: 'Intercom',
        description: 'Allow direct intercom style calling where supported.',
    },
    {
        key: 'directory_enabled',
        label: 'Directory',
        description: 'Expose directory and dial-by-name access for organization.',
    },
];

export default function OfficeFeaturesPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const [formState, setFormState] = useState<OfficeFeatures>(EMPTY_FEATURES);

    const { data: officeFeatures, isLoading } = useQuery<OfficeFeatures>({
        queryKey: ['office-features', activeOrganization?.id],
        queryFn: async () => {
            const response = await api.get<{ data: OfficeFeatures }>(`${organizationApiPrefix}/office-features`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    useEffect(() => {
        if (officeFeatures) {
            setFormState(officeFeatures);
        }
    }, [officeFeatures]);

    const saveMutation = useApiMutation<unknown, OfficeFeatures>({
        mutationFn: async (values) => {
            const response = await api.put(`${organizationApiPrefix}/office-features`, values);
            return response.data;
        },
        successMessage: 'Office features updated successfully',
        invalidateQueries: [['office-features', activeOrganization?.id || '']],
    });

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Office Features"
                description="Control shared organization phone features."
                breadcrumbs={`${activeOrganization.name} › Phone System`}
            />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SlidersHorizontal className="size-5 text-primary" />
                        Shared feature toggles
                    </CardTitle>
                    <CardDescription>
                        Enable or disable organization-wide business phone convenience features.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {FEATURE_FIELDS.map((feature) => (
                        <label key={feature.key} className="flex items-start gap-3 rounded-lg border p-4">
                            <Checkbox
                                checked={formState[feature.key]}
                                onCheckedChange={(checked) =>
                                    setFormState((current) => ({
                                        ...current,
                                        [feature.key]: checked === true,
                                    }))
                                }
                                disabled={isLoading || saveMutation.isPending}
                            />
                            <div className="space-y-1">
                                <div className="font-medium">{feature.label}</div>
                                <p className="text-sm text-muted-foreground">{feature.description}</p>
                            </div>
                        </label>
                    ))}

                    <div className="flex justify-end">
                        <Button
                            type="button"
                            onClick={() => saveMutation.mutate(formState)}
                            disabled={isLoading || saveMutation.isPending}
                        >
                            <Save className="mr-2 size-4" />
                            {saveMutation.isPending ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
