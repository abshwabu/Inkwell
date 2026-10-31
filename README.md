# Inkwell

Inkwell is a public blogging platform where thoughtful writing meets clean typography. Anyone can read stories freely without creating an account, and anyone can register to publish their thoughts to the world.

## Tech Stack

- **Backend:** [Laravel 12](https://laravel.com) (PHP 8.2+)
- **Frontend:** [Inertia.js v2](https://inertiajs.com) + [Vue 3](https://vuejs.org) (Composition API, `<script setup>`)
- **Styling:** [Tailwind CSS](https://tailwindcss.com) with `@tailwindcss/typography` & `@tailwindcss/forms`
- **Database:** SQLite 3 (WAL journal mode, normal sync, foreign keys enforced, 5000ms busy timeout)
- **Queue, Cache & Sessions:** `database` driver (persisted in SQLite)
- **Theme:** Class-based dark mode persisted in `localStorage` (system preference detection fallback)

---

## Getting Started

### Prerequisites

- **PHP 8.2+** (with `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl` extensions)
- **Composer 2.x**
- **Node.js 18+** & **npm**
- **SQLite 3**

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/abshwabu/Inkwell.git
   cd Inkwell
   ```

2. **Install PHP and Node dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Set up environment configuration:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Prepare the SQLite database:**
   The database configuration automatically defaults to `database/database.sqlite`:
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. **Start local development servers:**
   In one terminal, start the Vite development server:
   ```bash
   npm run dev
   ```

   In another terminal, start the Laravel backend:
   ```bash
   php artisan serve
   ```

   Visit [http://localhost:8000](http://localhost:8000) in your browser.

---

## Verifying SQLite PRAGMAs

Inkwell configures SQLite performance PRAGMAs on every connection in `AppServiceProvider`:
- `PRAGMA journal_mode = WAL;` (Write-Ahead Logging for high-concurrency reading)
- `PRAGMA synchronous = NORMAL;` (Safe and fast fsync policy)
- `PRAGMA foreign_keys = ON;` (Strict referential integrity)
- `PRAGMA busy_timeout = 5000;` (5s wait on concurrent locks)

You can verify these at any time via Artisan Tinker:
```bash
php artisan tinker --execute="dump(DB::select('PRAGMA journal_mode;'), DB::select('PRAGMA synchronous;'), DB::select('PRAGMA foreign_keys;'), DB::select('PRAGMA busy_timeout;'));"
```

Expected output:
```text
array:1 [
  0 => {#... +"journal_mode": "wal"}
]
array:1 [
  0 => {#... +"synchronous": 1}
]
array:1 [
  0 => {#... +"foreign_keys": 1}
]
array:1 [
  0 => {#... +"timeout": 5000}
]
```

---

## Running Automated Tests

Run the full feature test suite with Pest / PHPUnit:
```bash
php artisan test
# or
./vendor/bin/pest
```

Run end-to-end browser tests with Playwright (write → publish → read as guest):
```bash
npm run test:e2e
```

Build production assets with Vite:
```bash
npm run build
```

---

## SQLite Operations, Backups & Maintenance

### 1. Hot Backups via `VACUUM INTO`
Inkwell performs transactionally consistent, zero-downtime hot backups using SQLite's native `VACUUM INTO` engine. The command automatically prunes older snapshots, retaining the 14 most recent copies:

```bash
# Create a backup snapshot
php artisan sqlite:backup

# Specify custom retention
php artisan sqlite:backup --keep=14
```

Snapshots are stored in `storage/app/backups/inkwell_backup_YYYY_MM_DD_His.sqlite`.

### 2. Verified Backup Restore
You can restore the database from any snapshot with automatic integrity verification:

```bash
php artisan sqlite:backup --restore=storage/app/backups/inkwell_backup_2026_10_30_202419.sqlite
```
*Note: Restoring disconnects active database connections, copies the snapshot to `database/database.sqlite`, reconnects, and runs `PRAGMA integrity_check` to guarantee data integrity.*

### 3. Write-Ahead Log (WAL) Checkpoints
To flush and truncate the write-ahead log file (`database.sqlite-wal`), execute:

```bash
php artisan sqlite:checkpoint --mode=truncate
```

### 4. Health Check Endpoint
A dedicated system diagnostics endpoint `/health` validates:
- SQLite connectivity & `PRAGMA quick_check`
- `PRAGMA journal_mode = wal`
- `PRAGMA foreign_keys = ON`
- Storage writeability and cache operability

---

## SQLite Single-Writer Limits & PostgreSQL Migration

Inkwell runs on SQLite 3 with Write-Ahead Logging (`PRAGMA journal_mode=WAL`). Under WAL mode:
- **Readers never block writers**: Infinite concurrent guests can browse and read stories without locking the database.
- **Writers never block readers**: Background queue workers and author saves do not slow down read traffic.
- **Writers serialize**: SQLite permits only one write transaction at any exact millisecond. If multiple writes occur concurrently, SQLite waits up to **5,000ms** (`PRAGMA busy_timeout=5000`) before throwing a busy error.

### When SQLite is the Best Choice:
- **Single-Server Node**: A modern NVMe VPS (e.g. 4-8 cores, 16GB RAM) running Inkwell on SQLite can comfortably handle:
  - 10,000,000+ monthly page views.
  - 50,000+ registered users.
  - Hundreds of comments, likes, and story saves per hour.
- Zero database networking latency, zero external database hosting fees, and atomic backups.

### When to Migrate to PostgreSQL:
Migrate to PostgreSQL when the following architectural requirements arise:
1. **Multi-Server Horizontal Autoscaling**: Multiple web application servers sitting behind a load balancer that must communicate with a shared database cluster over TCP.
2. **High-Frequency Concurrent Write Throughput**: Sustained write spikes exceeding 200-500 write transactions per second (e.g. flash-sale traffic, heavy real-time analytics logging).
3. **Complex Distributed Architecture**: Dedicated read replicas across multiple geographic regions or specialized database partitioning requirements.

*To migrate to PostgreSQL: update `DB_CONNECTION=pgsql` in `.env`, install the `pdo_pgsql` PHP extension, and execute `php artisan migrate`.*

---

## SEO, Feeds & Discovery

- **Dynamic XML Sitemap:** Automatically generated and served at `/sitemap.xml` (includes all published posts, categories, tags, and authors). Regenerated nightly via `php artisan sitemap:generate`.
- **Search Engine Directives:** Managed via `/robots.txt` pointing directly to the sitemap.
- **RSS 2.0 Feeds:**
  - Main site feed: `/feed`
  - Per category feed: `/feed/category/{slug}`
  - Per author feed: `/feed/author/{username}` (or `/@{username}/feed`)
- **Rich Meta & Schema:** Every story serves Open Graph cards, Twitter summary cards, and Schema.org `Article` JSON-LD structured data.

---

## Production Deployment

1. **Environment Configuration:**
   Copy `.env.production.example` to `.env` and configure `APP_KEY`, `APP_URL`, and SMTP credentials.

2. **Nginx & SSL:**
   Use the production-ready configuration provided in `deployment/nginx.conf`. It includes rate-limiting, static asset caching headers, Gzip compression, and strict deny rules for SQLite files.

3. **Background Queue Worker (Supervisor):**
   Copy `deployment/supervisor/inkwell-worker.conf` to `/etc/supervisor/conf.d/inkwell-worker.conf`:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start inkwell-worker:*
   ```

4. **Task Scheduler (Cron):**
   Add the schedule runner from `deployment/crontab` to your server crontab:
   ```bash
   * * * * * cd /var/www/inkwell && php artisan schedule:run >> /dev/null 2>&1
   ```

5. **Automated Deployment Script:**
   Execute zero-downtime deployments using `deployment/deploy.sh`:
   ```bash
   ./deployment/deploy.sh
   ```

---

## License

The Inkwell platform is open-source software licensed under the [MIT license](LICENSE).
