const fs = require('fs');
const path = require('path');
const SwaggerParser = require('@apidevtools/swagger-parser');

const specPath = path.join(__dirname, 'docs', 'openapi.yaml');

SwaggerParser.validate(specPath)
  .then(api => {
    console.log('✅ OpenAPI spec is valid');
    console.log(`Title: ${api.info.title}`);
    console.log(`Version: ${api.info.version}`);
    console.log(`Paths: ${Object.keys(api.paths).length}`);
    console.log(`Tags: ${api.tags.map(t => t.name).join(', ')}`);
    process.exit(0);
  })
  .catch(err => {
    console.error('❌ OpenAPI spec validation failed:');
    console.error(err.message);
    process.exit(1);
  });
