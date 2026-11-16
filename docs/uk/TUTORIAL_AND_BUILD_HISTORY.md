# Покроковий підручник та історія створення Signal-to-Noise Hub (Laravel 13)

> [English version](../en/TUTORIAL_AND_BUILD_HISTORY.md) · [Посібник по проєкту](README.md) · [Архітектура та план розробки](ARCHITECTURE_AND_PLAN.md)

Цей документ зберігає всю покрокову історію розробки проєкту — усі команди термінала, ключові концепції Laravel і PHP, а також розподіл виконаних завдань за всіма 6 фазами.

---

## Фаза 1. Фундамент, схема БД і CRUD джерел

### Крок 1.1: Розгортання Laravel 13 і Livewire 4
* **Команди:**
  ```bash
  cd /home/local/projects/signal-hub
  composer create-project laravel/laravel .
  composer require livewire/livewire
  ```
* **Що вивчено:**
  * `composer` — менеджер залежностей PHP (аналог `npm` у JS або `pip` у Python), який керує бібліотеками в `composer.json` і папці `vendor/`.
  * Крапка `.` у `composer create-project laravel/laravel .` розпаковує каркас просто в поточну папку, створює `.env`, ключ `APP_KEY`, файл `database/database.sqlite` та запускає базові системні міграції (`users`, `cache`, `jobs`).

### Крок 1.2: Налаштування SQLite (WAL) і генерація моделей з міграціями
* **Налаштування `config/database.php`:** у конфігурації підключення `'sqlite'` виставлено `'busy_timeout' => 5000` (очікування до 5 секунд замість помилки `database is locked`) та `'journal_mode' => 'wal'` (неблокуюче читання під час паралельного запису воркерами).
* **Команди:**
  ```bash
  php artisan make:model Topic -m
  php artisan make:model Source -m
  php artisan make:model Entry -m
  php artisan make:model FilterRule -m
  ```
* **Що вивчено:**
  * Назва моделі пишеться в однині в `PascalCase`, а таблиця в БД — у множині в `snake_case` (`topics`, `sources`, `entries`, `filter_rules`).
  * Прапорець `-m` (`--migration`) автоматично створює відповідний файл міграції в `database/migrations/`.

### Крок 1.3: Міграції та створення таблиць
* **Застосовані конструкції Blueprint:**
  * `$table->foreignId('user_id')->constrained()->cascadeOnDelete()` — зовнішній ключ, який каскадно видаляє дані при видаленні користувача.
  * `->nullable()->constrained()->nullOnDelete()` — м'яка прив'язка: за видалення `Topic` джерела і статті зберігаються, скидаючи `topic_id` у `NULL`.
  * Складені унікальні індекси: `$table->unique(['user_id', 'slug'])` у `topics` та `$table->unique(['user_id', 'external_id_hash'])` в `entries`.
* **Команда:**
  ```bash
  php artisan migrate:fresh
  ```
  Перестворює схему з нуля, коли до наявних базових міграцій (наприклад, `users`) додаються нові колонки (`timezone`, `feed_token`, `digest_frequency`, `digest_time`, `last_digest_sent_at`).

### Крок 1.4: Налаштування моделей Eloquent
* **Що налаштовано в `app/Models/`:**
  * `$fillable` — захист від масового присвоєння (Mass Assignment), що дозволяє записувати лише перелічені поля.
  * `casts()` — перетворення типів SQLite на нативні PHP-типи (`boolean`, `integer`, `array` для JSON, `datetime` для об'єктів `Carbon`).
  * `booted()` у `User.php` — автоматична генерація UUID для `feed_token` через подію `static::creating(...)`.
  * Зв'язки `hasMany` і `belongsTo` між `User`, `Topic`, `Source`, `Entry` і `FilterRule`.

### Крок 1.5: Серверна валідація (Form Requests)
* **Команди:**
  ```bash
  php artisan make:request StoreTopicRequest
  php artisan make:request SaveSourceRequest
  ```
* **Що вивчено:**
  * Правило `'url:http,https'` захищає від невалідних і локальних схем (`file://`, `javascript:`).
  * Правило `Rule::exists('topics', 'id')->where('user_id', Auth::id())` гарантує, що користувач не зможе прив'язати чужу тему.
  * **Стандарт PSR-4:** назва PHP-файлу повинна буквально збігатися з назвою `class` усередині нього (`StoreTopicRequest.php` ↔ `class StoreTopicRequest`).

### Крок 1.6 і завершення Фази 1 (Antigravity)
* **Команди:**
  ```bash
  php artisan make:livewire TopicManager --class
  php artisan make:livewire SourceManager --class
  ```
* **Що вивчено і зроблено:**
  * Livewire-хук `updatedUrl()` автоматично визначає тип адаптера (`github`, `json`, `rss`) при введенні URL.
  * Жадібне завантаження `with('topic')` і `withCount(['sources', 'entries'])` запобігає проблемі **N+1 SQL-запитів**.
  * Створено `AuthController`, шаблони `guest.blade.php` / `app.blade.php`, UI-інтерфейси з Alpine-модалками підтвердження видалення, фабрики (`TopicFactory`, `SourceFactory`, `EntryFactory`, `FilterRuleFactory`), `DatabaseSeeder` (демо-користувач `demo@signalhub.test` / `password`) і `PhaseOneCrudTest`.

---

## Фаза 2. Архітектура адаптерів і ручний збір даних

### Крок 2.1: DTO `EntryData` та інтерфейс `SourceAdapterInterface`
* **Команди:**
  ```bash
  mkdir -p app/DataTransferObjects app/Contracts
  touch app/DataTransferObjects/EntryData.php app/Contracts/SourceAdapterInterface.php
  ```
* **Що вивчено:**
  * **`readonly class EntryData`** із Constructor Property Promotion — незмінний контейнер даних, що уніфікує записи з RSS, Atom і JSON.
  * **`interface SourceAdapterInterface`** — контракт із методом `fetch(Source $source): Collection`, який гарантує єдиний інтерфейс для всіх адаптерів.

### Крок 2.2: Адаптери `RssAtomAdapter`, `JsonFeedAdapter` і `AdapterFactory`
* **Що вивчено і реалізовано:**
  * `Http::timeout(15)->withHeaders(...)->get($source->url)->throw()` — вбудований HTTP-клієнт Laravel.
  * `SimpleXMLElement($body, LIBXML_NOCDATA)` — вбудований парсер XML із розкриттям `<![CDATA[...]]>`.
  * Витягування посилань Atom з атрибутів `<link rel="alternate" href="..." />`.
  * `AdapterFactory` з `match` і `app(...)` — отримання потрібного адаптера через **Service Container** Laravel.

### Крок 2.3 і завершення Фази 2 (Antigravity)
* **Команда:**
  ```bash
  php artisan make:command FetchSourcesCommand
  ```
* **Що вивчено і зроблено:**
  * **`EntryIngestionService`** — впровадження залежностей через конструктор (Dependency Injection), обчислення SHA-256 відбитка `external_id_hash` та пропуск дублікатів.
  * Консольна команда `php artisan sources:fetch {source_id?}` з обробкою помилок `Throwable` і записом збоїв у `status = 'failing'`, `error_count` та `last_error`.
  * **`HtmlSanitizer`** на базі `DOMDocument` — очищення HTML від `<script>`, `<iframe>`, `<style>`, `<img>`-трекерів, `onclick`/`onerror` та `javascript:`-посилань.
  * Підтримка XML-неймспейсів (`content:encoded`, `dc:creator`, `dc:date`, `atom:link`) і набори тестів (`HtmlSanitizerTest`, `SourceAdaptersTest`, `FetchSourcesCommandTest`) на локальних фікстурах `tests/Fixtures/`.

---

## Фаза 3. Черги, планувальник і Rate Limiting

### Крок 3.1: `FetchSourceJob`, RateLimiter і HTTP-кешування (`ETag` / `304`)
* **Команда:**
  ```bash
  php artisan make:job FetchSourceJob
  ```
* **Що вивчено і зроблено:**
  * Передача умовних заголовків `If-None-Match` (`etag`) і `If-Modified-Since` (`last_modified`) через `array_filter()`. За статусу `304 Not Modified` адаптер завершує роботу без парсингу.
  * `FetchSourceJob implements ShouldQueue` із `$tries = 3`, `$timeout = 15`, `$backoff = [30, 120, 300]`.
  * `RateLimiter::attempt("fetch-domain:{$host}", 10, ..., 60)` — обмеження до 10 запитів на хвилину до одного домену з м'яким відкладенням завдання через `$this->release(15)`.

### Крок 3.2 і завершення Фази 3 (Antigravity)
* **Налаштування `routes/console.php`:**
  ```php
  Schedule::call(function (): void { ... })->name('dispatch-due-sources')->everyMinute()->withoutOverlapping();
  ```
* **Команда перевірки розкладу:**
  ```bash
  php artisan schedule:list
  ```
* **Зроблено Antigravity:** кнопка `Fetch Now` у `SourceManager`, відображення `entries_count`, бейджа `ETag cached`, тексту `last_error` і `PhaseThreeQueueAndRateLimitTest` (`Queue::fake()`, `Http::fake()`, `schedule:run`, тест перевищення `RateLimiter`).

---

## Фаза 4. Рушій фільтрації шуму (`NoiseFilterService`)

### Крок 4.1: `NoiseFilterService` та інтеграція в `EntryIngestionService`
* **Що вивчено:**
  * Регістронезалежний пошук по UTF-8 через `mb_stripos($haystack, $needle) !== false`.
  * Суворий порядок перевірки правил за областю дії (`source_id`, `topic_id`, глобальні): `include` → `exclude` → `highlight`.
  * Інтеграція `$this->noiseFilter->evaluate(...)` безпосередньо перед `Entry::create(...)`.

### Крок 4.2 і завершення Фази 4 (Antigravity)
* **Команди:**
  ```bash
  php artisan make:request SaveFilterRuleRequest
  php artisan make:livewire FilterRuleManager --class
  ```
* **Що вивчено і зроблено:**
  * Валідація елементів масиву в Laravel через `'keywords.*' => ['required', 'string', 'max:80']`.
  * Метод `previewRule(NoiseFilterService $noiseFilter)` — перевірка чернетки правила в пам'яті без збереження в БД.
  * UI `filter-rule-manager.blade.php` із введенням тегів на Alpine.js (`$wire.entangle('keywords')`), перемикачем `Active / Paused`, маршрутом `/rules` і тестами `NoiseFilterServiceTest` + `FilterRuleManagerTest`.

---

## Фаза 5. Робоча стрічка хабу та повнотекстовий пошук

### Крок 5.1: Встановлення `laravel/scout` і налаштування моделі `Entry`
* **Команди:**
  ```bash
  composer require laravel/scout
  php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
  ```
* **Що вивчено:**
  * Підключення драйвера `SCOUT_DRIVER=database` у `.env` та `config/scout.php`.
  * Трейт `Laravel\Scout\Searchable` і метод `toSearchableArray()` в `Entry.php`.

### Крок 5.2 і завершення Фази 5 (Antigravity)
* **Команда:**
  ```bash
  php artisan make:livewire FeedHub --class
  ```
* **Що вивчено і зроблено:**
  * Атрибути `#[Url]` у Livewire 4 для збереження стану вкладок, фільтрів і пошуку в URL.
  * Розділення `applyScopeFilters()` (без `ORDER BY` для безпечного `UPDATE` в SQLite у `markAllAsRead()`) та `applyFilters()` (з `with()` і сортуванням).
  * Запит `Entry::search($term)->query(...)` для пошуку зі збереженням усіх фільтрів та ізоляції за `user_id`.
  * UI `feed-hub.blade.php` із вкладками, бічною шторкою читання, гарячими клавішами Alpine.js (`j`/`k`/`Enter`/`m`/`b`/`Esc`), маршрутом `/feed` і `PhaseFiveFeedHubTest`.

---

## Фаза 6. Вихідні канали: Приватний RSS і Email-дайджести

### Крок 6.1: Контролер `PrivateFeedController`
* **Команда:**
  ```bash
  php artisan make:controller PrivateFeedController
  ```
* **Що вивчено:**
  * Авторизація за секретним токеном в URL (`/feed/{token}.xml` і `/feed/{token}/{topicSlug}.xml`) поза `auth` middleware.
  * Кешування XML-стрічки на 15 хвилин через `Cache::remember(...)` і віддача із заголовком `Content-Type: application/rss+xml; charset=UTF-8`.

### Крок 6.2а та 6.2б: `DailyDigestMail`, `SendDigestMailJob` і `SendScheduledDigests`
* **Команди:**
  ```bash
  php artisan make:mail DailyDigestMail
  php artisan make:job SendDigestMailJob
  php artisan make:command SendScheduledDigests
  ```
* **Що вивчено:**
  * Збирання листа через `Mailable` (`Envelope`, `Content`, `view: 'emails.daily-digest'`) із групуванням записів за темами (`Collection::groupBy(...)`).
  * Конвертація поточного часу сервера в локальний часовий пояс користувача (`now()->setTimezone($timezone)`) і перевірка 15-хвилинного вікна відправлення без дублів (`last_digest_sent_at`).
  * Реєстрація `Schedule::command('digests:send')->everyFifteenMinutes()->withoutOverlapping();` у `routes/console.php`.

### Завершення Фази 6 (Antigravity)
* Створено валідний RSS 2.0 шаблон `resources/views/feeds/rss.blade.php` і HTML-шаблон листа `resources/views/emails/daily-digest.blade.php`.
* Створено компонент `ProfileSettings` (`/settings`) для налаштування часового поясу, частоти/часу дайджесту та перегенерації `feed_token` зі скиданням кешу RSS.
* Написано `PhaseSixOutputChannelsTest` із `Mail::fake()`, `Queue::fake()`, `Carbon::setTestNow()` і перевіркою XML через `simplexml_load_string()`.
