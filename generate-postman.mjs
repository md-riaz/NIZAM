import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const openapiPath = path.join(__dirname, 'docs', 'openapi.yaml');
const postmanPath = path.join(__dirname, 'docs', 'postman-collection.json');

// Read the OpenAPI spec
const openapiContent = fs.readFileSync(openapiPath, 'utf8');

// Generate Postman collection from OpenAPI
const postmanCollection = {
  info: {
    name: 'NIZAM API',
    description: 'NIZAM — Open Communications Control Platform API. Multi-organization VoIP/PBX platform built on FreeSWITCH + Laravel.',
    version: '1.0.1',
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
  },
  auth: {
    type: 'bearer',
    bearer: [{
      key: 'token',
      value: '{{api_token}}',
      type: 'string'
    }]
  },
  variable: [
    { key: 'base_url', value: 'http://localhost:8231/api/v1' },
    { key: 'api_token', value: '' },
    { key: 'organization_id', value: '' }
  ],
  item: []
};

// Parse OpenAPI spec manually (simple YAML parser for our structure)
const lines = openapiContent.split('\n');
let currentPath = null;
let currentMethod = null;
const paths = {};

for (let i = 0; i < lines.length; i++) {
  const line = lines[i];
  const pathMatch = line.match(/^  \/([^:]+):$/);
  if (pathMatch) {
    currentPath = '/' + pathMatch[1];
    paths[currentPath] = {};
    continue;
  }
  
  const methodMatch = line.match(/^    (get|post|put|delete|patch):$/);
  if (methodMatch) {
    currentMethod = methodMatch[1];
    paths[currentPath][currentMethod] = { parameters: [] };
    continue;
  }
  
  if (currentPath && currentMethod && line.trim().startsWith('- $ref:')) {
    const paramRef = line.trim().replace('- $ref: ', '');
    paths[currentPath][currentMethod].parameters.push(paramRef);
  }
}

// Generate Postman items from paths
for (const [path, methods] of Object.entries(paths)) {
  const pathItem = {
    name: path,
    request: {
      method: 'GET',
      header: [
        { key: 'Authorization', value: 'Bearer {{api_token}}', type: 'text' }
      ],
      url: { raw: '{{base_url}}' + path, host: ['{{base_url}}'], path: path.replace(/^\//, '').split('/') }
    }
  };
  
  if (path.includes('{')) {
    pathItem.name = path.replace(/\{[^}]+\}/g, (match) => {
      const paramName = match.replace(/[{}]/g, '');
      pathItem.request.url.raw = pathItem.request.url.raw.replace(match, ':' + paramName);
      return ':' + paramName;
    });
  }
  
  // Add method-specific details
  for (const [method, details] of Object.entries(methods)) {
    pathItem.request.method = method.toUpperCase();
    pathItem.request.url.raw = '{{base_url}}' + path;
    
    if (details.parameters && details.parameters.length > 0) {
      for (const param of details.parameters) {
        if (param.includes('OrganizationId')) {
          pathItem.request.url.raw = pathItem.request.url.raw.replace('{organizationId}', '{{organization_id}}');
        }
      }
    }
  }
  
  postmanCollection.item.push(pathItem);
}

// Write Postman collection
fs.writeFileSync(postmanPath, JSON.stringify(postmanCollection, null, 2));
console.log('✅ Postman collection generated:', postmanPath);
console.log(`Paths: ${postmanCollection.item.length}`);
