# Signal-to-Noise Hub — Project Guide

> [Русская версия](../ru/README.md) · [Architecture & Implementation Plan](ARCHITECTURE_AND_PLAN.md) · [Step-by-Step Laravel Tutorial & Build History](TUTORIAL_AND_BUILD_HISTORY.md)

**Signal-to-Noise Hub** is a personal, server-side content aggregator and noise-filtering reader built on **Laravel 13** and **Livewire 4**. It collects disparate feeds (GitHub Releases `.atom`, RSS/Atom blogs, public subreddits `.rss`, and JSON Feeds), filters noise using custom user rules, and delivers the curated stream through a web reader, private RSS endpoints, and scheduled email digests.

---

## User Flow

1. **Connect Sources (`/sources`, `/topics`):**
   Create color-coded topics (`Backend`, `Frontend`, `DevOps`, `Security`) and attach feed URLs. `SourceManager` auto-detects the adapter (`rss`, `github`, `json`) from the URL, tracks source health (`Active` / `Failing`, `error_count`, `last_error`), and provides a manual `Fetch Now` queue trigger.
2. **Filter Through the Rule Pipeline (`/rules`):**
   Every newly ingested entry is evaluated by `NoiseFilterService` before saving:
   - **Include (whitelist):** allow only matching entries into the main signal stream when active include rules exist.
   - **Exclude (stop-words):** mark matching entries with `is_noise = true` (preserved in the database and still searchable under **All Stream**).
   - **Highlight (priority):** flag critical matching entries with `is_highlighted = true`.
3. **Background Ingestion & Deduplication:**
   - The Laravel scheduler triggers `FetchSourceJob` every minute for sources whose `fetch_interval_minutes` has elapsed.
   - Requests enforce per-domain throttling via `RateLimiter::attempt()` (max 10 requests/minute per host), conditional HTTP caching (`If-None-Match` / `If-Modified-Since` → `304 Not Modified`), XSS sanitization via `DOMDocument` (`HtmlSanitizer`), and SHA-256 deduplication (`external_id_hash`).
4. **Three Consumption Channels:**
   - **Web Feed Hub (`/feed`):** Livewire reader with tabs (`Signal`, `Unread`, `Bookmarks`, `All Stream`), topic/source filters, `laravel/scout` full-text search, a slide-over reading drawer, and keyboard shortcuts (`j`/`k`, `Enter`, `m`, `b`, `Esc`).
   - **Private RSS (`/feed/{token}.xml` and `/feed/{token}/{topicSlug}.xml`):** token-authenticated RSS 2.0 XML feeds containing the latest 50 entries where `is_noise = false`, cached for 15 minutes with `Cache::remember`.
   - **Email Digest (`/settings` + `digests:send`):** scheduled command running every 15 minutes that converts UTC to each user's `$user->timezone` and emails unread signal items grouped by topic via `SendDigestMailJob` and `DailyDigestMail`.

---

## Directory Structure

```text
signal-hub/
├── app/
│   ├── Console/Commands/       # Artisan commands: sources:fetch, digests:send
│   ├── Contracts/              # SourceAdapterInterface contract
│   ├── DataTransferObjects/    # Readonly DTO EntryData
│   ├── Http/
│   │   ├── Controllers/        # AuthController, PrivateFeedController
│   │   └── Requests/           # StoreTopicRequest, SaveSourceRequest, SaveFilterRuleRequest
│   ├── Jobs/                   # FetchSourceJob, SendDigestMailJob
│   ├── Livewire/               # TopicManager, SourceManager, FilterRuleManager, FeedHub, ProfileSettings
│   ├── Mail/                   # DailyDigestMail
│   ├── Models/                 # User, Topic, Source, Entry, FilterRule
│   └── Services/               # EntryIngestionService, HtmlSanitizer, NoiseFilterService, Adapters/
├── config/                     # database.php (SQLite WAL), scout.php (database)
├── database/
│   ├── factories/              # Model factories
│   ├── migrations/             # Schema for 5 core tables + queue/cache tables
│   └── seeders/                # DatabaseSeeder with demo account demo@signalhub.test
├── docs/                       # Bilingual documentation (en / ru)
├── resources/views/
│   ├── auth/                   # login.blade.php, register.blade.php
│   ├── components/layouts/     # app.blade.php, guest.blade.php
│   ├── emails/                 # daily-digest.blade.php
│   ├── feeds/                  # rss.blade.php
│   └── livewire/               # Livewire Blade views
├── routes/
│   ├── web.php                 # Authenticated UI routes & private RSS endpoints
│   └── console.php             # Schedule definitions
└── tests/                      # Unit & Feature tests (39 tests, 231 assertions)
```

---

## Installation, Workers & CLI Reference

```bash
cd /home/local/projects/signal-hub

# Install dependencies and initialize environment
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate:fresh --seed

# Start local development server
php artisan serve
```

- **Demo credentials:** `demo@signalhub.test` / `password`

```bash
# Manually fetch all sources (or a single source ID)
php artisan sources:fetch
php artisan sources:fetch 1

# Run background queue worker
php artisan queue:work --tries=3

# Run local scheduler (source polling every minute, digests every 15 minutes)
php artisan schedule:work

# Inspect scheduled tasks
php artisan schedule:list

# Manually dispatch due email digests
php artisan digests:send

# Run the complete test suite
php artisan test
```

---

## Feed Hub Keyboard Shortcuts

| Key | Action |
| :--- | :--- |
| `j` / `k` | Move focus down / up through entries |
| `Enter` | Open focused entry in the slide-over reading drawer |
| `m` | Toggle read / unread status |
| `b` | Toggle bookmark status |
| `Esc` | Close the reading drawer |
