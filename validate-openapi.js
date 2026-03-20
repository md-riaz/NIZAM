import path from 'node:path';
import { fileURLToPath } from 'node:url';
import SwaggerParser from '@apidevtools/swagger-parser';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const specPath = path.join(__dirname, 'docs', 'openapi.yaml');

try {
  const api = await SwaggerParser.validate(specPath);
  console.log('✅ OpenAPI spec is valid');
  console.log(`Title: ${api.info.title}`);
  console.log(`Version: ${api.info.version}`);
  console.log(`Paths: ${Object.keys(api.paths).length}`);
  console.log(`Tags: ${(api.tags || []).map(t => t.name).join(', ')}`);
  process.exit(0);
} catch (err) {
  console.error('❌ OpenAPI spec validation failed:');
  console.error(err?.message || err);
  process.exit(1);
}
