# Пошаговый учебник и история создания Signal-to-Noise Hub (Laravel 13)

> [English version](../en/TUTORIAL_AND_BUILD_HISTORY.md) · [Руководство по проекту](README.md) · [Архитектура и план разработки](ARCHITECTURE_AND_PLAN.md)

Этот документ сохраняет всю пошаговую историю разработки проекта — все команды терминала, ключевые концепции Laravel и PHP, а также распределение выполненных задач по всем 6 фазам.

---

## Фаза 1. Фундамент, схема БД и CRUD источников

### Шаг 1.1: Развёртывание Laravel 13 и Livewire 4
* **Команды:**
  ```bash
  cd /home/local/projects/signal-hub
  composer create-project laravel/laravel .
  composer require livewire/livewire
  ```
* **Что изучено:**
  * `composer` — менеджер зависимостей PHP (аналог `npm` в JS или `pip` в Python), управляющий библиотеками в `composer.json` и папке `vendor/`.
  * Точка `.` в `composer create-project laravel/laravel .` распаковывает каркас прямо в текущую папку, создаёт `.env`, ключ `APP_KEY`, файл `database/database.sqlite` и запускает базовые системные миграции (`users`, `cache`, `jobs`).

### Шаг 1.2: Настройка SQLite (WAL) и генерация моделей с миграциями
* **Настройка `config/database.php`:** в конфигурации подключения `'sqlite'` выставлены `'busy_timeout' => 5000` (ожидание до 5 секунд вместо ошибки `database is locked`) и `'journal_mode' => 'wal'` (неблокирующее чтение при параллельной записи воркерами).
* **Команды:**
  ```bash
  php artisan make:model Topic -m
  php artisan make:model Source -m
  php artisan make:model Entry -m
  php artisan make:model FilterRule -m
  ```
* **Что изучено:**
  * Имя модели пишется в единственном числе в `PascalCase`, а таблица в БД — во множественном числе в `snake_case` (`topics`, `sources`, `entries`, `filter_rules`).
  * Флаг `-m` (`--migration`) автоматически создаёт соответствующий файл миграции в `database/migrations/`.

### Шаг 1.3: Миграции и создание таблиц
* **Применённые конструкции Blueprint:**
  * `$table->foreignId('user_id')->constrained()->cascadeOnDelete()` — внешний ключ, каскадно удаляющий данные при удалении пользователя.
  * `->nullable()->constrained()->nullOnDelete()` — мягкая привязка: при удалении `Topic` источники и статьи сохраняются, сбрасывая `topic_id` в `NULL`.
  * Составные уникальные индексы: `$table->unique(['user_id', 'slug'])` в `topics` и `$table->unique(['user_id', 'external_id_hash'])` в `entries`.
* **Команда:**
  ```bash
  php artisan migrate:fresh
  ```
  Пересоздаёт схему с нуля, когда в существующие базовые миграции (например, `users`) добавляются новые колонки (`timezone`, `feed_token`, `digest_frequency`, `digest_time`, `last_digest_sent_at`).

### Шаг 1.4: Настройка моделей Eloquent
* **Что настроено в `app/Models/`:**
  * `$fillable` — защита от массового присвоения (Mass Assignment), разрешающая записывать только перечисленные поля.
  * `casts()` — преобразование типов SQLite в нативные PHP-типы (`boolean`, `integer`, `array` для JSON, `datetime` для объектов `Carbon`).
  * `booted()` в `User.php` — автоматическая генерация UUID для `feed_token` через событие `static::creating(...)`.
  * Связи `hasMany` и `belongsTo` между `User`, `Topic`, `Source`, `Entry` и `FilterRule`.

### Шаг 1.5: Серверная валидация (Form Requests)
* **Команды:**
  ```bash
  php artisan make:request StoreTopicRequest
  php artisan make:request SaveSourceRequest
  ```
* **Что изучено:**
  * Правило `'url:http,https'` защищает от невалидных и локальных схем (`file://`, `javascript:`).
  * Правило `Rule::exists('topics', 'id')->where('user_id', Auth::id())` гарантирует, что пользователь не сможет привязать чужую тему.
  * **Стандарт PSR-4:** имя PHP-файла должно буквально совпадать с именем `class` внутри него (`StoreTopicRequest.php` ↔ `class StoreTopicRequest`).

### Шаг 1.6 и завершение Фазы 1 (Antigravity)
* **Команды:**
  ```bash
  php artisan make:livewire TopicManager --class
  php artisan make:livewire SourceManager --class
  ```
* **Что изучено и сделано:**
  * Livewire-хук `updatedUrl()` автоматически определяет тип адаптера (`github`, `json`, `rss`) при вводе URL.
  * Жадная загрузка `with('topic')` и `withCount(['sources', 'entries'])` предотвращает проблему **N+1 SQL-запросов**.
  * Созданы `AuthController`, шаблоны `guest.blade.php` / `app.blade.php`, UI-интерфейсы с Alpine-модалками подтверждения удаления, фабрики (`TopicFactory`, `SourceFactory`, `EntryFactory`, `FilterRuleFactory`), `DatabaseSeeder` (демо-пользователь `demo@signalhub.test` / `password`) и `PhaseOneCrudTest`.

---

## Фаза 2. Архитектура адаптеров и ручной сбор данных

### Шаг 2.1: DTO `EntryData` и интерфейс `SourceAdapterInterface`
* **Команды:**
  ```bash
  mkdir -p app/DataTransferObjects app/Contracts
  touch app/DataTransferObjects/EntryData.php app/Contracts/SourceAdapterInterface.php
  ```
* **Что изучено:**
  * **`readonly class EntryData`** с Constructor Property Promotion — неизменяемый контейнер данных, унифицирующий записи из RSS, Atom и JSON.
  * **`interface SourceAdapterInterface`** — контракт с методом `fetch(Source $source): Collection`, гарантирующий единый интерфейс для всех адаптеров.

### Шаг 2.2: Адаптеры `RssAtomAdapter`, `JsonFeedAdapter` и `AdapterFactory`
* **Что изучено и реализовано:**
  * `Http::timeout(15)->withHeaders(...)->get($source->url)->throw()` — встроенный HTTP-клиент Laravel.
  * `SimpleXMLElement($body, LIBXML_NOCDATA)` — встроенный парсер XML с раскрытием `<![CDATA[...]]>`.
  * Извлечение ссылок Atom из атрибутов `<link rel="alternate" href="..." />`.
  * `AdapterFactory` с `match` и `app(...)` — разрешение нужного адаптера через **Service Container** Laravel.

### Шаг 2.3 и завершение Фазы 2 (Antigravity)
* **Команда:**
  ```bash
  php artisan make:command FetchSourcesCommand
  ```
* **Что изучено и сделано:**
  * **`EntryIngestionService`** — внедрение зависимостей через конструктор (Dependency Injection), вычисление SHA-256 отпечатка `external_id_hash` и пропуск дубликатов.
  * Консольная команда `php artisan sources:fetch {source_id?}` с обработкой ошибок `Throwable` и записью сбоев в `status = 'failing'`, `error_count` и `last_error`.
  * **`HtmlSanitizer`** на базе `DOMDocument` — очистка HTML от `<script>`, `<iframe>`, `<style>`, `<img>`-трекеров, `onclick`/`onerror` и `javascript:`-ссылок.
  * Поддержка XML-неймспейсов (`content:encoded`, `dc:creator`, `dc:date`, `atom:link`) и наборы тестов (`HtmlSanitizerTest`, `SourceAdaptersTest`, `FetchSourcesCommandTest`) на локальных фикстурах `tests/Fixtures/`.

---

## Фаза 3. Очереди, планировщик и Rate Limiting

### Шаг 3.1: `FetchSourceJob`, RateLimiter и HTTP-кэширование (`ETag` / `304`)
* **Команда:**
  ```bash
  php artisan make:job FetchSourceJob
  ```
* **Что изучено и сделано:**
  * Передача условных заголовков `If-None-Match` (`etag`) и `If-Modified-Since` (`last_modified`) через `array_filter()`. При статусе `304 Not Modified` адаптер завершает работу без парсинга.
  * `FetchSourceJob implements ShouldQueue` с `$tries = 3`, `$timeout = 15`, `$backoff = [30, 120, 300]`.
  * `RateLimiter::attempt("fetch-domain:{$host}", 10, ..., 60)` — ограничение до 10 запросов в минуту к одному домену с мягким откладыванием задачи через `$this->release(15)`.

### Шаг 3.2 и завершение Фазы 3 (Antigravity)
* **Настройка `routes/console.php`:**
  ```php
  Schedule::call(function (): void { ... })->name('dispatch-due-sources')->everyMinute()->withoutOverlapping();
  ```
* **Команда проверки расписания:**
  ```bash
  php artisan schedule:list
  ```
* **Сделано Antigravity:** кнопка `Fetch Now` в `SourceManager`, отображение `entries_count`, бейджа `ETag cached`, текста `last_error` и `PhaseThreeQueueAndRateLimitTest` (`Queue::fake()`, `Http::fake()`, `schedule:run`, тест превышения `RateLimiter`).

---

## Фаза 4. Движок фильтрации шума (`NoiseFilterService`)

### Шаг 4.1: `NoiseFilterService` и интеграция в `EntryIngestionService`
* **Что изучено:**
  * Регистронезависимый поиск по UTF-8 через `mb_stripos($haystack, $needle) !== false`.
  * Строгий порядок проверки правил по области действия (`source_id`, `topic_id`, глобальные): `include` → `exclude` → `highlight`.
  * Интеграция `$this->noiseFilter->evaluate(...)` непосредственно перед `Entry::create(...)`.

### Шаг 4.2 и завершение Фазы 4 (Antigravity)
* **Команды:**
  ```bash
  php artisan make:request SaveFilterRuleRequest
  php artisan make:livewire FilterRuleManager --class
  ```
* **Что изучено и сделано:**
  * Валидация элементов массива в Laravel через `'keywords.*' => ['required', 'string', 'max:80']`.
  * Метод `previewRule(NoiseFilterService $noiseFilter)` — оценка черновика правила в памяти без сохранения в БД.
  * UI `filter-rule-manager.blade.php` с вводом тегов на Alpine.js (`$wire.entangle('keywords')`), переключателем `Active / Paused`, маршрутом `/rules` и тестами `NoiseFilterServiceTest` + `FilterRuleManagerTest`.

---

## Фаза 5. Рабочая лента хаба и полнотекстовый поиск

### Шаг 5.1: Установка `laravel/scout` и настройка модели `Entry`
* **Команды:**
  ```bash
  composer require laravel/scout
  php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
  ```
* **Что изучено:**
  * Подключение драйвера `SCOUT_DRIVER=database` в `.env` и `config/scout.php`.
  * Трейт `Laravel\Scout\Searchable` и метод `toSearchableArray()` в `Entry.php`.

### Шаг 5.2 и завершение Фазы 5 (Antigravity)
* **Команда:**
  ```bash
  php artisan make:livewire FeedHub --class
  ```
* **Что изучено и сделано:**
  * Атрибуты `#[Url]` в Livewire 4 для сохранения состояния вкладок, фильтров и поиска в URL.
  * Разделение `applyScopeFilters()` (без `ORDER BY` для безопасного `UPDATE` в SQLite в `markAllAsRead()`) и `applyFilters()` (с `with()` и сортировкой).
  * Запрос `Entry::search($term)->query(...)` для поиска с сохранением всех фильтров и изоляции по `user_id`.
  * UI `feed-hub.blade.php` с вкладками, боковой шторкой чтения, хоткеями Alpine.js (`j`/`k`/`Enter`/`m`/`b`/`Esc`), маршрутом `/feed` и `PhaseFiveFeedHubTest`.

---

## Фаза 6. Выходные каналы: Приватный RSS и Email-дайджесты

### Шаг 6.1: Контроллер `PrivateFeedController`
* **Команда:**
  ```bash
  php artisan make:controller PrivateFeedController
  ```
* **Что изучено:**
  * Авторизация по секретному токену в URL (`/feed/{token}.xml` и `/feed/{token}/{topicSlug}.xml`) вне `auth` middleware.
  * Кэширование XML-ленты на 15 минут через `Cache::remember(...)` и выдача с заголовком `Content-Type: application/rss+xml; charset=UTF-8`.

### Шаг 6.2а и 6.2б: `DailyDigestMail`, `SendDigestMailJob` и `SendScheduledDigests`
* **Команды:**
  ```bash
  php artisan make:mail DailyDigestMail
  php artisan make:job SendDigestMailJob
  php artisan make:command SendScheduledDigests
  ```
* **Что изучено:**
  * Сборка письма через `Mailable` (`Envelope`, `Content`, `view: 'emails.daily-digest'`) с группировкой записей по темам (`Collection::groupBy(...)`).
  * Конвертация текущего времени сервера в локальный часовой пояс пользователя (`now()->setTimezone($timezone)`) и проверка 15-минутного окна отправки без дублей (`last_digest_sent_at`).
  * Регистрация `Schedule::command('digests:send')->everyFifteenMinutes()->withoutOverlapping();` в `routes/console.php`.

### Завершение Фазы 6 (Antigravity)
* Создан валидный RSS 2.0 шаблон `resources/views/feeds/rss.blade.php` и HTML-шаблон письма `resources/views/emails/daily-digest.blade.php`.
* Создан компонент `ProfileSettings` (`/settings`) для настройки часового пояса, частоты/времени дайджеста и перегенерации `feed_token` со сбросом кэша RSS.
* Написан `PhaseSixOutputChannelsTest` с `Mail::fake()`, `Queue::fake()`, `Carbon::setTestNow()` и проверкой XML через `simplexml_load_string()`.
