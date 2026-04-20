#!/usr/bin/env python3
import json
from collections import OrderedDict
from copy import deepcopy
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent
OPENAPI_PATH = ROOT / 'docs' / 'openapi.yaml'
POSTMAN_PATH = ROOT / 'docs' / 'postman-collection.json'


def load_spec():
    with OPENAPI_PATH.open('r', encoding='utf-8') as f:
        return yaml.safe_load(f)


SPEC = load_spec()
COMPONENTS = SPEC.get('components', {})
SCHEMAS = COMPONENTS.get('schemas', {})
PARAMETERS = COMPONENTS.get('parameters', {})
RESPONSES = COMPONENTS.get('responses', {})


def resolve_ref(ref):
    if not ref.startswith('#/'):
        raise ValueError(f'Unsupported ref: {ref}')
    node = SPEC
    for part in ref[2:].split('/'):
        node = node[part]
    return node


def resolve_schema(schema):
    if not schema:
        return {}
    if '$ref' in schema:
        return resolve_schema(resolve_ref(schema['$ref']))
    out = deepcopy(schema)
    if 'allOf' in out:
        merged = {}
        for item in out['allOf']:
            merged = deep_merge(merged, resolve_schema(item))
        out.pop('allOf', None)
        out = deep_merge(merged, out)
    if 'anyOf' in out:
        return resolve_schema(out['anyOf'][0])
    if 'oneOf' in out:
        return resolve_schema(out['oneOf'][0])
    return out


def deep_merge(a, b):
    if isinstance(a, dict) and isinstance(b, dict):
        merged = dict(a)
        for k, v in b.items():
            merged[k] = deep_merge(merged[k], v) if k in merged else deepcopy(v)
        return merged
    return deepcopy(b)


def example_from_schema(schema, name_hint='value'):
    schema = resolve_schema(schema)
    if 'example' in schema:
        return schema['example']
    if 'default' in schema:
        return schema['default']

    t = schema.get('type')
    enum = schema.get('enum')
    if enum:
        return enum[0]

    if t == 'object' or 'properties' in schema:
        result = OrderedDict()
        props = schema.get('properties', {})
        required = set(schema.get('required', []))
        for key, prop in props.items():
            if required and key not in required and len(result) >= 8:
                continue
            result[key] = example_from_schema(prop, key)
        if not result and schema.get('additionalProperties'):
            result['key'] = example_from_schema(schema['additionalProperties'], 'value')
        return result

    if t == 'array':
        item_schema = schema.get('items', {'type': 'string'})
        return [example_from_schema(item_schema, name_hint)]

    fmt = schema.get('format')
    if fmt == 'uuid':
        return '{{%s}}' % name_hint
    if fmt == 'email':
        return 'user@example.com'
    if fmt == 'uri':
        return 'https://example.com/webhook'
    if fmt == 'date-time':
        return '2026-03-20T00:00:00Z'
    if fmt == 'password':
        return 'password123'

    if t == 'integer':
        minimum = schema.get('minimum')
        return minimum if minimum is not None else 1
    if t == 'number':
        minimum = schema.get('minimum')
        return minimum if minimum is not None else 1.0
    if t == 'boolean':
        return True
    if t == 'string':
        known = {
            'name': 'Example Name',
            'description': 'Example description',
            'extension': '1001',
            'number': '+15551234567',
            'domain': 'acme.example.com',
            'slug': 'acme',
            'timezone': 'Asia/Dhaka',
            'url': 'https://example.com/webhook',
            'secret': 'change-me',
            'email': 'user@example.com',
            'password': 'password123',
            'caller_id': '+15550001111',
            'destination': '1002',
            'uuid': '{{call_uuid}}',
            'organization_id': '{{organization_id}}',
            'extension_id': '{{extension_id}}',
            'agent_id': '{{agent_id}}',
        }
        return known.get(name_hint, 'string')

    return 'string'


def normalize_path_for_postman(api_path):
    raw = '{{base_url}}' + api_path
    path_parts = []
    variable = []
    for part in api_path.strip('/').split('/'):
        if part.startswith('{') and part.endswith('}'):
            key = part[1:-1]
            if key == 'organizationId':
                raw = raw.replace(part, '{{organization_id}}')
                path_parts.append('{{organization_id}}')
            else:
                raw = raw.replace(part, '{{%s}}' % camel_to_snake(key))
                path_parts.append('{{%s}}' % camel_to_snake(key))
            variable.append({'key': key})
        else:
            path_parts.append(part)
    return raw, path_parts, variable


def camel_to_snake(name):
    out = []
    for i, ch in enumerate(name):
        if ch.isupper() and i > 0 and name[i-1].islower():
            out.append('_')
        out.append(ch.lower())
    return ''.join(out)


def parameter_from_ref_or_inline(item):
    if '$ref' in item:
        return resolve_ref(item['$ref'])
    return item


def build_query_params(parameters):
    query = []
    for param in parameters:
        p = parameter_from_ref_or_inline(param)
        if p.get('in') != 'query':
            continue
        schema = resolve_schema(p.get('schema', {}))
        query.append({
            'key': p['name'],
            'value': str(example_from_schema(schema, camel_to_snake(p['name']))),
            'description': p.get('description', '')
        })
    return query


def should_require_auth(operation):
    security = operation.get('security', SPEC.get('security', []))
    if security == []:
        return False
    return True


def build_request_body(operation):
    body = operation.get('requestBody')
    if not body:
        return None
    body = parameter_from_ref_or_inline(body)
    content = body.get('content', {})
    if 'application/json' in content:
        schema = content['application/json'].get('schema', {})
        payload = example_from_schema(schema)
        return {
            'mode': 'raw',
            'raw': json.dumps(payload, indent=2),
            'options': {'raw': {'language': 'json'}}
        }
    if 'text/plain' in content:
        return {'mode': 'raw', 'raw': 'text'}
    return None


def build_headers(operation):
    headers = []
    if should_require_auth(operation):
        headers.append({'key': 'Authorization', 'value': 'Bearer {{api_token}}', 'type': 'text'})
    if operation.get('requestBody'):
        headers.append({'key': 'Content-Type', 'value': 'application/json', 'type': 'text'})
    return headers


def item_name(operation, method, api_path):
    return operation.get('summary') or f"{method.upper()} {api_path}"


def tag_folders():
    folders = OrderedDict()
    for tag in SPEC.get('tags', []):
        folders[tag['name']] = {'name': tag['name'], 'item': []}
    return folders


def collect_variables():
    variables = OrderedDict()
    variables['base_url'] = SPEC.get('servers', [{}])[0].get('url', 'http://localhost:8231/api/v1')
    variables['api_token'] = ''
    variables['organization_id'] = ''

    for _, path_item in SPEC.get('paths', {}).items():
        common_parameters = path_item.get('parameters', []) if isinstance(path_item, dict) else []
        for param in common_parameters:
            p = parameter_from_ref_or_inline(param)
            if p.get('in') == 'path' and p['name'] != 'organizationId':
                variables[camel_to_snake(p['name'])] = ''

        for _, operation in path_item.items():
            if not isinstance(operation, dict):
                continue
            for param in operation.get('parameters', []):
                p = parameter_from_ref_or_inline(param)
                if p.get('in') == 'path' and p['name'] != 'organizationId':
                    variables[camel_to_snake(p['name'])] = ''
    return [{'key': k, 'value': v} for k, v in variables.items()]


def build_collection():
    folders = tag_folders()

    for api_path, path_item in SPEC.get('paths', {}).items():
        common_parameters = path_item.get('parameters', []) if isinstance(path_item, dict) else []
        for method, operation in path_item.items():
            if method not in {'get', 'post', 'put', 'delete', 'patch'}:
                continue
            tags = operation.get('tags') or ['Ungrouped']
            tag = tags[0]
            folders.setdefault(tag, {'name': tag, 'item': []})

            all_parameters = common_parameters + operation.get('parameters', [])
            raw, path_parts, path_variables = normalize_path_for_postman(api_path)
            query = build_query_params(all_parameters)

            request = {
                'method': method.upper(),
                'header': build_headers(operation),
                'url': {
                    'raw': raw,
                    'host': ['{{base_url}}'],
                    'path': path_parts,
                },
                'description': operation.get('description', '') or operation.get('summary', '')
            }
            if path_variables:
                request['url']['variable'] = path_variables
            if query:
                request['url']['query'] = query
            body = build_request_body(operation)
            if body:
                request['body'] = body

            folders[tag]['item'].append({
                'name': item_name(operation, method, api_path),
                'request': request,
                'response': []
            })

    collection = OrderedDict([
        ('info', {
            'name': SPEC.get('info', {}).get('title', 'NIZAM API'),
            'description': SPEC.get('info', {}).get('description', '').strip(),
            'version': SPEC.get('info', {}).get('version', '1.0.0'),
            'schema': 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
        }),
        ('auth', {
            'type': 'bearer',
            'bearer': [{'key': 'token', 'value': '{{api_token}}', 'type': 'string'}]
        }),
        ('variable', collect_variables()),
        ('item', [folder for folder in folders.values() if folder['item']])
    ])
    return collection


def main():
    collection = build_collection()
    with POSTMAN_PATH.open('w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2)
        f.write('\n')
    print(f'Generated {POSTMAN_PATH}')
    print(f"Folders: {len(collection['item'])}")
    print(f"Variables: {len(collection['variable'])}")


if __name__ == '__main__':
    main()
