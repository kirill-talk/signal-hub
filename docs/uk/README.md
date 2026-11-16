# Signal-to-Noise Hub — Посібник по проєкту

> [English version](../en/README.md) · [Архітектура та план розробки](ARCHITECTURE_AND_PLAN.md) · [Покроковий підручник та історія створення](TUTORIAL_AND_BUILD_HISTORY.md)

**Signal-to-Noise Hub** — це персональний серверний агрегатор і фільтр контенту на **Laravel 13** та **Livewire 4**, який збирає розрізнені стрічки (релізи на GitHub, RSS/Atom-блоги, відкриті сабреддіти, JSON-фіди), відсікає інформаційний шум за правилами користувача й доставляє очищений потік через вебридер, приватний RSS та email-дайджест.

---

## Сценарій роботи (User Flow)

1. **Підключення джерел (`/sources`, `/topics`):**
   Користувач створює теми (`Backend`, `Frontend`, `DevOps`, `Security`) і додає URL джерел. `SourceManager` автоматично визначає адаптер (`rss`, `github`, `json`) за посиланням, відстежує статус (`Active` / `Failing`), лічильник помилок і кнопку ручного запуску `Fetch Now`.
2. **Фільтрація за правилами (`/rules`):**
   Кожен новий запис перед збереженням проходить конвеєр правил `NoiseFilterService`:
   * **Include (білий список):** пропускати в основну стрічку лише матеріали з потрібними ключовими словами.
   * **Exclude (стоп-слова):** позначати шум прапорцем `is_noise = true` (записи не втрачаються — їх можна знайти у вкладці `All Stream` або через пошук).
   * **Highlight (пріоритет):** виділяти критично важливі оновлення прапорцем `is_highlighted = true`.
3. **Фоновий збір та дедуплікація:**
   * Планувальник щохвилини запускає `FetchSourceJob` для джерел, у яких минув `fetch_interval_minutes`.
   * Запити обмежені через `RateLimiter::attempt()` (не більше 10 запитів на хвилину до одного домену), використовують HTTP-кешування `ETag` / `Last-Modified` (`304 Not Modified`), очищення HTML від XSS через `DOMDocument` (`HtmlSanitizer`) та дедуплікацію за `external_id_hash` (SHA-256).
4. **Три канали споживання:**
   * **Вебстрічка (`/feed`):** Livewire-ридер із вкладками (`Signal`, `Unread`, `Bookmarks`, `All Stream`), фільтрами за темами та джерелами, повнотекстовим пошуком через `laravel/scout`, бічною шторкою читання та гарячими клавішами (`j`/`k`, `Enter`, `m`, `b`, `Esc`).
   * **Приватний RSS (`/feed/{token}.xml` і `/feed/{token}/{topicSlug}.xml`):** персональні посилання, які віддають останні 50 записів із `is_noise = false` (кешуються на 15 хвилин через `Cache::remember`).
   * **Email-дайджест (`/settings` + `digests:send`):** розсилка непрочитаних важливих матеріалів, згрупованих за темами, у вибрану годину з урахуванням часового поясу користувача (`$user->timezone`).

---

## Структура директорій проєкту

```text
signal-hub/
├── app/
│   ├── Console/Commands/       # Artisan-команди: sources:fetch, digests:send
│   ├── Contracts/              # Інтерфейс SourceAdapterInterface
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
│   ├── factories/              # Фабрики для всіх моделей
│   ├── migrations/             # Схема 5 таблиць і системних таблиць черг/кешу
│   └── seeders/                # DatabaseSeeder з демо-акаунтом demo@signalhub.test
├── docs/                       # Двомовна документація (en / uk)
├── resources/views/
│   ├── auth/                   # login.blade.php, register.blade.php
│   ├── components/layouts/     # app.blade.php, guest.blade.php
│   ├── emails/                 # daily-digest.blade.php
│   ├── feeds/                  # rss.blade.php
│   └── livewire/               # Шаблони Livewire-компонентів
├── routes/
│   ├── web.php                 # Вебмаршрути та приватні RSS-ендпоінти
│   └── console.php             # Розклад Schedule
└── tests/                      # Unit і Feature тести (39 тестів, 231 перевірка)
```

---

## Встановлення, запуск і воркери

```bash
cd /home/local/projects/signal-hub

# Встановлення залежностей і початкове налаштування
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate:fresh --seed

# Запуск локального вебсервера
php artisan serve
```

* **Демо-акаунт:** `demo@signalhub.test` / `password`

### Фонові процеси та консольні команди
```bash
# Ручне опитування всіх джерел (або конкретного за ID)
php artisan sources:fetch
php artisan sources:fetch 1

# Запуск воркера черг (обробка FetchSourceJob і SendDigestMailJob)
php artisan queue:work --tries=3

# Запуск локального планувальника (опитування стрічок щохвилини, дайджести кожні 15 хвилин)
php artisan schedule:work

# Перегляд зареєстрованих завдань планувальника
php artisan schedule:list

# Ручна перевірка та відправлення відповідних за часом дайджестів
php artisan digests:send

# Запуск усіх автотестів
php artisan test
```

---

## Гарячі клавіші у Feed Hub

| Клавіша | Дія |
| :--- | :--- |
| `j` / `k` | Перемістити фокус на наступний / попередній запис |
| `Enter` | Відкрити вибраний запис у бічній шторці читання |
| `m` | Перемкнути статус «Прочитано / Непрочитано» |
| `b` | Додати або прибрати запис із закладок |
| `Esc` | Закрити бічну шторку читання |
