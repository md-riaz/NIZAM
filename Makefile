# NIZAM — developer and operator convenience targets
# Usage: make <target>
# Run 'make help' to list all available targets.

.DEFAULT_GOAL := help
.PHONY: help setup up down restart build rebuild logs shell \
        migrate seed key-generate sync-permissions \
        test lint fix queue-restart esl-restart status health \
        backup-db restore-db clean

COMPOSE    := docker compose
APP        := $(COMPOSE) exec app
ARTISAN    := $(APP) php artisan

# ────────────────────────────────────────────────────────────────────
# Help
# ────────────────────────────────────────────────────────────────────

help: ## Show this help message
	@echo ""
	@echo "  NIZAM — available make targets"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ { printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

# ────────────────────────────────────────────────────────────────────
# First-time setup
# ────────────────────────────────────────────────────────────────────

setup: ## First-time setup: copy .env, generate key, start services, migrate
	@[ -f .env ] || cp .env.example .env
	@grep -q '^APP_KEY=base64:' .env || \
	  { KEY=$$(php artisan key:generate --show --no-ansi); \
	    sed -i "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env; \
	    echo "APP_KEY generated and written to .env"; }
	$(COMPOSE) up -d --build
	@echo "Waiting for app to become healthy..."
	@$(COMPOSE) exec -T app php -r "exit(0);" 2>/dev/null || sleep 10
	$(ARTISAN) migrate --force
	@echo ""
	@echo "  Setup complete. API → http://localhost:8080/api/v1/health"
	@echo "  Run 'make seed' to load demo data."
	@echo ""

# ────────────────────────────────────────────────────────────────────
# Docker lifecycle
# ────────────────────────────────────────────────────────────────────

up: ## Start all services (detached)
	$(COMPOSE) up -d

down: ## Stop and remove containers
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

build: ## Build images (uses cache)
	$(COMPOSE) build

rebuild: ## Rebuild images from scratch (no cache)
	$(COMPOSE) build --no-cache

logs: ## Tail logs from all services (Ctrl+C to stop)
	$(COMPOSE) logs -f

logs-app: ## Tail logs from app service only
	$(COMPOSE) logs -f app

status: ## Show running container status
	$(COMPOSE) ps

health: ## Hit the health endpoint and pretty-print JSON
	@curl -s http://localhost:$${APP_PORT:-8080}/api/v1/health | python3 -m json.tool 2>/dev/null \
	  || curl -s http://localhost:$${APP_PORT:-8080}/api/v1/health

# ────────────────────────────────────────────────────────────────────
# Application management
# ────────────────────────────────────────────────────────────────────

shell: ## Open a shell inside the app container
	$(COMPOSE) exec app sh

migrate: ## Run database migrations
	$(ARTISAN) migrate --force

migrate-fresh: ## Drop all tables and re-run migrations (DESTRUCTIVE)
	$(ARTISAN) migrate:fresh --force

seed: ## Seed demo data
	$(ARTISAN) db:seed

key-generate: ## Generate APP_KEY and write it to .env
	@KEY=$$($(APP) php artisan key:generate --show --no-ansi); \
	  sed -i "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env; \
	  echo "APP_KEY updated in .env"

sync-permissions: ## Sync API permissions from code to database
	$(ARTISAN) nizam:sync-permissions

cache-clear: ## Clear all Laravel caches
	$(ARTISAN) config:clear
	$(ARTISAN) route:clear
	$(ARTISAN) view:clear
	$(ARTISAN) cache:clear

cache-warm: ## Warm all Laravel caches (production)
	$(ARTISAN) config:cache
	$(ARTISAN) route:cache
	$(ARTISAN) view:cache

# ────────────────────────────────────────────────────────────────────
# Workers
# ────────────────────────────────────────────────────────────────────

queue-restart: ## Gracefully restart queue workers
	$(ARTISAN) queue:restart

esl-restart: ## Restart the ESL listener container
	$(COMPOSE) restart esl-listener

# ────────────────────────────────────────────────────────────────────
# Testing & code quality
# ────────────────────────────────────────────────────────────────────

test: ## Run the full test suite
	$(APP) php artisan test

test-file: ## Run a single test file: make test-file F=tests/Feature/Api/ExtensionApiTest.php
	$(APP) php artisan test $(F)

lint: ## Check code style (Pint — dry run)
	$(APP) vendor/bin/pint --test

fix: ## Auto-fix code style (Pint)
	$(APP) vendor/bin/pint

# ────────────────────────────────────────────────────────────────────
# Backup & restore
# ────────────────────────────────────────────────────────────────────

backup-db: ## Dump the PostgreSQL database to ./backups/
	@mkdir -p backups
	$(COMPOSE) exec -T postgres pg_dump -U $${DB_USERNAME:-nizam} $${DB_DATABASE:-nizam} \
	  | gzip > backups/nizam_$$(date +%Y%m%d_%H%M%S).sql.gz
	@echo "Backup saved to backups/"

restore-db: ## Restore from a .sql.gz file: make restore-db F=backups/nizam_20260228.sql.gz
	@[ -n "$(F)" ] || { echo "Usage: make restore-db F=<file.sql.gz>"; exit 1; }
	gunzip -c $(F) | $(COMPOSE) exec -T postgres psql -U $${DB_USERNAME:-nizam} $${DB_DATABASE:-nizam}

# ────────────────────────────────────────────────────────────────────
# Cleanup
# ────────────────────────────────────────────────────────────────────

clean: ## Remove containers, volumes, and built images (DESTRUCTIVE)
	$(COMPOSE) down -v --remove-orphans
	$(COMPOSE) rm -f
