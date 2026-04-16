import { Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface IvrOption {
    digit: string;
    label?: string;
}

interface IvrConfig {
    greeting?: string;
    short_greeting?: string;
    timeout?: number;
    max_failures?: number;
    options?: IvrOption[];
}

export function IvrNodeEditor({
    name,
    config,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: IvrConfig;
    onNameChange: (value: string) => void;
    onConfigChange: (config: IvrConfig) => void;
}) {
    const options = config.options ?? [];

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="ivr-name">Node Name</Label>
                <Input id="ivr-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="ivr-greeting">Greeting</Label>
                <Textarea
                    id="ivr-greeting"
                    value={config.greeting ?? ''}
                    onChange={(event) => onConfigChange({ ...config, greeting: event.target.value })}
                    placeholder="Thank you for calling. Press 1 for sales, 2 for support."
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="ivr-short-greeting">Short Greeting</Label>
                <Input
                    id="ivr-short-greeting"
                    value={config.short_greeting ?? ''}
                    onChange={(event) => onConfigChange({ ...config, short_greeting: event.target.value })}
                    placeholder="Press 1 for sales"
                />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="ivr-timeout">Timeout (seconds)</Label>
                    <Input
                        id="ivr-timeout"
                        type="number"
                        min="1"
                        value={config.timeout ?? 5}
                        onChange={(event) => onConfigChange({ ...config, timeout: Number(event.target.value) })}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="ivr-failures">Max Failures</Label>
                    <Input
                        id="ivr-failures"
                        type="number"
                        min="1"
                        value={config.max_failures ?? 3}
                        onChange={(event) => onConfigChange({ ...config, max_failures: Number(event.target.value) })}
                    />
                </div>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <Label>Digit Options</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onConfigChange({
                            ...config,
                            options: [...options, { digit: '', label: '' }],
                        })}
                    >
                        <Plus className="mr-2 size-4" />
                        Add Option
                    </Button>
                </div>
                <div className="space-y-2">
                    {options.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            Add options, then connect this node using edge conditions like `digit_1`, `digit_2`, `timeout`, or `invalid`.
                        </p>
                    ) : (
                        options.map((option, index) => (
                            <div key={`${index}-${option.digit}`} className="grid gap-2 sm:grid-cols-[80px_1fr_auto]">
                                <Input
                                    value={option.digit}
                                    placeholder="1"
                                    onChange={(event) => {
                                        const nextOptions = [...options];
                                        nextOptions[index] = { ...option, digit: event.target.value };
                                        onConfigChange({ ...config, options: nextOptions });
                                    }}
                                />
                                <Input
                                    value={option.label ?? ''}
                                    placeholder="Sales"
                                    onChange={(event) => {
                                        const nextOptions = [...options];
                                        nextOptions[index] = { ...option, label: event.target.value };
                                        onConfigChange({ ...config, options: nextOptions });
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        const nextOptions = options.filter((_, optionIndex) => optionIndex !== index);
                                        onConfigChange({ ...config, options: nextOptions });
                                    }}
                                >
                                    <Trash2 className="size-4 text-destructive" />
                                </Button>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
