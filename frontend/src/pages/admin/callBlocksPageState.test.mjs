import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const frontendRoot = path.resolve(import.meta.dirname, '..', '..');
const appFile = readFileSync(path.join(frontendRoot, 'app.tsx'), 'utf8');
const layoutFile = readFileSync(path.join(frontendRoot, 'layouts', 'SuperadminLayout.tsx'), 'utf8');
const modelsFile = readFileSync(path.join(frontendRoot, 'types', 'models.ts'), 'utf8');

test('admin router exposes call block page', () => {
    assert.match(appFile, /const CallBlocksPage = lazy\(\(\) => import\('\@\/pages\/admin\/CallBlocksPage'\)\);/);
    assert.match(appFile, /<Route path="call-blocks" element=\{<CallBlocksPage \/>\} \/>/);
});

test('security navigation links to call block page', () => {
    assert.match(layoutFile, /\{ label: 'Call Block', icon: Shield, href: '\/admin\/call-blocks', adminOnly: true, organizationRequired: true \}/);
});

test('frontend models define call block resource contract', () => {
    assert.match(modelsFile, /export const CallBlockSchema = z\.object\(/);
    assert.match(modelsFile, /action: z\.literal\('reject'\)/);
    assert.match(modelsFile, /export type CallBlock = z\.infer<typeof CallBlockSchema>;/);
});
