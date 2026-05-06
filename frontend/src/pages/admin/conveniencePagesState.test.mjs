import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const frontendRoot = path.resolve(import.meta.dirname, '..', '..');
const readIfExists = (filePath) => (existsSync(filePath) ? readFileSync(filePath, 'utf8') : '');
const appFile = readIfExists(path.join(frontendRoot, 'app.tsx'));
const layoutFile = readIfExists(path.join(frontendRoot, 'layouts', 'SuperadminLayout.tsx'));
const modelsFile = readIfExists(path.join(frontendRoot, 'types', 'models.ts'));
const directoryPageFile = readIfExists(path.join(frontendRoot, 'pages', 'admin', 'DirectoryPage.tsx'));
const officeFeaturesPageFile = readIfExists(path.join(frontendRoot, 'pages', 'admin', 'OfficeFeaturesPage.tsx'));

test('frontend models define convenience feature DTO contracts', () => {
    assert.match(modelsFile, /export interface ExtensionFeatures \{/);
    assert.match(modelsFile, /follow_me_enabled: boolean;/);
    assert.match(modelsFile, /dnd_enabled: boolean;/);
    assert.match(modelsFile, /export interface OfficeFeatures \{/);
    assert.match(modelsFile, /directory_enabled: boolean;/);
});

test('admin pages exist for directory and office features', () => {
    assert.match(directoryPageFile, /export default function DirectoryPage\(/);
    assert.match(directoryPageFile, /Directory/);
    assert.match(officeFeaturesPageFile, /export default function OfficeFeaturesPage\(/);
    assert.match(officeFeaturesPageFile, /Office Features/);
});

test('admin router exposes directory and office feature pages', () => {
    assert.match(appFile, /const DirectoryPage = lazy\(\(\) => import\('\@\/pages\/admin\/DirectoryPage'\)\);/);
    assert.match(appFile, /const OfficeFeaturesPage = lazy\(\(\) => import\('\@\/pages\/admin\/OfficeFeaturesPage'\)\);/);
    assert.match(appFile, /<Route path="directory" element=\{<DirectoryPage \/>\} \/>/);
    assert.match(appFile, /<Route path="office-features" element=\{<OfficeFeaturesPage \/>\} \/>/);
});

test('sidebar navigation links to directory and office feature pages', () => {
    assert.match(layoutFile, /\{ label: 'Directory', icon: BookUser, href: '\/admin\/directory', organizationRequired: true \}/);
    assert.match(layoutFile, /\{ label: 'Office Features', icon: SlidersHorizontal, href: '\/admin\/office-features', organizationRequired: true \}/);
});
