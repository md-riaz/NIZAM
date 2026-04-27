import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { SystemMedia } from '@/types/models';

interface PromptMediaInputProps {
    promptId: string;
    mediaId: string;
    promptLabel?: string;
    mediaLabel?: string;
    promptPlaceholder?: string;
    mediaPlaceholder?: string;
    promptValue: string;
    selectedMediaId: string;
    mediaOptions: SystemMedia[];
    onPromptChange: (value: string) => void;
    onMediaChange: (mediaId: string, resolvedPrompt: string) => void;
}

function resolveMediaPrompt(media: SystemMedia | undefined): string {
    if (!media) return '';
    return media.path ?? `recordings/${media.id}/${media.file_name}`;
}

export function PromptMediaInput({
    promptId,
    mediaId,
    promptLabel = 'Prompt',
    mediaLabel = 'Media Asset',
    promptPlaceholder,
    mediaPlaceholder = 'Select uploaded media',
    promptValue,
    selectedMediaId,
    mediaOptions,
    onPromptChange,
    onMediaChange,
}: PromptMediaInputProps) {
    return (
        <>
            <div className="space-y-2">
                <Label htmlFor={mediaId}>{mediaLabel}</Label>
                <Select
                    value={selectedMediaId || '__manual__'}
                    onValueChange={(value) => {
                        if (value === '__manual__') {
                            onMediaChange('', promptValue);
                            return;
                        }

                        const media = mediaOptions.find((item) => String(item.id) === value);
                        onMediaChange(value, resolveMediaPrompt(media));
                    }}
                >
                    <SelectTrigger id={mediaId}>
                        <SelectValue placeholder={mediaPlaceholder} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__manual__">Manual prompt</SelectItem>
                        {mediaOptions.map((media) => (
                            <SelectItem key={media.id} value={String(media.id)}>
                                {media.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {selectedMediaId && (
                <div className="space-y-2">
                    <Label htmlFor={`${mediaId}-value`}>Media ID</Label>
                    <Input id={`${mediaId}-value`} value={selectedMediaId} readOnly />
                </div>
            )}

            <div className="space-y-2">
                <Label htmlFor={promptId}>{promptLabel}</Label>
                <Textarea
                    id={promptId}
                    value={promptValue}
                    onChange={(event) => onPromptChange(event.target.value)}
                    placeholder={promptPlaceholder}
                />
            </div>
        </>
    );
}
