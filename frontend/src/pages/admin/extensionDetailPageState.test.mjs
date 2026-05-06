import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const frontendRoot = path.resolve(import.meta.dirname, '..', '..');
const extensionDetailFile = readFileSync(path.join(frontendRoot, 'pages', 'admin', 'ExtensionDetailPage.tsx'), 'utf8');

test('extension detail page renders follow me summary badges', () => {
    assert.match(extensionDetailFile, /Follow me/);
    assert.match(extensionDetailFile, /follow_me_enabled \? 'Enabled' : 'Disabled'/);
    assert.match(extensionDetailFile, /follow_me_destination \?\? '—'/);
});

test('extension detail page renders DND summary badge', () => {
    assert.match(extensionDetailFile, /Do not disturb/);
    assert.match(extensionDetailFile, /dnd_enabled \? 'Enabled' : 'Disabled'/);
});
