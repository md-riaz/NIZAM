import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const backendRoot = path.resolve(import.meta.dirname, '..', '..');
const openApiFile = readFileSync(path.join(backendRoot, 'docs', 'openapi.yaml'), 'utf8');
const apiReferenceFile = readFileSync(path.join(backendRoot, 'docs', 'api-reference.md'), 'utf8');

test('openapi documents convenience feature schemas and paths', () => {
    assert.match(openApiFile, /\/organizations\/\{organizationId\}\/directory:/);
    assert.match(openApiFile, /\/organizations\/\{organizationId\}\/office-features:/);
    assert.match(openApiFile, /\/organizations\/\{organizationId\}\/extensions\/\{extensionId\}\/features:/);
    assert.match(openApiFile, /ExtensionFeatures:/);
    assert.match(openApiFile, /OfficeFeatures:/);
});

test('api reference documents convenience feature endpoints', () => {
    assert.match(apiReferenceFile, /## Organization Directory/);
    assert.match(apiReferenceFile, /GET \/api\/v1\/organizations\/\{organization\}\/directory/);
    assert.match(apiReferenceFile, /## Office Features/);
    assert.match(apiReferenceFile, /GET \/api\/v1\/organizations\/\{organization\}\/office-features/);
    assert.match(apiReferenceFile, /PUT \/api\/v1\/organizations\/\{organization\}\/office-features/);
    assert.match(apiReferenceFile, /## Extension Convenience Features/);
    assert.match(apiReferenceFile, /PUT \/api\/v1\/organizations\/\{organization\}\/extensions\/\{extension\}\/features/);
});
