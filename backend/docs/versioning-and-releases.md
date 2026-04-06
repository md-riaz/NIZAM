# Versioning & Release Strategy

## Semantic Versioning

NIZAM follows [Semantic Versioning 2.0.0](https://semver.org/) for both the API and event schemas.

### Version Format: `MAJOR.MINOR.PATCH`

| Component | When to Increment | Example |
|-----------|-------------------|---------|
| **MAJOR** | Breaking changes to API or event schema | Removing an endpoint, changing response structure |
| **MINOR** | New features, backward-compatible additions | New endpoint, new optional field |
| **PATCH** | Bug fixes, documentation updates | Fix validation bug, update docs |

### API Versioning
- API version is communicated via the `Accept` header or URL prefix
- Current version: `1.0`
- All responses include `schema_version` for event payloads

### Event Schema Versioning
- Event payloads include `schema_version` field (e.g., `"1.0"`)
- New fields are additive only (backward compatible)
- Removing or renaming fields requires a MAJOR version bump
- Consumers should ignore unknown fields

---

## Migration Strategy

### Backward-Compatible Changes (MINOR/PATCH)
These changes do NOT require consumer updates:
- Adding new optional fields to responses
- Adding new API endpoints
- Adding new event types
- Adding new query parameters
- Relaxing validation rules

### Breaking Changes (MAJOR)
These changes REQUIRE consumer updates:
- Removing or renaming fields
- Changing field types
- Removing API endpoints
- Changing authentication mechanisms
- Altering event payload structure

### Deprecation Schedule

| Phase | Duration | Action |
|-------|----------|--------|
| **Announcement** | Release N | Mark as deprecated in docs, add `Deprecated` header |
| **Warning Period** | Release N+1 to N+2 | Log warnings when deprecated features are used |
| **Removal** | Release N+3 (minimum) | Remove deprecated feature |

- Minimum deprecation period: **3 minor releases** or **6 months**, whichever is longer
- Deprecated endpoints return `X-Deprecated: true` header
- Deprecation notices are included in release notes

### Database Migration Guidelines
- All migrations must have a `down()` method
- Test migrations in staging before production
- Large data migrations should be run as background jobs
- Schema changes should be backward-compatible when possible

---

## Release Checklist

### Pre-Release
- [ ] All tests pass (`php artisan test`)
- [ ] Code style validated (`vendor/bin/pint --test`)
- [ ] No new security vulnerabilities (`composer audit`)
- [ ] Schema diff verified (compare migration files with previous release)
- [ ] API documentation updated (OpenAPI spec reflects changes)
- [ ] CHANGELOG updated with version number and date
- [ ] Migration compatibility verified (run migrations forward and backward)

### Smoke Tests
- [ ] Health check endpoint returns 200
- [ ] Authentication flow works (register → login → token → me)
- [ ] Tenant CRUD operations succeed
- [ ] Extension provisioning works
- [ ] Call event processing functions correctly
- [ ] Webhook delivery succeeds
- [ ] Queue metrics are computed correctly

### Load Tests
- [ ] API handles expected concurrent request volume
- [ ] Event processing handles expected event rate
- [ ] Webhook delivery scales with queue depth
- [ ] Database connection pool holds under load
- [ ] No memory leaks during sustained load

### Security Scan
- [ ] Dependency audit: `composer audit`
- [ ] Static analysis passes
- [ ] No hardcoded credentials in codebase
- [ ] Authentication and authorization tested
- [ ] Input validation covers all endpoints

### Schema Diff Verification
- [ ] Compare `database/migrations/` with previous release
- [ ] Verify no breaking schema changes without MAJOR version bump
- [ ] Confirm all new migrations have `down()` methods
- [ ] Test migration rollback in staging

### Deployment
- [ ] Create release tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`
- [ ] Push tag: `git push origin vX.Y.Z`
- [ ] Run migrations in staging: `php artisan migrate`
- [ ] Run smoke tests in staging
- [ ] Deploy to production
- [ ] Run migrations in production
- [ ] Run smoke tests in production
- [ ] Monitor error rates for 30 minutes post-deploy

### Post-Release
- [ ] Update documentation site
- [ ] Notify SDK users of new version
- [ ] Monitor metrics for anomalies
- [ ] Close release milestone

---

## Release Automation

### Automated Checks (CI/CD)
```yaml
# These should run on every PR and release:
- php artisan test                 # Full test suite
- vendor/bin/pint --test           # Code style
- composer audit                   # Security
- php artisan migrate:fresh        # Migration integrity
- php artisan migrate:rollback     # Rollback integrity
```

### Release Tags
- Use annotated tags: `git tag -a v1.2.3 -m "Release v1.2.3"`
- Tag format: `vMAJOR.MINOR.PATCH`
- Pre-release: `vMAJOR.MINOR.PATCH-rc.N`
