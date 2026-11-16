# Step-by-Step Laravel Tutorial & Build History — Signal-to-Noise Hub

> [Русская версия](../ru/TUTORIAL_AND_BUILD_HISTORY.md) · [Project Guide](README.md) · [Architecture & Implementation Plan](ARCHITECTURE_AND_PLAN.md)

This document preserves the complete step-by-step build history of **Signal-to-Noise Hub** — every CLI command executed, the Laravel and PHP concepts learned at each milestone, and what was built across Phases 1–6.

---

## Phase 1. Foundation, Database Schema & Source CRUD

### Step 1.1: Bootstrap Laravel 13 & Livewire 4
- **Commands:**
  ```bash
  cd /home/local/projects/signal-hub
  composer create-project laravel/laravel .
  composer require livewire/livewire
  ```
- **Concepts Learned:**
  - `composer` manages PHP dependencies in `composer.json` and `vendor/`.
  - Passing `.` to `composer create-project` installs directly into the current directory, creates `.env`, generates `APP_KEY`, creates `database/database.sqlite`, and runs initial migrations (`users`, `cache`, `jobs`).

### Step 1.2: SQLite WAL Configuration & Generating Models/Migrations
- **Configuration (`config/database.php`):** set `'busy_timeout' => 5000` and `'journal_mode' => 'wal'` on the `sqlite` connection to allow concurrent reads while queue workers write.
- **Commands:**
  ```bash
  php artisan make:model Topic -m
  php artisan make:model Source -m
  php artisan make:model Entry -m
  php artisan make:model FilterRule -m
  ```
- **Concepts Learned:**
  - Laravel maps singular `PascalCase` models (`FilterRule`) to plural `snake_case` tables (`filter_rules`).
  - `-m` (`--migration`) generates the paired migration file in `database/migrations/`.

### Step 1.3: Migrations & Schema Creation
- **Key Blueprint Patterns:**
  - `$table->foreignId('user_id')->constrained()->cascadeOnDelete()` cascades deletion of user-owned rows.
  - `->nullable()->constrained()->nullOnDelete()` preserves sources and entries when a `Topic` is deleted.
  - Composite unique indexes: `UNIQUE(user_id, slug)` on `topics` and `UNIQUE(user_id, external_id_hash)` on `entries`.
- **Command:**
  ```bash
  php artisan migrate:fresh
  ```
  Drops and rebuilds all tables cleanly after adding custom user columns (`timezone`, `feed_token`, `digest_frequency`, `digest_time`, `last_digest_sent_at`).

### Step 1.4: Configuring Eloquent Models
- **Configured in `app/Models/`:**
  - `$fillable` guards against mass-assignment vulnerabilities.
  - `casts()` converts SQLite storage into native PHP types (`boolean`, `integer`, `array` for JSON, `datetime` for `Carbon` instances).
  - `booted()` in `User.php` automatically generates a UUID `feed_token` on `static::creating(...)`.
  - `hasMany` and `belongsTo` relationships across all 5 models.

### Step 1.5: Server-Side Validation (Form Requests)
- **Commands:**
  ```bash
  php artisan make:request StoreTopicRequest
  php artisan make:request SaveSourceRequest
  ```
- **Concepts Learned:**
  - `'url:http,https'` blocks non-HTTP schemes (`file://`, `javascript:`).
  - `Rule::exists('topics', 'id')->where('user_id', Auth::id())` enforces ownership of referenced topics.
  - **PSR-4 Autoloading:** PHP filenames must match the exact `class` name inside them.

### Step 1.6 & Phase 1 Completion (Antigravity)
- **Commands:**
  ```bash
  php artisan make:livewire TopicManager --class
  php artisan make:livewire SourceManager --class
  ```
- **Highlights:**
  - Livewire lifecycle hook `updatedUrl()` auto-detects `github`, `json`, or `rss` adapter types.
  - Eager loading `with('topic')` and `withCount(['sources', 'entries'])` prevents **N+1 queries**.
  - Added `AuthController`, `guest.blade.php` / `app.blade.php`, Tailwind + Alpine.js delete modals, factories (`TopicFactory`, `SourceFactory`, `EntryFactory`, `FilterRuleFactory`), `DatabaseSeeder` (`demo@signalhub.test` / `password`), and `PhaseOneCrudTest`.

---

## Phase 2. Adapter Layer & Manual Ingestion

### Step 2.1: `EntryData` DTO & `SourceAdapterInterface`
- **Commands:**
  ```bash
  mkdir -p app/DataTransferObjects app/Contracts
  touch app/DataTransferObjects/EntryData.php app/Contracts/SourceAdapterInterface.php
  ```
- **Concepts Learned:**
  - **`readonly class EntryData`** using Constructor Property Promotion creates an immutable normalized record across RSS, Atom, and JSON formats.
  - **`interface SourceAdapterInterface`** defines the `fetch(Source $source): Collection` contract.

### Step 2.2: `RssAtomAdapter`, `JsonFeedAdapter` & `AdapterFactory`
- **Highlights:**
  - Laravel `Http::timeout(15)->withHeaders(...)->get($source->url)->throw()`.
  - `SimpleXMLElement($body, LIBXML_NOCDATA)` extracts `<![CDATA[...]]>` bodies cleanly.
  - Atom `<link rel="alternate" href="..." />` attribute extraction.
  - `AdapterFactory` resolves adapters via Laravel's **Service Container** (`app(...)`).

### Step 2.3 & Phase 2 Completion (Antigravity)
- **Command:**
  ```bash
  php artisan make:command FetchSourcesCommand
  ```
- **Highlights:**
  - `EntryIngestionService` leverages constructor Dependency Injection, computes SHA-256 `external_id_hash`, and skips duplicates.
  - `php artisan sources:fetch {source_id?}` records network/XML exceptions to `status = 'failing'`, `error_count`, and `last_error`.
  - `HtmlSanitizer` (built with `DOMDocument`) strips `<script>`, `<iframe>`, `<style>`, tracking `<img>` tags, inline `on*` event attributes, and `javascript:` URLs.
  - Added XML namespace handling (`content:encoded`, `dc:creator`, `dc:date`, `atom:link`) and fixture-based tests (`HtmlSanitizerTest`, `SourceAdaptersTest`, `FetchSourcesCommandTest`).

---

## Phase 3. Queues, Scheduler & Rate Limiting

### Step 3.1: `FetchSourceJob`, RateLimiter & Conditional HTTP Caching
- **Command:**
  ```bash
  php artisan make:job FetchSourceJob
  ```
- **Highlights:**
  - `If-None-Match` (`etag`) and `If-Modified-Since` (`last_modified`) headers short-circuit on `304 Not Modified`.
  - `FetchSourceJob implements ShouldQueue` configured with `$tries = 3`, `$timeout = 15`, and `$backoff = [30, 120, 300]`.
  - `RateLimiter::attempt("fetch-domain:{$host}", 10, ..., 60)` limits requests to 10/min per domain and calls `$this->release(15)` when throttled.

### Step 3.2 & Phase 3 Completion (Antigravity)
- **Schedule (`routes/console.php`):**
  ```php
  Schedule::call(function (): void { ... })->name('dispatch-due-sources')->everyMinute()->withoutOverlapping();
  ```
- **Verification Command:** `php artisan schedule:list`
- **Antigravity Additions:** `Fetch Now` button with Livewire loading states, source `entries_count`, `ETag cached` indicator, inline `last_error` display, and `PhaseThreeQueueAndRateLimitTest` (`Queue::fake()`, `Http::fake()`, `schedule:run`, and `RateLimiter` throttling).

---

## Phase 4. Signal-to-Noise Engine (`NoiseFilterService`)

### Step 4.1: `NoiseFilterService` & Ingestion Integration
- **Highlights:**
  - UTF-8 case-insensitive matching via `mb_stripos($haystack, $needle) !== false`.
  - Scope matching (`source_id`, `topic_id`, or global `null`) evaluated in strict order: `include` → `exclude` → `highlight`.
  - Wired into `EntryIngestionService::ingest()` prior to `Entry::create(...)`.

### Step 4.2 & Phase 4 Completion (Antigravity)
- **Commands:**
  ```bash
  php artisan make:request SaveFilterRuleRequest
  php artisan make:livewire FilterRuleManager --class
  ```
- **Highlights:**
  - Nested array validation via `'keywords.*' => ['required', 'string', 'max:80']`.
  - `previewRule(NoiseFilterService $noiseFilter)` evaluates an in-memory draft rule against existing user entries.
  - Alpine.js keyword chip builder (`$wire.entangle('keywords')`), `Active / Paused` toggle, `/rules` route, and `NoiseFilterServiceTest` + `FilterRuleManagerTest`.

---

## Phase 5. Web Feed Hub & Full-Text Search

### Step 5.1: Installing `laravel/scout` & Indexing `Entry`
- **Commands:**
  ```bash
  composer require laravel/scout
  php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
  ```
- **Highlights:** configured `SCOUT_DRIVER=database` in `.env` and `config/scout.php`, and added `Searchable` + `toSearchableArray()` to `Entry.php`.

### Step 5.2 & Phase 5 Completion (Antigravity)
- **Command:**
  ```bash
  php artisan make:livewire FeedHub --class
  ```
- **Highlights:**
  - Livewire 4 `#[Url]` attributes keep tab, topic, source, and search query synced in the browser URL.
  - Separated `applyScopeFilters()` (no `ORDER BY`, safe for SQLite `UPDATE` in `markAllAsRead()`) from `applyFilters()` (eager loading + ordering).
  - `Entry::search($term)->query(...)` runs Scout search scoped to the authenticated user and active filters.
  - Built `feed-hub.blade.php` with tabs, slide-over reading drawer, Alpine.js keyboard shortcuts (`j`/`k`/`Enter`/`m`/`b`/`Esc`), `/feed` route, and `PhaseFiveFeedHubTest`.

---

## Phase 6. Output Channels: Private RSS & Email Digests

### Step 6.1: `PrivateFeedController` & RSS Routes
- **Command:**
  ```bash
  php artisan make:controller PrivateFeedController
  ```
- **Highlights:**
  - Token-authenticated `/feed/{token}.xml` and `/feed/{token}/{topicSlug}.xml` endpoints cached for 15 minutes via `Cache::remember(...)` and returned with `Content-Type: application/rss+xml; charset=UTF-8`.

### Step 6.2a & 6.2b: `DailyDigestMail`, `SendDigestMailJob` & `SendScheduledDigests`
- **Commands:**
  ```bash
  php artisan make:mail DailyDigestMail
  php artisan make:job SendDigestMailJob
  php artisan make:command SendScheduledDigests
  ```
- **Highlights:**
  - `DailyDigestMail` groups unread signal items by topic (`Collection::groupBy(...)`).
  - `SendScheduledDigests` (`digests:send`) converts `now()` to each user's `$user->timezone`, checks the 15-minute delivery window, prevents duplicate sends via `last_digest_sent_at`, and dispatches `SendDigestMailJob`.
  - Registered `Schedule::command('digests:send')->everyFifteenMinutes()->withoutOverlapping();` in `routes/console.php`.

### Phase 6 Completion (Antigravity)
- Built valid RSS 2.0 XML template `resources/views/feeds/rss.blade.php` and responsive HTML email template `resources/views/emails/daily-digest.blade.php`.
- Added `ProfileSettings` (`/settings`) for timezone, digest frequency/time, and `feed_token` regeneration with RSS cache invalidation.
- Verified everything in `PhaseSixOutputChannelsTest` using `Mail::fake()`, `Queue::fake()`, `Carbon::setTestNow()`, and `simplexml_load_string()`.
