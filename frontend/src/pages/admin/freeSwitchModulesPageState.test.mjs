import assert from 'node:assert/strict';
import test from 'node:test';

function getModuleActionState(module) {
    const canStart = module.supports_start === true;
    const canStop = module.supports_stop === true;

    return {
        canStart,
        canStop,
        hasSafeAction: canStart || canStop,
    };
}

test('returns a start action for not loaded modules', () => {
    assert.deepEqual(
        getModuleActionState({
            name: 'mod_xml_curl',
            type: 'xml_handler',
            status: 'not_loaded',
            supports_start: true,
            supports_stop: false,
        }),
        {
            canStart: true,
            canStop: false,
            hasSafeAction: true,
        },
    );
});

test('returns a stop action only for safely stoppable running modules', () => {
    assert.deepEqual(
        getModuleActionState({
            name: 'mod_avmd',
            type: 'application',
            status: 'running',
            supports_start: false,
            supports_stop: true,
        }),
        {
            canStart: false,
            canStop: true,
            hasSafeAction: true,
        },
    );
});

test('returns no action for protected running modules', () => {
    assert.deepEqual(
        getModuleActionState({
            name: 'mod_sofia',
            type: 'endpoint',
            status: 'running',
            supports_start: false,
            supports_stop: false,
        }),
        {
            canStart: false,
            canStop: false,
            hasSafeAction: false,
        },
    );
});
