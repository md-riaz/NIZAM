import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface RingTeamConfig {
    team_id?: string;
    timeout?: number;
    strategy?: string;
    members_text?: string;
}

interface TeamOption {
    id: string;
    name: string;
}

export function RingGroupNodeEditor({
    name,
    config,
    teamOptions,
    onNameChange,
    onConfigChange,
}: {
    name: string;
    config: RingTeamConfig;
    teamOptions: TeamOption[];
    onNameChange: (value: string) => void;
    onConfigChange: (config: RingTeamConfig) => void;
}) {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="ring-group-name">Node Name</Label>
                <Input id="ring-group-name" value={name} onChange={(event) => onNameChange(event.target.value)} />
            </div>

            <div className="space-y-2">
                <Label>Team</Label>
                <Select
                    value={config.team_id ?? ''}
                    onValueChange={(value) => onConfigChange({ ...config, team_id: value })}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select team" />
                    </SelectTrigger>
                    <SelectContent>
                        {teamOptions.map((team) => (
                            <SelectItem key={team.id} value={team.id}>
                                {team.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="ring-group-timeout">Ring Timeout</Label>
                    <Input
                        id="ring-group-timeout"
                        type="number"
                        min="1"
                        value={config.timeout ?? 30}
                        onChange={(event) => onConfigChange({ ...config, timeout: Number(event.target.value) })}
                    />
                </div>
                <div className="space-y-2">
                    <Label>Strategy</Label>
                    <Select
                        value={config.strategy ?? 'simultaneous'}
                        onValueChange={(value) => onConfigChange({ ...config, strategy: value })}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select strategy" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="simultaneous">Simultaneous</SelectItem>
                            <SelectItem value="sequence">Sequence</SelectItem>
                            <SelectItem value="enterprise">Enterprise</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="ring-group-members">Members Snapshot</Label>
                <Textarea
                    id="ring-group-members"
                    value={config.members_text ?? ''}
                    onChange={(event) => onConfigChange({ ...config, members_text: event.target.value })}
                    placeholder="1001,20,0&#10;1002,20,5"
                />
                <p className="text-xs text-muted-foreground">
                    Optional inline member snapshot for flow context. Backend team routing uses selected team ID.
                </p>
            </div>
        </div>
    );
}
