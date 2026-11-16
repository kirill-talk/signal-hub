# Signal-to-Noise Hub — Руководство по проекту

> [English version](../en/README.md) · [Архитектура и план разработки](ARCHITECTURE_AND_PLAN.md) · [Пошаговый учебник и история создания](TUTORIAL_AND_BUILD_HISTORY.md)

**Signal-to-Noise Hub** — это персональный серверный агрегатор и фильтр контента на **Laravel 13** и **Livewire 4**, который собирает разрозненные ленты (релизы на GitHub, RSS/Atom-блоги, открытые сабреддиты, JSON-фиды), отсекает информационный шум по пользовательским правилам и доставляет очищенный поток через веб-ридер, приватный RSS и email-дайджест.

---

## Пользовательский сценарий (User Flow)

1. **Подключение источников (`/sources`, `/topics`):**
   Пользователь заводит темы (`Backend`, `Frontend`, `DevOps`, `Security`) и добавляет URL источников. `SourceManager` автоматически определяет адаптер (`rss`, `github`, `json`) по ссылке, отслеживает статус (`Active` / `Failing`), счётчик ошибок и кнопку ручного запуска `Fetch Now`.
2. **Фильтрация по правилам (`/rules`):**
   Каждая новая запись до сохранения проходит конвейер правил `NoiseFilterService`:
   * **Include (белый список):** пропускать в основную ленту только материалы с нужными ключевыми словами.
   * **Exclude (стоп-слова):** помечать шум флагом `is_noise = true` (записи не теряются, их можно найти во вкладке `All Stream` или через поиск).
   * **Highlight (приоритет):** выделять критически важные обновления флагом `is_highlighted = true`.
3. **Фоновый сбор и дедупликация:**
   * Планировщик каждую минуту запускает `FetchSourceJob` для источников, у которых истёк `fetch_interval_minutes`.
   * Запросы ограничены `RateLimiter::attempt()` (не более 10 запросов в минуту к одному домену), используют HTTP-кэширование `ETag` / `Last-Modified` (`304 Not Modified`), очистку HTML от XSS через `DOMDocument` (`HtmlSanitizer`) и дедупликацию по `external_id_hash` (SHA-256).
4. **Три канала потребления:**
   * **Веб-лента (`/feed`):** Livewire-ридер с вкладками (`Signal`, `Unread`, `Bookmarks`, `All Stream`), фильтрами по темам и источникам, полнотекстовым поиском через `laravel/scout`, боковой шторкой чтения и горячими клавишами (`j`/`k`, `Enter`, `m`, `b`, `Esc`).
   * **Приватный RSS (`/feed/{token}.xml` и `/feed/{token}/{topicSlug}.xml`):** персональные ссылки, отдающие последние 50 записей с `is_noise = false` (кэшируются на 15 минут через `Cache::remember`).
   * **Email-дайджест (`/settings` + `digests:send`):** рассылка непрочитанных важных материалов, сгруппированных по темам, в выбранный час с учётом часового пояса пользователя (`$user->timezone`).

---

## Структура папок проекта

```text
signal-hub/
├── app/
│   ├── Console/Commands/       # Artisan-команды: sources:fetch, digests:send
│   ├── Contracts/              # Интерфейс SourceAdapterInterface
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
│   ├── factories/              # Фабрики для всех моделей
│   ├── migrations/             # Схема 5 таблиц и системных таблиц очередей/кэша
│   └── seeders/                # DatabaseSeeder с демо-аккаунтом demo@signalhub.test
├── docs/                       # Двуязычная документация (en / ru)
├── resources/views/
│   ├── auth/                   # login.blade.php, register.blade.php
│   ├── components/layouts/     # app.blade.php, guest.blade.php
│   ├── emails/                 # daily-digest.blade.php
│   ├── feeds/                  # rss.blade.php
│   └── livewire/               # Шаблоны Livewire-компонентов
├── routes/
│   ├── web.php                 # Веб-маршруты и приватные RSS-эндпоинты
│   └── console.php             # Расписание Schedule
└── tests/                      # Unit и Feature тесты (39 тестов, 231 проверка)
```

---

## Установка, запуск и воркеры

```bash
cd /home/local/projects/signal-hub

# Установка зависимостей и первичная настройка
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate:fresh --seed

# Запуск локального веб-сервера
php artisan serve
```

* **Демо-аккаунт:** `demo@signalhub.test` / `password`

### Фоновые процессы и консольные команды
```bash
# Ручной опрос всех источников (или конкретного по ID)
php artisan sources:fetch
php artisan sources:fetch 1

# Запуск воркера очередей (обработка FetchSourceJob и SendDigestMailJob)
php artisan queue:work --tries=3

# Запуск локального планировщика (опрос лент раз в минуту, рассылка дайджестов раз в 15 минут)
php artisan schedule:work

# Просмотр зарегистрированных задач планировщика
php artisan schedule:list

# Ручная проверка и отправка подходящих по времени дайджестов
php artisan digests:send

# Запуск всех автотестов
php artisan test
```

---

## Горячие клавиши в Feed Hub

| Клавиша | Действие |
| :--- | :--- |
| `j` / `k` | Переместить фокус на следующую / предыдущую запись |
| `Enter` | Открыть выбранную запись в боковой шторке чтения |
| `m` | Переключить статус «Прочитано / Непрочитано» |
| `b` | Добавить или убрать запись из закладок |
| `Esc` | Закрыть боковую шторку чтения |
