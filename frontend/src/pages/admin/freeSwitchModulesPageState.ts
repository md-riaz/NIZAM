import type { FreeSwitchModuleStatus } from '@/types/models';

export interface FreeSwitchModuleActionState {
    canStart: boolean;
    canStop: boolean;
    hasSafeAction: boolean;
}

export function getModuleActionState(module: FreeSwitchModuleStatus): FreeSwitchModuleActionState {
    const canStart = module.supports_start === true;
    const canStop = module.supports_stop === true;

    return {
        canStart,
        canStop,
        hasSafeAction: canStart || canStop,
    };
}
