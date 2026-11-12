# ============================================
# Feedbacker Docker — Makefile
# ============================================
# Run from the docker/ directory.
#
#   make up                 Start all containers
#   make down               Stop & remove containers
#   make restart            Full restart (down + up)
#   make stop               Stop without removing
#   make vendor             composer install
#   make migrate            Apply migrations
#   make test_db            Create test database
#   make test_db_migration  Clone test DB structure
#   make test_db_drop       Drop test database
#   make test-db-shell      psql into the test database
#   make exec cmd="..."     Run arbitrary command in app container
#   make artisan cmd="..."  Run artisan command
#   make composer cmd="..." Run composer command
#   make npm cmd="..."      Run npm command (node container)
#   make npm-install        npm install
#   make npm-build          Build frontend assets
#   make npm-dev            Vite dev server
#   make test               Run the PHP test suite
#   make i18n               Apply translation changes (import + build + clear)
#   make fix-perms          Align host file ownership with UID/GID
#   make env                Create laravel/.env and generate APP_KEY
#   make storage-link       Symlink public/storage (local disk only)
#   make minio-init         (Re)apply the MinIO bucket policy
#   make minio-shell        mc inside the MinIO container
# ============================================

ENV_FILE := .env-common
DC := docker compose --env-file $(ENV_FILE)
DC_TOOLS := docker compose --env-file $(ENV_FILE) --profile tools

# Load .env-common so DB_*, TEST_DB_*, UID/GID are available to recipes.
-include $(ENV_FILE)
export

.PHONY: help up down restart stop build ps logs \
        env vendor migrate fresh seed key \
        test_db test_db_drop test_db_migration test test_unit test_feature \
        exec shell artisan composer tinker route-list db-shell redis-shell \
        test-db-shell i18n \
        storage-link minio-init minio-shell \
        npm npm-install npm-ci npm-build npm-dev \
        fix-perms go-build go-test go-lint

help:
	@grep -E '^#   make ' $(MAKEFILE_LIST) | sed 's/^#   //'

# ── Container lifecycle ──

up:
	@echo "🚀 Starting containers..."
	$(DC) up -d --build
	@echo "✅ All containers started"
	@$(DC) ps

down:
	@echo "🛑 Stopping & removing containers..."
	$(DC) down
	@echo "✅ Done"

restart:
	@echo "🔄 Full restart..."
	$(DC) down
	$(DC) up -d --build
	@echo "✅ Restarted"
	@$(DC) ps

stop:
	@echo "⏸️  Stopping containers..."
	$(DC) stop
	@echo "✅ Stopped"

build:
	@echo "🔨 Building images..."
	$(DC) build
	@echo "✅ Build complete"

ps:
	$(DC) ps

logs:
	$(DC) logs -f

# ── PHP / Laravel ──

env:
	@echo "🔑 Preparing laravel/.env..."
	@test -f ../laravel/.env || $(DC) exec -T app cp .env.example .env
	@grep -q '^APP_KEY=base64:' ../laravel/.env \
		&& echo "   APP_KEY already set, leaving it alone" \
		|| $(DC) exec -T app php artisan key:generate --force
	@echo "✅ laravel/.env ready"

vendor:
	@echo "📦 Running composer install in app container..."
	$(DC) exec -T app composer install --no-interaction --prefer-dist
	@echo "✅ Dependencies installed"

migrate:
	@echo "🗄️  Running migrations..."
	$(DC) exec -T app php artisan migrate --force
	@echo "✅ Migrations applied"

fresh:
	@echo "🗄️  Fresh migration (drop all + re-run)..."
	$(DC) exec -T app php artisan migrate:fresh --force
	@echo "✅ Fresh migration done"

seed:
	$(DC) exec -T app php artisan db:seed

key:
	$(DC) exec -T app php artisan key:generate

# ── Test database ──
#
# The suite never migrates. The test database is created once and its schema
# is cloned (structure only, no rows) from the development database, so tests
# run against PostgreSQL with the same shape the application uses.
#
# Re-run `make test_db_migration` after adding a migration — `make test` does
# it automatically.

test_db:
	@echo "🧪 Ensuring test database '$(TEST_DB_DATABASE)' exists..."
	@$(DC) exec -T pgsql psql -U $(DB_USERNAME) -d postgres -tAc \
		"SELECT 1 FROM pg_database WHERE datname = '$(TEST_DB_DATABASE)'" \
		| grep -q 1 \
		|| $(DC) exec -T pgsql psql -U $(DB_USERNAME) -d postgres -q -c \
			"CREATE DATABASE $(TEST_DB_DATABASE) OWNER $(DB_USERNAME);"
	@echo "✅ Test database ready"

test_db_drop:
	@echo "🧨 Dropping test database '$(TEST_DB_DATABASE)'..."
	@$(DC) exec -T pgsql psql -U $(DB_USERNAME) -d postgres -q -c \
		"DROP DATABASE IF EXISTS $(TEST_DB_DATABASE) WITH (FORCE);"
	@echo "✅ Dropped"

# Structure only: --schema-only skips all rows, --no-owner/--no-privileges keep
# the dump portable between the two databases.
test_db_migration: test_db
	@echo "🧪 Cloning structure: $(DB_DATABASE) → $(TEST_DB_DATABASE)..."
	@$(DC) exec -T pgsql psql -U $(DB_USERNAME) -d $(TEST_DB_DATABASE) -q -c \
		"DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"
	@$(DC) exec -T pgsql sh -c \
		'pg_dump -U $(DB_USERNAME) -d $(DB_DATABASE) --schema-only --no-owner --no-privileges' \
		| $(DC) exec -T pgsql psql -U $(DB_USERNAME) -d $(TEST_DB_DATABASE) \
			-q -v ON_ERROR_STOP=1 > /dev/null
	@echo "✅ Test DB structure cloned"

test-db-shell:
	$(DC) exec pgsql psql -U $(DB_USERNAME) -d $(TEST_DB_DATABASE)

# ── Tests ──
#
# Depends on the structure clone so the schema can never drift from the
# migrations that built the development database.

test: test_db_migration
	@echo "🧪 Running test suite..."
	$(DC) exec -T app php artisan test

test_unit: test_db_migration
	$(DC) exec -T app php artisan test --testsuite=Unit

test_feature: test_db_migration
	$(DC) exec -T app php artisan test --testsuite=Feature

# ── Utility ──

exec:
	@echo "▶️  Running: $(cmd)"
	$(DC) exec -T app $(cmd)

shell:
	$(DC) exec app bash

artisan:
	@echo "▶️  artisan $(cmd)"
	$(DC) exec -T app php artisan $(cmd)

composer:
	@echo "▶️  composer $(cmd)"
	$(DC) exec -T app composer $(cmd)

tinker:
	$(DC) exec app php artisan tinker

route-list:
	$(DC) exec -T app php artisan route:list

db-shell:
	$(DC) exec pgsql psql -U $(DB_USERNAME) -d $(DB_DATABASE)

redis-shell:
	$(DC) exec redis redis-cli

# ── Object storage ──
#
# Uploads go to whichever disk FILESYSTEM_DISK selects. MinIO is the default;
# these targets cover the two things that need doing by hand.

# Only needed when FILESYSTEM_DISK=local: Laravel serves local files through
# public/storage, which has to exist.
storage-link:
	@echo "🔗 Linking public/storage..."
	$(DC) exec -T app php artisan storage:link
	@echo "✅ Linked"

# The bucket is created by the MinIO container and made public by the one-shot
# minio-init service. Re-run this after changing credentials or the bucket name.
minio-init:
	@echo "🪣 Applying the MinIO bucket policy..."
	$(DC) up minio-init
	@echo "✅ Bucket '$(AWS_BUCKET)' is publicly readable"

minio-shell:
	$(DC) exec minio sh -c 'mc alias set local http://127.0.0.1:9000 "$$MINIO_ROOT_USER" "$$MINIO_ROOT_PASSWORD" >/dev/null; mc ls local; sh'

# ── Translations ──
#
# Translation *values* live in the database and are resolved per request, so
# editing wording needs no rebuild. What does need one is a change to the Vue
# templates — a new t() call, a renamed key, a new page — because the bundle
# ships the calls, not the strings. This target applies both.
#
#   make i18n                          additive import, then rebuild
#   make i18n force=1                  overwrite the database from the files
#   make i18n prune=1                  also drop rows whose key no longer exists
i18n:
	@echo "🌍 Importing language files into the database..."
	$(DC) exec -T app php artisan translations:import $(if $(force),--force,) $(if $(prune),--prune,)
	@echo "📦 Rebuilding the frontend bundle..."
	$(DC_TOOLS) run --rm node npm run build
	@echo "🧹 Clearing compiled views and caches..."
	$(DC) exec -T app php artisan optimize:clear
	@echo "✅ Translations applied — reload the page"

# ── Utility ──

# Align ownership of the bind-mounted sources with the host user.
# Needed after running artisan/composer inside the container as root.
fix-perms:
	@echo "🔧 Aligning ownership to $(UID):$(GID)..."
	$(DC) exec -T app chown -R $(UID):$(GID) /var/www
	@echo "✅ Ownership aligned"

# ── Node / frontend (on demand via the 'tools' profile) ──

npm:
	@echo "▶️  npm $(cmd)"
	$(DC_TOOLS) run --rm node npm $(cmd)

npm-install:
	@echo "📦 Installing JS dependencies..."
	$(DC_TOOLS) run --rm node npm install
	@echo "✅ JS dependencies installed"

npm-ci:
	$(DC_TOOLS) run --rm node npm ci

npm-build:
	@echo "🏗️  Building frontend assets..."
	$(DC_TOOLS) run --rm node npm run build
	@echo "✅ Assets built"

npm-dev:
	@echo "🔥 Starting Vite dev server on :$(VITE_PORT)..."
	$(DC_TOOLS) run --rm -p $(VITE_PORT):$(VITE_PORT) node npm run dev -- --host 0.0.0.0

# ── Go (sources live in ../go-widget) ──

go-build:
	@echo "🔨 Building Go widget server..."
	cd ../go-widget && go build -o ../bin/widget-server ./cmd/server
	@echo "✅ Build complete"

go-test:
	cd ../go-widget && go test ./... -v

go-lint:
	cd ../go-widget && golangci-lint run ./...
