# Feedbacker Docker — Agent Context

> Infrastructure repo. Run everything from `docker/`.
> Keep in sync with `Makefile` and `README.md`.
> `README.md` is the human-facing quick start; this file is the condensed
> reference for agents.

## Project Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | Inertia.js 2 + Vue 3 + Tailwind CSS 3 |
| Auth | Laravel Breeze (Vue stack) + Sanctum |
| Widget server | Go 1.23 |
| Database | PostgreSQL 15 |
| Cache / Queue / Session | Redis 7 |

## Repository Layout

`docker/` is a standalone repo; `laravel/` and `go-widget/` are separate repos.

```
feedbacker/
├── docker/                 ← THIS REPO
│   ├── docker-compose.yml
│   ├── Makefile
│   ├── .env-common         ← committed defaults
│   ├── .env                ← local overrides (gitignored)
│   ├── Agents.md
│   ├── php/Dockerfile
│   ├── go/Dockerfile
│   └── nginx/default.conf
├── laravel/                ← Laravel application
└── go-widget/              ← Go widget server
```

## Services

| Service | Container | Port | Notes |
|---------|-----------|------|-------|
| `app` | feedbacker-app | 9000 | PHP-FPM |
| `nginx` | feedbacker-nginx | 8080 | reverse proxy → app |
| `widget-server` | feedbacker-widget | 8081 | Go public API + JS embed |
| `pgsql` | feedbacker-pgsql | 5432 | PostgreSQL |
| `redis` | feedbacker-redis | 6379 | Redis |
| `node` | feedbacker-node | — | on demand (`tools` profile) |

## Commands

```bash
# Lifecycle
make up                 # build + start everything
make down               # stop & remove
make restart            # down + up
make stop               # stop without removing
make ps / make logs

# PHP / Laravel
make env                # create laravel/.env + APP_KEY (idempotent)
make vendor             # composer install
make migrate            # apply migrations
make fresh              # migrate:fresh
make seed               # create the admin account
make artisan cmd="route:list"
make composer cmd="require foo/bar"
make shell              # bash inside app
make tinker

# Frontend (node container)
make npm-install
make npm-build
make npm-dev            # Vite dev server on :5173
make npm cmd="install @vueuse/core"

# Database
make db-shell           # psql (development database)
make redis-shell
make test_db            # create the PostgreSQL test database
make test_db_migration  # clone the schema into it (structure only)
make test_db_drop       # drop the test database
make test-db-shell      # psql into the test database

# Tests
make test               # clones the schema, then runs the suite
make test_feature
make test_unit

# Housekeeping
make fix-perms          # re-own bind-mounted files as $(UID):$(GID)

# Go (sources in ../go-widget)
make go-build / go-test / go-lint
```

## Configuration

- **`.env-common`** — committed defaults: DB credentials, ports, Redis client, UID/GID.
- **`.env`** — local overrides, gitignored.
- **`laravel/.env`** — application config owned by the Laravel repo (including `APP_KEY`).

Deliberately **not** in `.env-common`: `APP_KEY`, `APP_ENV`, `APP_DEBUG`.
Those belong to `laravel/.env`; injecting them from Compose would override the
application config inside the container (Laravel's Dotenv never overwrites an
existing environment variable).

## Test database and fixtures

The suite runs against **PostgreSQL** (`feedbacker_test`), whose schema is
cloned — structure only — from the development database:

```
make migrate            # develop the schema in `feedbacker`
make test_db_migration  # pg_dump --schema-only → `feedbacker_test`
make test               # does the clone, then runs the suite
```

Consequences worth remembering:

- Tests **never migrate**. Do not add `RefreshDatabase` to a test — it would
  run `migrate:fresh` and wipe the cloned schema. `Tests\TestCase` uses
  `DatabaseTransactions`, so each test rolls back.
- `Tests\TestCase::ensureSchemaIsReady()` fails with an actionable message when
  the test database is missing.
- `tests/bootstrap.php` normalises `$_SERVER`. The container exports
  `APP_ENV`/`DB_*`/`REDIS_*` and PHP copies them into `$_SERVER`, which
  Laravel's `Env` repository reads *before* `$_ENV`. Without it the suite would
  run with `APP_ENV=local` (CSRF active → HTTP 419 on every write) against the
  development database. `DB_HOST`/`DB_USERNAME`/`DB_PASSWORD` are deliberately
  left alone — the test database lives on the same server.

Test data comes from `tests/Fixtures/TestFixturesSeeder.php`, seeded in
`setUp()`:

| Account | Role | Spaces |
|---------|------|--------|
| `admin@feedbacker.test` | `admin` | none |
| `owner@feedbacker.test` | `user` | `acme-inc`, `globex` (closed) |
| `stranger@feedbacker.test` | `user` | `initech` |

Reach it through `tests/Concerns/InteractsWithFixtures.php`:
`fixtureAdmin()`, `fixtureOwner()`, `fixtureStranger()`, `fixtureSpace()`,
`fixtureInactiveSpace()`, `fixtureForeignSpace()`,
`fixtureTestimonial(name)`, `fixtureWidget()`, `fixtureBadgeWidget()`,
`fixtureWidgetByToken(token)`.

Fixture testimonials have staggered `created_at` values because the schema
stores timestamps with second precision — same-second rows tie in
`ORDER BY created_at DESC`.

Factories remain available for records the fixtures do not cover
(`User::factory()->admin()` / `->member()` / `->roleless()`).

## Roles and permissions (RBAC)

Powered by `spatie/laravel-permission`. Names live in enums so a typo is a
compile-time error instead of a runtime `PermissionDoesNotExist`:

- `app/Enums/RoleEnum.php` — `admin`, `user`
- `app/Enums/PermissionEnum.php` — `spaces.view.any`, `spaces.update.any`,
  `spaces.delete.any`, `testimonials.moderate.any`, `users.manage`
- `database/seeders/RolePermissionSeeder.php` — grants them (idempotent)

`User` uses spatie's `HasRoles` trait and assigns the baseline `user` role on
creation (`User::booted()` → `assignDefaultRole()`), guarded so it is a no-op
before the seeder has run.

`SpacePolicy` combines ownership with permissions. Ownership is checked first;
the `.any` permissions only add cross-account oversight.

| Ability | Owner | `admin` | Other `user` |
|---------|:-----:|:-------:|:------------:|
| `spaces.index` scope | own spaces | **all spaces** | own spaces |
| `view` / `update` / `delete` | yes | yes | 403 |
| `moderateTestimonials` (inbox) | yes | yes | 403 |

Controllers resolve abilities per record and pass them to Inertia
(`can: { update, delete }`); the Vue pages never infer permissions themselves.

There is no `is_admin` column — it was replaced by the `admin` role.

## Admin account

`make seed` runs `RolePermissionSeeder` then `AdminUserSeeder`, which upserts
an administrator from `config/feedbacker.php` (fed by `ADMIN_NAME`,
`ADMIN_EMAIL`, `ADMIN_PASSWORD` in `laravel/.env`) and gives it the `admin`
role. Defaults: `admin@feedbacker.test` / `password`. Both seeders are
idempotent.

### Domain model

```
User ──hasMany──> Space ──hasOne──> SpaceBranding
                    │
                    ├──hasMany──> Testimonial
                    ├──hasMany──> Widget
                    └──hasMany──> WidgetView / TestimonialClick
```

- `Space` — route key is `slug`; creating one auto-creates its branding record.
- `SpaceBranding` — table is `space_branding` (singular), set explicitly.
- `Testimonial` — `status` is cast to `TestimonialStatusEnum`; `tags` is a
  `json` column (not a PG array) so SQLite tests work. Scopes: `approved()`,
  `pending()`, `rejected()`, `featured()`, `forWall()`.
- `Widget` — route key is **`token`**, not the UUID: the token ends up in
  third-party page source. `configWithDefaults()` merges the stored config over
  `WidgetLayoutEnum::defaultConfig()` so an old row still renders.
- `WidgetView` / `TestimonialClick` — append-only, `$timestamps = false`.

## Widget builder

`WidgetLayoutEnum` (`app/Enums/WidgetLayoutEnum.php`) is the single source of
truth for the four layouts, their labels, descriptions and default config. It
exposes `options()` for the Vue builder, so adding a layout is one enum case
plus a branch in the bundle.

| Ability | Where |
|---------|-------|
| `spaces.widgets.index` / `.create` | authorises against the **Space** |
| `spaces.widgets.edit` / `.update` / `.destroy` | `WidgetPolicy`, which delegates to `SpacePolicy` |

The config is a fixed set of keys validated in `WidgetRequest` — never a
free-form blob — so the renderer can rely on the shape. `config.accent` is
regex-checked because it is interpolated into a CSS declaration.

## Video

`testimonials.video_url` is validated as `url:http,https` on submission. The Go
bundle recognises YouTube, Vimeo and direct video files and plays them in a
lightbox; anything else opens in a new tab. Upload is out of scope — it needs
object storage.

## Public collection flow

| Route | Method | Middleware |
|-------|--------|-----------|
| `/c/{space}` | GET | none |
| `/c/{space}` | POST | `throttle:10,1` |

`CollectionPageController` validates through `StorePublicTestimonialRequest`,
which exposes `testimonialData()` — only submitter-owned fields. `status`,
`is_featured`, `tags` and `space_id` are set server-side, so a visitor cannot
self-approve or post into another space. `looksLikeSpam()` implements the
honeypot; spam is dropped but still answered with a success redirect.

## Moderation flow

| Route | Name |
|-------|------|
| `GET spaces/{space}/testimonials` | `spaces.testimonials.index` |
| `PATCH testimonials/{testimonial}/approve` | `testimonials.approve` |
| `PATCH testimonials/{testimonial}/reject` | `testimonials.reject` |
| `PATCH testimonials/{testimonial}/feature` | `testimonials.feature` |
| `DELETE testimonials/{testimonial}` | `testimonials.destroy` |

`TestimonialPolicy::moderate()` delegates to
`SpacePolicy::moderateTestimonials()`, so the "who may moderate this space"
rule lives in one place.

## Widget service (Go)

Lives in `../go-widget`. Layers:

```
cmd/server/main.go            wiring, HTTP server, graceful shutdown
internal/config (env only)    read via env() in main
internal/model                Space, Testimonial, Wall, WidgetView, TestimonialClick
internal/store                Store interface + pgx implementation
internal/cache                TTL cache (in-process)
internal/middleware           CORS, logging, recover, rate limiting
internal/widget               embed.js (go:embed) + option parsing
internal/handler              HTTP handlers
```

`handler.Handler` depends on the `store.Store` **interface**, so its tests run
against a fake with no database.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/health` | pings PostgreSQL, 503 when down |
| GET | `/w/{slug}.js` | space-wide embed; `.js` optional |
| GET | `/w/{slug}/{token}.js` | saved widget; slug must match the token |
| GET | `/api/public/spaces/{slug}` | public projection (no `author_email`) |
| GET | `/api/public/spaces/{slug}/wall` | `?limit=` `?featured_only=` `?min_rating=` |
| GET | `/api/public/spaces/{slug}/testimonials` | alias for `/wall` |
| GET | `/api/public/widgets/{token}` | saved widget's wall, filters applied |
| POST | `/api/track/view` | throttled 60/min per IP |
| POST | `/api/track/click` | throttled 60/min per IP |

Decisions worth not re-litigating:

- **Writes stay in Laravel.** The Go service is read + analytics only, so there
  is a single submission path (`CollectionPageController`).
- **Caching is in-process**, 60s TTL, keyed by slug **plus filter**. A shared
  cache only matters when the service is scaled out; `cache.Cache` is the seam.
- **`widget_views.ip_address` comes from the connection**, never the payload.
- **A space-wide embed configures itself by query string**; a saved widget by
  its row. Both converge on `model.EmbedOptions`.
- **Layout rules beat stored config** — `single` always resolves to limit 1 and
  `featured_only`.
- **The embed JS is `go:embed`-ed** and never uses `innerHTML`, so testimonial
  content cannot inject markup into a host page.

## Analytics (Laravel side)

- `App\Models\WidgetView`, `App\Models\TestimonialClick` — append-only,
  `$timestamps = false`.
- `App\Support\Analytics` — `summary()`, `dailySeries()`, `topTestimonials()`,
  `perSpace()`. Every method takes a **collection of space ids**, so the caller
  decides the scope (member → own spaces, admin → all).
- `SpaceAnalyticsController@show` renders `/spaces/{slug}/analytics`.
- The dashboard and space page carry an `analytics` summary prop.

## Object storage

MinIO runs in the stack (`minio` + a one-shot `minio-init` that makes the
bucket publicly readable). Uploads go through Laravel's `Storage` facade and
`App\Support\Uploads`; **no caller names a disk** — `FILESYSTEM_DISK` selects
the backend, so switching to real S3 is a config change.

| Piece | Where |
|-------|-------|
| Helper | `app/Support/Uploads.php` — `replace()`, `forget()`, `url()`, directory builders |
| Validation | `app/Http/Requests/UploadImageRequest.php` |
| Endpoints | `SpaceLogoController`, `TestimonialAvatarController` |
| Compose | `minio`, `minio-init` (bucket policy), `minio_data` volume |
| Make | `minio-init`, `minio-shell`, `storage-link` |

Rules worth keeping:

- **SVG is rejected.** It can carry script and the bucket is browser-loadable.
- **The dimension rule is not cosmetic.** A decompression bomb passes the size
  check; reading dimensions needs GD built with WebP (`--with-webp`).
- **Storage paths are deterministic** (`spaces/{id}/logo.{ext}`) so replacing a
  logo removes the old file whatever its extension — `Uploads::forget()` works
  by stem, not by URL.
- **`AWS_ENDPOINT` is the container's route, `AWS_URL` the browser's.** Getting
  them the wrong way round yields URLs that upload fine and then 404.
- **The bucket is anonymous-download**, not presigned: these are public assets
  and expiring URLs would break embedded walls.
- **Known gap:** deleting a space leaves its objects behind. Cleanup belongs in
  a sweeping job, not in a database transaction.

## Notifications, tags and search

**Notifications** are queued (`ShouldQueue`) and dispatched from
`CollectionPageController::notifyOwner()`, which swallows failures — a broken
mailer must not lose a testimonial. `feedbacker-queue` runs the worker; Mailpit
catches mail locally.

**Tags** live in a json column, normalised on write (`Testimonial::normaliseTags`):
trimmed, lowercased, deduplicated, capped. Filtering uses
`tags::jsonb @> ?::jsonb` — the raw form, because `whereJsonContains` needs
jsonb and the column is json.

> Blank tags are filtered in `UpdateTestimonialTagsRequest::prepareForValidation()`.
> `ConvertEmptyStringsToNull` turns `""` into `null`, which the `string` rule
> rejects — one stray entry would otherwise fail the whole request.

**Search** matches a generated `tsvector` column (`search_vector`) with a GIN
index. `Testimonial::toTsquery()` turns free text into ANDed prefix terms
(`embed` → `embed:*`); it returns `null` when nothing usable remains, because
`to_tsquery('')` is a PostgreSQL syntax error. Results are ordered by
`ts_rank`, not date.

`simple` rather than `english`: stemming is language-specific, and prefix
matching is the part users notice.

## Container DNS

`nginx/default.conf` puts the upstream in a variable and names Docker's resolver:

```nginx
resolver 127.0.0.11 valid=10s ipv6=off;
set $upstream_app app;
fastcgi_pass $upstream_app:9000;
```

A literal `fastcgi_pass app:9000` is resolved once at startup and cached for the
life of the process. Recreating the app container gives it a new IP, so nginx
keeps dialling a dead address and every request becomes a 502 until nginx is
restarted. The variable forces re-resolution.

Related: **env changes only reach a container when it is recreated.**
`docker compose up -d app` after editing `.env-common` — a restart is not enough.

## Languages and translations

English and Russian. Strings resolve **database row → language file → the key
itself**, so the files define which keys exist and the database holds the
wording. Two classes, and both are load-bearing:

- `App\Translation\DatabaseLoader` — merges rows over the file array
- `App\Translation\DatabaseTranslator` — **Laravel's translator memoises every
  group it loads**, so without this it never asks the loader twice in a request
  and a saved translation stays invisible. This subclass drops its memo when
  `Translation::version()` moves.

`Translation::version()` is a cache counter bumped on every write; it is part of
the loader's cache key, so a save is visible immediately without flushing.

| Concern | Where |
|---------|-------|
| Locale resolution | `App\Http\Middleware\SetLocale` — session → user → `Accept-Language` → default |
| Switcher | `GET /locale/{code}`, `LocaleController` (open to guests) |
| Catalogue | `TranslationController`, guarded by `translations.manage` |
| Import | `translations:import` — additive by default |
| Frontend | `useTranslations()` (`t`, `tc`, `locale`, `locales`) |

The `translator` role holds `translations.manage` **and nothing else**: the point
is maintaining the catalogue without reaching customer data.

**Coverage is complete**: 318 keys per language, covering every page a user
sees. Two tests guard it — one fails when the `en` and `ru` files disagree on a
key set, another when a Russian value is byte-identical to its English one
(legitimate exceptions are listed in `TranslationTest`).

When adding a string: add the key to both files, run `make i18n`, then use
`t('group.key')` in Vue or `__('app.group.key')` in PHP. The import is additive,
so it will never clobber a translator's wording.

> **Never name a page prop after a shared Inertia prop.** Page props win, so a
> controller passing its own `translations` replaces the shared map and every
> `t()` call on that page renders the raw key — which is how the catalogue
> shipped with untranslated buttons. Reserved names: `auth`, `flash`, `locale`,
> `locales`, `translations`. The catalogue uses `entries`, and
> `TranslationTest::test_no_page_shadows_the_shared_translation_map` fails if
> the collision returns.

> **A rebuild is needed for template changes, not wording changes.** Values are
> read from the database per request; a new `t()` call, a renamed key or a new
> page lives in the bundle. `make i18n` does the import, the build and the cache
> clear in one step.

> **Uniqueness is a creation-time rule.** A translation's identity is its
> `(locale, group, key)` triple and is fixed when the row is created;
> `TranslationRequest` only applies the `unique` rule on `store`, and
> `TranslationController::update()` writes the value alone. Letting an edit move
> the identity would mean landing on a slot another row holds — which is exactly
> what the unique index forbids, and what made every save fail once a key
> existed in both languages (the normal state after an import).

> Keys use dot notation (`nav.dashboard`) and are expanded with `Arr::set`
> before merging, because the translator looks them up with `Arr::get`.

> `group` and `key` are reserved words in PostgreSQL. Eloquent quotes them, so
> the application is fine, but raw SQL must write `"group"` and `"key"`.

> **Cache objects with care.** `Cache::put('x', collect([...]))` round-trips as
> `__PHP_Incomplete_Class` in this stack — it took the whole application down
> with a `TypeError` on the first request. Cache plain arrays and rebuild models
> with `newFromBody()`/`newFromBuilder()`; `Language::all_cached()` shows the
> pattern.

> The test database is cloned **schema-only**, so reference data a migration
> inserts (the language list) never reaches it. `LanguageSeeder` is called from
> both the migration and `TestFixturesSeeder`.

## Testing notes

`Tests\TestCase` uses `RefreshDatabase` and seeds `RolePermissionSeeder` in
`setUp()`, so roles exist for every test. `Gate` is not the cache to clear —
use `app(PermissionRegistrar::class)->forgetCachedPermissions()`.

Factory helpers: `User::factory()->admin()`, `->member()`, `->roleless()`.

## Permissions model

The `app` image creates a user matching `UID`/`GID` (default 1000) and runs
php-fpm workers as that user, so files PHP writes (logs, compiled views, cache)
stay owned by the host developer and bind-mounted sources remain editable.

If files end up root-owned (e.g. after running artisan as root), restore them:

```bash
make fix-perms
```

## Setup from scratch

```bash
git clone <docker-repo>   feedbacker/docker
git clone <laravel-repo>  feedbacker/laravel
git clone <go-repo>       feedbacker/go-widget

cd feedbacker/docker
cp .env-common .env
make up
make vendor
make migrate
make npm-install
make npm-build
```

App: http://localhost:8080 · Widget server: http://localhost:8081

## Delivered phases

- **Phase 1** — Docker environment, PostgreSQL/Redis/Nginx, Go widget server skeleton, base migrations.
- **Phase 2** — Breeze auth (login/register/password reset/profile), `Space` + `SpaceBranding` domain, Space CRUD, branding editor with live preview.
- **Phase 2.1** — static marketing landing page, project `README.md`, `make env`.
- **Phase 2.2** — RBAC via spatie/laravel-permission, `admin` role, enums for roles/permissions, permission-driven policies and UI abilities.
- **Phase 3** — `Testimonial` domain, public collection page `/c/{slug}` with honeypot + throttle, moderation inbox with status/search/rating filters.
- **Phase 3.1** — suite moved to PostgreSQL with a cloned test schema, transactional tests, shared fixture dataset.
- **Phase 4** — Go widget service rewritten (store interface, TTL cache, rate limiting, embedded JS), shadow-DOM masonry widget with query-string options, impression/click tracking, analytics pages.
- **Phase 5R** — `widgets` table and builder (four layouts, per-widget filters, public tokens, live preview), video testimonials by URL.
- **Phase 6R** — queued email notifications (Mailpit locally), tags with normalisation and filtering, PostgreSQL full-text search over a generated `tsvector`.
- **Phase 7R** — MinIO in the stack, disk-agnostic upload layer, logo and avatar uploads with image validation.
- **Phase 8R.5** — locales (en, ru), database-backed translation catalogue with an admin/translator CRUD, locale switcher and middleware.
- **Phase 8R (rest)** — social imports, NPS, case studies, custom domains, integrations, billing.
