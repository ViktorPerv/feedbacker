# Feedbacker — Docker environment

Infrastructure repository for **Feedbacker**, a testimonial collection and
showcase service (a self-hosted take on [testimonial.to](https://testimonial.to)).

This repo owns the Docker Compose stack, the PHP and Go images, the Nginx
config, the Makefile and the shared environment defaults. The application code
lives in two sibling repositories.

```
feedbacker/
├── docker/          ← this repo (infrastructure)
├── laravel/         ← application (Laravel 13 + Inertia + Vue 3)
└── go-widget/       ← public widget server (Go)
```

---

## Requirements

| Tool | Version | Notes |
|------|---------|-------|
| Docker Desktop | 4.x+ | with the WSL 2 backend on Windows |
| GNU Make | 3.81+ | `make` inside WSL / macOS / Linux |
| Git | any | |

Nothing else needs to be installed on the host — PHP, Node, PostgreSQL and
Redis all run in containers.

> **Windows users:** run every command from inside WSL (`wsl` in a terminal),
> not from PowerShell. Docker Desktop must have WSL integration enabled for
> your distribution.

---

## Quick start

### 1. Clone the three repositories side by side

```bash
mkdir -p ~/feedbacker && cd ~/feedbacker

git clone <docker-repo-url>  docker
git clone <laravel-repo-url> laravel
git clone <go-repo-url>      go-widget
```

The directory layout matters: `docker/docker-compose.yml` mounts
`../laravel` and builds `../go-widget`.

### 2. Create the local environment file

```bash
cd docker
cp .env-common .env
```

`.env-common` holds the committed defaults; `.env` is yours and is gitignored.
Change database credentials, ports or the GitHub token there if you need to.

### 3. Build and start the stack

```bash
make up
```

This builds the PHP and Go images and starts five services. The first build
takes a few minutes (PHP extensions are compiled).

### 4. Prepare the application

```bash
make env        # create laravel/.env from .env.example and generate APP_KEY
make vendor     # composer install
make migrate    # create the database schema
make seed       # create the admin account
make npm-install
make npm-build
```

### 5. Open the app

| URL | What |
|-----|------|
| http://localhost:8080 | Application (landing page, dashboard) |
| http://localhost:8081/health | Go widget server health check |
| http://localhost:8081/w/{slug}.js | The embeddable widget for a space |

### Default admin account

`make seed` runs `RolePermissionSeeder` and then creates a local development
administrator with the `admin` role, using the values in `laravel/.env`:

| Field | Default |
|-------|---------|
| Email | `admin@feedbacker.test` |
| Password | `password` |

Change `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `laravel/.env` before seeding, or
re-run `make seed` after editing — both seeders are idempotent.

> The seeder prints a warning whenever the password is still `password`.
> Never deploy with the default credentials.

You can also self-register at http://localhost:8080/register — that is the
normal end-user path, and new accounts automatically receive the `user` role.

---

## Testimonials

### Collecting

Every space has a public collection page at `/c/{slug}` — no account needed.
It renders the space's branding (logo, colours, font, button and thank-you
copy) and asks for a name, optional job title/company/email, a 1–5 star rating
and the story itself.

Submissions always land as `pending`. Nothing is public until it is approved.

| Route | Method | Notes |
|-------|--------|-------|
| `/c/{slug}` | GET | Public collection page |
| `/c/{slug}` | POST | Submit — throttled to 10 requests/minute per IP |

Spam handling on the public form:

- **Honeypot** — a hidden `website` field. When it is filled the submission is
  dropped, but the response still looks like a success so bots learn nothing.
- **Throttling** — `throttle:10,1` on the POST route.
- **Server-controlled fields** — `status`, `is_featured`, `tags` and `space_id`
  are never taken from the request body, so a submitter cannot self-approve or
  post into someone else's space.

### Moderating

The inbox lives at `/spaces/{slug}/testimonials`. It lists submissions with
status tabs (all / pending / approved / rejected), a text search over author
name, content and company, and a rating filter.

Actions per testimonial:

| Action | Effect |
|--------|--------|
| Approve | `status = approved`, stamps `approved_at` |
| Reject | `status = rejected`, clears `is_featured` |
| Feature | Toggles `is_featured` (drives wall ordering) |
| Delete | Removes the record |

Reachable by the space owner, or by any account holding
`testimonials.moderate.any` (the `admin` role).

### Data model

`Testimonial` belongs to a `Space` and cascades on delete. Status is an enum
(`App\Enums\TestimonialStatusEnum`) cast on the model, so an invalid state is
impossible to store. `tags` is a `json` column rather than a PostgreSQL array
so the same schema runs on the SQLite database used by the test suite.

---

## The widget and analytics

### Embedding

Approved testimonials are served by the Go widget service as a self-contained
script. The snippet is on the space page and the analytics page:

```html
<div data-feedbacker="your-slug"></div>
<script async src="http://localhost:8081/w/your-slug.js"></script>
```

The placeholder is optional — with no `[data-feedbacker]` element on the page
the widget creates one and appends it to `<body>`.

> **Where the script lives.** The snippet points at the **widget service**, not
> at the application: `/w/…` is answered by Go, not by Laravel. In production
> both usually sit behind one domain; locally they are separate ports. This is
> what `WIDGET_PUBLIC_URL` controls.

### The live demo on the landing page

The homepage embeds a **real widget** from the `admin-space` space, so a visitor
sees the product working rather than a screenshot of it. The section appears only
when the demo widget exists.

```bash
make artisan cmd="db:seed --class=DemoContentSeeder"
```

The seeder creates six approved reviews and a masonry widget. It is idempotent,
and which space is demonstrated is set by `WIDGET_DEMO_SPACE`.

The script is injected from `onMounted`: a literal `<script>` tag in a Vue
template would be parsed as the component's own script. Running it this way
exercises exactly the same code path a customer's site would.

Everything renders inside a **shadow root**, so host page CSS cannot leak in
and widget CSS cannot leak out. The layout is a responsive masonry wall
(CSS columns) with star ratings, avatars (falling back to initials) and a
featured badge.

### The widget builder

A space can have any number of named widgets, each with its own layout and
filters, at **`/spaces/{slug}/widgets`**. This is what the query-string options
above cannot express: one space powering a wall on the homepage, a badge in the
footer and a carousel on a landing page — each with different settings.

| Layout | What it renders |
|--------|-----------------|
| `masonry` | Responsive CSS-column grid |
| `carousel` | Horizontal scroll-snap row with arrows |
| `single` | One testimonial, for a hero or sidebar |
| `badge` | Avatars, star average and a count |

Filters are per widget: `featured_only` and `min_rating`. Layout rules win over
stored values where they have to — a `single` widget always asks for exactly
one testimonial, and the featured one.

Each widget gets a 24-character public **token**, and its embed URL addresses it
by that token:

```
http://localhost:8081/w/{space-slug}/{token}.js
```

The token is never the UUID: it ends up in third-party page source. It is also
stable across edits, because changing it would break every page already
embedding the widget.

The builder renders a **live preview** of the selected layout with sample data,
so the four layouts can be compared without publishing anything.

### Video testimonials

The collection form accepts an optional video link, restricted to `http` and
`https`. The bundle recognises YouTube, Vimeo and direct `.mp4`/`.webm`/`.ogg`
files and plays them in a lightbox inside the widget; anything else opens in a
new tab.

Self-hosted video upload is **not** part of this — the storage layer below can
hold the files, but there is no recorder UI yet.

---

## Notifications, tags and search

### Notifications

Submitting a testimonial queues a heads-up to the space owner. It is queued
because a visitor's request should never wait on an SMTP handshake, and a mail
outage must not fail their submission — the notification is dispatched inside a
guard, so a broken mailer cannot lose the testimonial.

Local development uses **Mailpit**, which catches everything and shows it at
http://localhost:8025. No SMTP account needed.

```bash
docker compose logs -f queue-worker   # watch jobs being processed
```

| Variable | Value | Notes |
|----------|-------|-------|
| `MAIL_MAILER` | `smtp` | point at a real relay for anything but local |
| `MAIL_HOST` / `MAIL_PORT` | `mailpit` / `1025` | |
| `QUEUE_CONNECTION` | `redis` | the `queue-worker` service runs `queue:work` |

> Env changes only reach a container when it is **recreated**. `docker compose
> up -d app` after editing `.env-common` — restarting is not enough.

### Tags

Tags are free-form, per testimonial, and stored as json. They are normalised on
the way in: trimmed, lowercased, deduplicated, capped at 10 tags of 32
characters. The inbox shows every tag in use with counts, and filtering by one
is a single click.

`PATCH /testimonials/{id}/tags` takes the whole list — sending `[]` clears it.

> Blank entries are dropped **before** validation. A tag input emits `""` when
> cleared, `ConvertEmptyStringsToNull` turns that into `null`, and the `string`
> rule would then reject the request over one stray entry — losing the tags the
> user did type.

### Full-text search

`testimonials.search_vector` is a **generated** `tsvector` column with a GIN
index: PostgreSQL maintains it on write, so it cannot drift from the row.

```sql
to_tsvector('simple',
    author_name || ' ' || author_title || ' ' || author_company || ' ' || content
)
```

The `simple` configuration is deliberate. `english` would stem English words
but treat Russian ones as opaque tokens; `simple` only lowercases, so every
language behaves the same. The stemming people actually notice is covered by
**prefix matching**: the search box turns `embed` into `embed:*`, so it finds
"embeds".

Matching terms are ANDed, and results are ordered by `ts_rank` rather than date
— searching means you want the best match first, not the newest.

A term that produces no usable tokens (punctuation only) skips the clause
entirely: `to_tsquery('')` is a syntax error in PostgreSQL.

---

## Languages

English and Russian ship today. The catalogue lives in the **database**, so
wording is edited from the application rather than from a deploy.

### Where a string comes from

```
database row  →  language file  →  the key itself
```

The files (`lang/{locale}/*.php`) stay the source of truth for *which* keys
exist; the database is where their wording is edited. A key with no row — or a
row with an empty value — falls back to the file, so a new string can ship
before anyone has translated it, and deleting a row restores the file value
rather than blanking the interface.

Two pieces make this work, and both are needed:

| Piece | Why |
|-------|-----|
| `App\Translation\DatabaseLoader` | Reads rows and merges them over the file array |
| `App\Translation\DatabaseTranslator` | Laravel's translator memoises every group it loads, so it would never ask the loader twice in one request — a saved translation would be invisible until the next request |

### Choosing a language

Resolved in this order, by `App\Http\Middleware\SetLocale`:

1. the session — an explicit choice from the switcher
2. the signed-in user's saved preference
3. the browser's `Accept-Language`
4. the language marked as default

`GET /locale/{code}` is open to guests, because the public collection page
should be readable in either language. A signed-in user's choice is also
written to their profile so it follows them to another browser.

### The catalogue

**`/translations`** — reachable by `admin` and by the **`translator`** role.

The translator role exists so the catalogue can be maintained without granting
access to customer data: it holds `translations.manage` and nothing else, so a
translator cannot open a space, moderate a testimonial or manage a user.

| Feature | |
|---------|---|
| Completion bars | How far each language has got, against the default locale's key count |
| Filters | Language, group, free-text (matches key **and** value), and "only untranslated" |
| Import | `translations:import` pulls the language files in — **additive**, so a translator's work is never clobbered by a deploy |
| Sibling panel | On the edit page, what the same key says in the other languages |

**A translation's identity is `(locale, group, key)` and is fixed at creation.**
An edit rewrites the wording and nothing else.

That is deliberate. Uniqueness is a creation-time concern: once a key exists in
both languages — which is the normal state after an import — changing the
language or the key on an existing row would mean moving it onto a slot another
row already holds. So the edit page shows the identity as read-only and the
update endpoint ignores any attempt to change it, which makes a collision
impossible rather than merely rejected.

To use a different key, create a new entry and delete the old one.

```bash
make artisan cmd="translations:import"              # additive
make artisan cmd="translations:import --force"      # overwrite from files
make artisan cmd="translations:import --group=auth" # one group only
```

### What is translated

Everything a user sees: the landing page, authentication, the dashboard, spaces,
branding, analytics, the widget builder, the moderation inbox, the profile pages,
the public collection page and the catalogue itself — **318 interface keys** per
language, shipped to the frontend on every Inertia response.

Behind that sit the framework groups, which stay server-side:

| Group | Keys | Notes |
|-------|------|-------|
| `app` | 318 | the interface; the only group sent to the browser |
| `validation` | 138 / 111 | the rules the application uses, plus the common ones |
| `passwords` | 5 | |
| `auth` | 3 | |
| `pagination` | 2 | |

Validation is deliberately **partial**: a rule with no Russian row falls back to
English through `fallback_locale`, so an untranslated edge case reads as English
rather than as a raw key. Field names are translated too, so a message reads
«Поле «имя автора» обязательно для заполнения» rather than naming the column.

> Plural forms use Laravel's `|` syntax and are resolved in the frontend by
> `useTranslations().tc()`, which implements the Russian 1/2-5/other rule.
> English strings carry two forms, Russian three.

### Adding a string

1. Add the key to `lang/en/app.php` **and** `lang/ru/app.php` — a test fails if
   the two files disagree.
2. Run `make i18n` to create the rows and rebuild the bundle.
3. Use it: `t('group.key')` in Vue, `__('app.group.key')` in PHP.

```bash
make i18n                  # import + rebuild + clear caches
make i18n force=1          # also overwrite the database from the files
make i18n prune=1          # also drop rows whose key no longer exists
```

**When a rebuild is needed.** Translation *values* live in the database and are
resolved per request, so editing wording takes effect on reload. What does need
a rebuild is a change to the Vue templates — a new `t()` call, a renamed key, a
new page — because the bundle ships the calls, not the strings. `make i18n`
covers both.

`--prune` is what keeps the catalogue honest after a key is renamed: without it
the old row lingers and a translator sees entries nothing renders. It is scoped
to the locales and groups actually scanned, so `--group=auth` cannot delete the
rest of the catalogue.

### Never name a page prop after a shared one

Inertia merges page props over shared props, so a page that passes its own
`translations` replaces the shared translation map — and every `t()` call on
that page renders the raw key. That is exactly how the catalogue shipped with
untranslated buttons: its prop was the paginator.

The shared names are `auth`, `flash`, `locale`, `locales`, `translations`. The
catalogue passes its rows as **`entries`**. A test asserts the shared map
survives on every page, and it fails if the collision is reintroduced.

---

## Object storage

Uploads (space logos, testimonial avatars) go to S3-compatible object storage.
MinIO runs in the stack, so local development needs no AWS account.

| Service | Container | Port | Notes |
|---------|-----------|------|-------|
| `app` | `feedbacker-app` | — | php-fpm |
| `queue-worker` | `feedbacker-queue` | — | `queue:work`, same image as `app` |
| `nginx` | `feedbacker-nginx` | 8080 | |
| `widget-server` | `feedbacker-widget` | 8081 | Go |
| `pgsql` | `feedbacker-pgsql` | 5432 | PostgreSQL 15 |
| `redis` | `feedbacker-redis` | 6379 | cache + queue |
| `mailpit` | `feedbacker-mailpit` | 8025 (UI), 1025 (SMTP) | catches all mail |
| `minio` | `feedbacker-minio` | 9000 (API), 9001 (console) | S3-compatible |
| `minio-init` | `feedbacker-minio-init` | — | one-shot: makes the bucket public |

Console: http://localhost:9001 — `minioadmin` / `minioadmin`.

### The application never names a disk

Everything goes through Laravel's `Storage` facade and `App\Support\Uploads`,
and `FILESYSTEM_DISK` selects the backend. Switching from MinIO to real S3, or
to the local filesystem, is a config change with no code change.

```bash
make minio-init     # (re)apply the bucket policy
make minio-shell    # mc + a shell inside the MinIO container
make storage-link   # only needed when FILESYSTEM_DISK=local
```

### Two URLs, and why

`docker/.env-common` sets both:

| Variable | Value | Who uses it |
|----------|-------|-------------|
| `AWS_ENDPOINT` | `http://minio:9000` | the **app container** uploads here |
| `AWS_URL` | `http://localhost:9000/feedbacker` | the **browser** loads images from here |

The app reaches MinIO by its compose service name; the browser cannot resolve
that name and needs the published port. Getting these the wrong way round
produces URLs that upload fine and then 404 in the page.

### Uploads

| Route | Field | Who |
|-------|-------|-----|
| `POST /spaces/{slug}/logo` | `logo` | space owner, or `spaces.update.any` |
| `DELETE /spaces/{slug}/logo` | — | same |
| `POST /testimonials/{id}/avatar` | `avatar` | space owner, or `testimonials.moderate.any` |
| `DELETE /testimonials/{id}/avatar` | — | same |

Validation (`UploadImageRequest`):

- **PNG, JPG or WebP**, up to **2 MB**, at most **2400×2400** pixels
- The dimension rule matters: a decompression bomb passes the size check but
  not this one, and reading the dimensions requires GD built with WebP support
- **SVG is deliberately rejected** — an SVG can carry script, and these files
  are served from a bucket browsers load directly

Files are stored under a deterministic path (`spaces/{id}/logo.{ext}`), so
replacing a logo removes the previous file whatever its extension. The stored
column stays a **URL**, so a pasted link and an upload are interchangeable.

The bucket is set to **anonymous download** rather than issuing presigned URLs:
logos and avatars appear on public collection pages and in widgets, so they are
public assets. Signed URLs would expire and break embedded walls.

> **Known gap:** deleting a space removes its row but leaves its objects in the
> bucket. Deleting storage inside a database transaction is worse than an
> orphan, so cleanup belongs in a job that sweeps objects with no owning row.

### Configuring an embed

Options travel in the query string, so one space can power several
differently-configured walls without touching the database:

| Parameter | Default | Notes |
|-----------|---------|-------|
| `theme` | `light` | `light` or `dark` |
| `columns` | `3` | 1–6 |
| `limit` | `50` | 1–200 |
| `accent` | space branding | `#RGB` or `#RRGGBB` |
| `font` | space branding | must be a plain font stack |
| `heading` | `What our customers say` | max 120 chars |
| `ratings` | `1` | `0` hides the stars |
| `footer` | `1` | `0` hides the attribution |

```
/w/acme-inc.js?theme=dark&columns=2&limit=12&accent=%23112233
```

Anything missing or out of range falls back to its default, and `accent`/`font`
are validated because they are interpolated into a CSS declaration.

### Widget service endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/health` | Liveness; pings PostgreSQL |
| `GET` | `/w/{slug}.js` | The space-wide embed (the `.js` is optional) |
| `GET` | `/w/{slug}/{token}.js` | A saved widget, configured by its row |
| `GET` | `/api/public/spaces/{slug}` | Public space projection |
| `GET` | `/api/public/spaces/{slug}/wall` | Approved testimonials; `?limit=`, `?featured_only=`, `?min_rating=` |
| `GET` | `/api/public/spaces/{slug}/testimonials` | Alias for `/wall` |
| `GET` | `/api/public/widgets/{token}` | A saved widget's wall, filters applied server-side |
| `POST` | `/api/track/view` | Record an impression |
| `POST` | `/api/track/click` | Record a card click |

The `{slug}` in `/w/{slug}/{token}.js` is decoration for readability, but it
must agree with the token: a stale snippet pointing at the wrong space is
rejected rather than silently serving another account's reviews.

`author_email` is never exposed — it is collected on the collection page and
stays in the application.

### Caching and rate limiting

`/wall` responses are cached **in process** for 60 seconds. For a single
instance that beats a network hop to Redis and needs no extra dependency; the
trade-off is that an approved testimonial can take up to a minute to appear.
Instant invalidation would require the two services to share a version counter,
which is the natural upgrade when the service is scaled horizontally.

Tracking endpoints are throttled to **60 requests/minute per IP** with a
fixed-window counter. Reads are not throttled — widget impressions are exactly
the traffic that must stay fast. The client-supplied IP is ignored; only the
connection's address is recorded.

### Analytics

The dashboard and each space show views, clicks and click rate over a window of
7, 30 or 90 days. The per-space analytics page at `/spaces/{slug}/analytics`
adds a daily bar chart, the most-clicked testimonials, and the embed snippet.

Aggregation lives in `App\Support\Analytics` and always takes a collection of
space ids, so the caller decides the scope: a member passes their own spaces, an
administrator passes all of them.

> Analytics are append-only. `widget_views` keeps its row when a testimonial is
> deleted (the reference is nulled); `testimonial_clicks` cascades away with it.

---

## Roles and permissions

Authorisation is powered by
[spatie/laravel-permission](https://spatie.be/docs/laravel-permission). Three
roles ship today:

| Role | Assigned | Purpose |
|------|----------|---------|
| `admin` | by `AdminUserSeeder` (local dev) | Cross-account oversight |
| `translator` | manually | Maintains the translation catalogue, with no access to customer data |
| `user` | automatically on registration | Standard account holder |

Permissions follow a `<resource>.<action>[.scope]` convention. The `.any`
suffix marks a cross-account power; everything else is governed by ownership
in the policy and needs no permission.

| Permission | `admin` | `translator` | `user` |
|------------|:-------:|:------------:|:------:|
| `spaces.view.any` | ✅ | — | — |
| `spaces.update.any` | ✅ | — | — |
| `spaces.delete.any` | ✅ | — | — |
| `testimonials.moderate.any` | ✅ | — | — |
| `users.manage` | ✅ | — | — |
| `translations.manage` | ✅ | ✅ | — |

How that resolves in `SpacePolicy`:

| Ability | Owner | `admin` | Other `user` |
|---------|:-----:|:-------:|:------------:|
| `spaces.index` scope | own spaces | **all spaces** | own spaces |
| `view` / `update` / `delete` | ✅ | ✅ | 403 |
| `moderateTestimonials` (inbox) | ✅ | ✅ | 403 |

Ownership alone authorises own-resource operations, so an account whose role
was revoked still keeps access to its own data.

Controllers pass the resolved abilities to the front end, so the UI never
guesses: `spaces.index` sends a per-space `can: { update, delete }` object and
`spaces.show` sends `can: { update, delete }`.

### Adding a role or permission

1. Add the case to `app/Enums/RoleEnum.php` or `app/Enums/PermissionEnum.php`.
2. Grant it in `database/seeders/RolePermissionSeeder.php`.
3. Run `make seed` — the seeder is idempotent.

Enums keep names in one place, so a typo becomes a compile-time error rather
than a silent `PermissionDoesNotExist` at runtime.

---

## Services

| Service | Container | Host port | Description |
|---------|-----------|-----------|-------------|
| `app` | `feedbacker-app` | — | PHP 8.4 FPM, runs the Laravel app |
| `queue-worker` | `feedbacker-queue` | — | `queue:work`, same image as `app` |
| `nginx` | `feedbacker-nginx` | 8080 | Reverse proxy in front of `app` |
| `widget-server` | `feedbacker-widget` | 8081 | Go service: public API + JS embed |
| `pgsql` | `feedbacker-pgsql` | 5432 | PostgreSQL 15 |
| `redis` | `feedbacker-redis` | 6379 | Cache, queue and sessions |
| `mailpit` | `feedbacker-mailpit` | 8025 / 1025 | Catches all outgoing mail |
| `minio` | `feedbacker-minio` | 9000 / 9001 | S3-compatible object storage |
| `node` | `feedbacker-node` | — | Frontend tooling, on demand only |

Ports are configurable through `APP_PORT`, `WIDGET_PORT`, `DB_PORT` and
`REDIS_PORT` in `.env`.

---

## Commands

Run everything from `docker/`. `make help` prints the same list.

### Containers

```bash
make up          # build + start everything
make down        # stop and remove containers
make restart     # down, then up --build
make stop        # stop without removing
make ps          # container status
make logs        # follow all logs
```

### PHP / Laravel

```bash
make env                # create laravel/.env + APP_KEY (safe to re-run)
make vendor             # composer install
make migrate            # php artisan migrate
make fresh              # php artisan migrate:fresh
make seed               # create the admin account
make test               # run the PHP test suite
make i18n               # apply translations: import + rebuild + clear caches
make artisan cmd="route:list"
make composer cmd="require foo/bar"
make shell              # bash inside the app container
make tinker             # php artisan tinker
```

### Frontend

Node never runs on the host — it runs in a throwaway container:

```bash
make npm-install            # npm install
make npm-build              # production asset build
make npm-dev                # Vite dev server on http://localhost:5173
make npm cmd="install @vueuse/core"
```

### Database

```bash
make db-shell               # psql inside the pgsql container
make redis-shell            # redis-cli
make test_db                # create the PostgreSQL test database
make test_db_migration      # clone the schema into it (structure only)
make test_db_drop           # drop the test database
make test-db-shell          # psql into the test database
```

### Go widget server

The Go sources live in `../go-widget` and are built into the `widget-server`
container:

```bash
make go-test        # unit tests (no database needed)
make go-lint        # golangci-lint
make go-build       # local binary in ../bin
make restart        # rebuild the container image
```

### Housekeeping

```bash
make fix-perms      # re-own bind-mounted files as $(UID):$(GID)
```

---

## Configuration

Three files, three jobs:

| File | Committed | Purpose |
|------|-----------|---------|
| `docker/.env-common` | yes | Docker defaults: DB credentials, ports, Redis client, UID/GID |
| `docker/.env` | no | Your local overrides |
| `laravel/.env` | no | Application config, including `APP_KEY` and the admin account |

`APP_KEY`, `APP_ENV` and `APP_DEBUG` deliberately live **only** in
`laravel/.env`. Laravel's Dotenv never overwrites an environment variable that
already exists, so injecting them from Compose would silently shadow the
application config.

### Environment variables you are most likely to change

| Variable | Default | Where |
|----------|---------|-------|
| `DB_USERNAME` / `DB_PASSWORD` | `feedbacker` / `secret` | `docker/.env` |
| `DB_DATABASE` | `feedbacker` | `docker/.env` |
| `APP_PORT` | `8080` | `docker/.env` |
| `WIDGET_PORT` | `8081` | `docker/.env` |
| `WIDGET_PUBLIC_URL` | `http://localhost:8081` | `docker/.env` — the widget service as the browser sees it |
| `WIDGET_DEMO_SPACE` | `admin-space` | `docker/.env` — which space the landing page demonstrates |
| `GITHUB_TOKEN` | empty | `docker/.env` — for Composer private packages |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | `admin@feedbacker.test` / `password` | `laravel/.env` |

### Favicon

A thumbs-up on green. The files live in `laravel/public/` and are linked from
`resources/views/app.blade.php`:

| File | Used for |
|------|----------|
| `favicon.svg` | Modern browsers; scales without loss |
| `favicon.ico` | Fallback, and anything that requests `/favicon.ico` implicitly |
| `apple-touch-icon.png` | The icon iOS uses when the site is added to the home screen |
| `favicon-512.png` | Large size for manifests and previews |

The vector and raster files come from one geometry: the ICO and PNGs are
rasterised from the same path the SVG carries, so they cannot drift apart.

### Roles

Roles and permissions live in the database, not in config. See
[Roles and permissions](#roles-and-permissions).

---

## Testing

```bash
make test               # clone the schema, then run the suite
make test_feature       # Feature suite only
make test_unit          # Unit suite only
make test_db            # create the test database, if missing
make test_db_migration  # clone the structure from the development database
make test_db_drop       # drop the test database
make test-db-shell      # psql into the test database
```

### How the test database works

The suite runs against **PostgreSQL**, not SQLite — same engine as production,
so JSON columns, `LIKE` semantics and constraints behave identically.

The test database (`feedbacker_test`) is **not built by migrations**. Its
schema is cloned from the development database:

```
make migrate            # develop the schema in `feedbacker`
make test_db_migration  # pg_dump --schema-only → `feedbacker_test`
```

`make test` runs `test_db_migration` first, so the schema can never drift from
the migrations that built the development database. After adding a migration
you only need `make migrate && make test`.

Because the schema is pre-built, tests **do not migrate**. Each test runs
inside a transaction (`DatabaseTransactions`) that is rolled back afterwards,
so nothing one test writes can be seen by the next. `Tests\TestCase` checks
that the schema is present and, if it is not, fails with an actionable message
telling you to run `make test_db_migration`.

### Fixtures

Test data comes from `tests/Fixtures/TestFixturesSeeder.php` — one canonical
dataset that every test shares:

| Account | Role | Spaces |
|---------|------|--------|
| `admin@feedbacker.test` | `admin` | none (oversight only) |
| `owner@feedbacker.test` | `user` | `acme-inc` (active), `globex` (closed) |
| `stranger@feedbacker.test` | `user` | `initech` |

`acme-inc` holds four testimonials — Alice (approved + featured), Bob
(approved), Carol (pending) and Dave (rejected) — so status filters, counts and
moderation flows all have something to work with. Their `created_at` values are
staggered a day apart on purpose: the schema stores timestamps with second
precision, so records created in the same second tie and ordering assertions
would be non-deterministic.

Tests reach the dataset through typed helpers instead of building their own
records:

```php
$this->actingAs($this->fixtureOwner())
    ->get(route('spaces.show', $this->fixtureSpace()->slug))
    ->assertOk();

$this->fixtureTestimonial(TestFixturesSeeder::PENDING);
$this->fixtureAdmin();  $this->fixtureStranger();
$this->fixtureInactiveSpace();  $this->fixtureForeignSpace();
```

Factories are still there for the cases that need a record the fixtures do not
cover — a roleless account, a third space, an ad-hoc testimonial.

### Adding a fixture

1. Add a constant and the record in `tests/Fixtures/TestFixturesSeeder.php`.
2. If it needs a lookup helper, extend `tests/Concerns/InteractsWithFixtures.php`.
3. Every test sees it automatically — the seeder runs in `setUp()`.

> The suite boots a fresh application (and a fresh database connection) per
> test, so a full run takes around 12 seconds. That is the cost of running
> against a real PostgreSQL server rather than an in-process SQLite file.

---

## Architecture notes

**Why both PHP and Go?** Laravel handles the dashboard, authentication and
CRUD. The Go service serves the embeddable widget and the public read API —
traffic patterns where a small, fast, stateless binary pays off.

**Why roles *and* ownership?** Ownership is the primary guard: a space belongs
to exactly one account, and the policy checks `user_id` first. Roles only add
what ownership cannot express — cross-account oversight. That keeps the common
case fast (no permission lookup) and the privileged case explicit.

**Permissions.** The `app` image creates a user matching `APP_UID`/`APP_GID`
(seeded from `UID`/`GID`, default `1000`) and runs PHP-FPM workers as that user,
so anything PHP writes — logs, compiled Blade views, cache — stays owned by you
and the bind-mounted sources stay editable. `storage/` and `bootstrap/cache`
only need `775`; they are not world-writable.

> The build args are named `APP_UID`/`APP_GID` rather than `UID`/`GID` because
> those are shell specials. More importantly, the guard that switches the pool
> checks the user **by name**: busybox `id` does not resolve a numeric uid, so
> `id -u 1000` fails even when the user exists — which silently left the pool on
> `www-data` and made every PHP-written file owned by the wrong user.

If files ever end up owned by `root` (for example after running a command as
root inside the container), fix them with:

```bash
make fix-perms
```

**The test database is cloned, not migrated.** See
[Testing](#testing) below.

---

## Troubleshooting

**`make: command not found`**
Install `make` inside WSL: `sudo apt update && sudo apt install make`.

**HTTP 500 and `tempnam(): file created in the system's temporary directory`**
PHP cannot write to `storage/framework/views`. Run `make fix-perms`. If it
persists, `make shell` and check `id` versus `ls -ld /var/www/storage`.

**`Class "Predis\Client" not found`**
`REDIS_CLIENT` is set to `predis` but the package is missing. The image ships
the `phpredis` extension, so set `REDIS_CLIENT=phpredis` in `docker/.env` and
restart: `make restart`.

**Permission denied when editing files under `laravel/`**
The files were created by root inside the container. Run `make fix-perms`.

**Port already in use**
Change `APP_PORT` (or `DB_PORT`, `REDIS_PORT`) in `docker/.env`, then
`make restart`.

**Docker Hub `TLS handshake timeout` during build**
Transient network trouble reaching the registry. Retry `make build`, or
configure a registry mirror in Docker Desktop.

**Reset everything, including the database**

```bash
make down
docker compose --env-file .env-common down -v   # ⚠️ deletes volumes
make up && make migrate && make seed && make npm-build && make i18n
```

**502 after recreating a container**
nginx resolves the upstream once at startup and caches it for the life of the
process. `nginx/default.conf` puts the address in a variable and names Docker's
resolver (`resolver 127.0.0.11`), so recreating `app` no longer breaks the site.

**Buttons show raw keys such as `common.edit`**
A page prop shadowed a shared Inertia prop. Page props win, so a controller
passing its own `translations` replaces the shared map and every `t()` call on
that page renders the key. Reserved names: `auth`, `flash`, `locale`, `locales`,
`translations`; the catalogue uses `entries`, and a test fails if the collision
returns.

---

## Project status

| Phase | Scope | State |
|-------|-------|-------|
| 1 | Docker environment, PostgreSQL/Redis/Nginx, Go widget skeleton | done |
| 2 | Auth (Breeze + Vue 3), `Space` + `SpaceBranding`, CRUD, branding editor | done |
| 2.1 | Marketing landing page, project README, `make env` | done |
| 2.2 | RBAC (spatie/laravel-permission), `admin` role, permission-driven policies and UI abilities | done |
| 3 | Testimonials: public collection page `/c/{slug}`, moderation inbox, filters | done |
| 3.1 | Test suite on PostgreSQL: cloned test schema, transactional tests, fixture dataset | done |
| 4 | Wall of Love embed served by Go (shadow DOM, masonry, options), impression/click tracking, analytics pages | done |
| 5R | Widget builder: four layouts, per-widget filters, public tokens, live preview; video testimonials by URL | done |
| 6R | Email notifications (queued), tags, PostgreSQL full-text search | done |
| 7R | MinIO object storage: logo and avatar uploads, public bucket, disk-agnostic storage layer | done |
| 8R.5 | Localisation: en/ru, translation catalogue in the database, CRUD for admin and translator, switcher | done |
| 8R | Social imports, NPS, case studies, custom domains, integrations, billing | planned |

See `Agents.md` for the condensed command reference aimed at AI agents.
