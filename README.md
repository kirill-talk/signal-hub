# Signal-to-Noise Hub

**Signal-to-Noise Hub** is a personal, server-side content aggregator and noise-filtering reader built on **Laravel 13** and **Livewire 4**. It transforms dozens of scattered feeds (GitHub Releases `.atom`, RSS/Atom blogs, public subreddits `.rss`, and JSON Feeds) into a single structured stream with zero extra infrastructure dependencies.

> **Documentation:**
> - **English:** [Project Guide](docs/en/README.md) · [Architecture & 6-Phase Plan](docs/en/ARCHITECTURE_AND_PLAN.md) · [Step-by-Step Laravel Tutorial & Build History](docs/en/TUTORIAL_AND_BUILD_HISTORY.md)
> - **Українська:** [Посібник по проєкту](docs/uk/README.md) · [Архітектура та план (6 фаз)](docs/uk/ARCHITECTURE_AND_PLAN.md) · [Покроковий підручник та історія розробки](docs/uk/TUTORIAL_AND_BUILD_HISTORY.md)

---

## How It Works

1. **Connect Sources (`/sources`, `/topics`):**
   Add feed URLs and assign them to custom color-coded topics (`Backend`, `Frontend`, `DevOps`, `Security`). `SourceManager` automatically detects the adapter (`rss`, `github`, `json`) from the URL and manages fetch intervals and health indicators (`Active` / `Failing`, `error_count`, `last_error`, `ETag cached`).
2. **Filter Through the Rule Pipeline (`/rules`):**
   Every ingested entry passes through `NoiseFilterService` before persistence using strict precedence:
   - **Include (whitelist):** if active `include` rules exist for the scope, only matching entries stay in the main signal stream.
   - **Exclude (stop-words):** matching entries are marked `is_noise = true` (preserved in the database and searchable under **All Stream**, never silently lost).
   - **Highlight (priority):** non-noise entries matching priority keywords get `is_highlighted = true`.
   - Includes an Alpine.js keyword chip builder and a **Test Rule on Existing Entries** live preview.
3. **Background Ingestion, Rate Limiting & Deduplication:**
   - The Laravel scheduler (`routes/console.php`) runs every minute and dispatches `FetchSourceJob` for sources whose `fetch_interval_minutes` has elapsed.
   - Requests use `RateLimiter::attempt()` (max 10 requests/minute per domain, releasing back to the queue on throttling), conditional HTTP headers (`If-None-Match` / `If-Modified-Since` → `304 Not Modified`), `DOMDocument` XSS sanitization (`HtmlSanitizer`), and SHA-256 deduplication (`external_id_hash`).
4. **Three Consumption Channels:**
   - **Web Feed Hub (`/feed`):** Livewire reader with tabs (`Signal`, `Unread`, `Bookmarks`, `All Stream`), topic/source filters, full-text search via `laravel/scout` (`SCOUT_DRIVER=database`), a slide-over reading drawer, and keyboard shortcuts (`j`/`k`, `Enter`, `m`, `b`, `Esc`).
   - **Private RSS 2.0 Feeds (`/feed/{token}.xml` & `/feed/{token}/{topicSlug}.xml`):** token-authenticated XML endpoints serving only `is_noise = false` entries, cached for 15 minutes via `Cache::remember`.
   - **Timezone-Aware Email Digests (`/settings` + `digests:send`):** scheduled command running every 15 minutes that converts UTC to each user's configured `timezone` (`Europe/Kyiv`, `UTC`, etc.) and sends unread signal entries grouped by topic via `SendDigestMailJob` and `DailyDigestMail`.

---

## Zero-Extra-Infrastructure Tech Stack

| Layer | Implementation |
| :--- | :--- |
| **Framework & PHP** | Laravel 13 (`^13.17`), PHP `^8.3` |
| **Database, Queue & Cache** | SQLite (`journal_mode=wal`, `busy_timeout=5000`) + `QUEUE_CONNECTION=database` + `CACHE_STORE=database` |
| **HTTP & Feed Parsing** | Laravel `Http` facade + native PHP `SimpleXMLElement` (RSS 2.0, Atom, `content:encoded`, `dc:creator`, `atom:link`) + `json_decode` (JSON Feed 1.1) |
| **HTML Sanitization** | Native PHP `DOMDocument` (`App\Services\HtmlSanitizer`) |
| **Full-Text Search** | `laravel/scout` with `SCOUT_DRIVER=database` |
| **Frontend & Interactivity** | Livewire 4 (`^4.4`), Alpine.js 3, Tailwind CSS, Blade components |

---

## Database Schema Overview

- **`users`**: authentication fields + `timezone`, `feed_token` (auto-generated UUID), `digest_frequency` (`none`, `daily`, `weekly`), `digest_time`, `last_digest_sent_at`.
- **`topics`**: `user_id`, `name`, `slug` (unique per user), `color`.
- **`sources`**: `user_id`, `topic_id`, `name`, `url`, `adapter_type` (`rss`, `github`, `json`), `fetch_interval_minutes`, `last_fetched_at`, `etag`, `last_modified`, `status`, `error_count`, `last_error`.
- **`entries`**: `user_id`, `source_id`, `topic_id`, `external_id_hash` (unique per user), `title`, `url`, `author`, `summary`, `published_at`, `is_read`, `is_bookmarked`, `is_noise`, `is_highlighted`, `raw_payload`.
- **`filter_rules`**: `user_id`, `source_id`, `topic_id`, `type` (`include`, `exclude`, `highlight`), `match_field` (`title`, `summary`, `both`), `keywords` (JSON array), `is_active`.

---

## Quick Start

```bash
# 1. Install PHP dependencies
composer install

# 2. Create environment config and SQLite database
cp .env.example .env
touch database/database.sqlite
php artisan key:generate

# 3. Run migrations and seed demo data
php artisan migrate:fresh --seed

# 4. Start the local server
php artisan serve
```

### Demo Account (created by `DatabaseSeeder`)
- **Email:** `demo@signalhub.test`
- **Password:** `password`

---

## CLI Commands & Background Workers

```bash
# Fetch all sources (or a single source by ID) synchronously
php artisan sources:fetch
php artisan sources:fetch 1

# Run the queue worker for background FetchSourceJob & SendDigestMailJob
php artisan queue:work --tries=3

# Run the Laravel scheduler locally (dispatches due sources every minute & digests every 15 minutes)
php artisan schedule:work

# Inspect registered scheduled tasks
php artisan schedule:list

# Manually evaluate and dispatch due email digests
php artisan digests:send
```

---

## Feed Hub Keyboard Shortcuts

| Key | Action |
| :--- | :--- |
| `j` / `k` | Move focus down / up in the entry list |
| `Enter` | Open the focused entry in the slide-over reading drawer |
| `m` | Toggle read / unread status on the active entry |
| `b` | Toggle bookmark on the active entry |
| `Esc` | Close the reading drawer |

---

## Running Tests

```bash
php artisan test
```

The test suite includes **39 Unit & Feature tests (231 assertions)** covering HTML XSS sanitization, RSS/Atom/JSON adapters against local fixtures, `sources:fetch` deduplication, queue/rate-limit/ETag behavior, `NoiseFilterService`, `FeedHub` Scout search, private RSS XML generation, timezone-aware digest scheduling, and profile settings.
