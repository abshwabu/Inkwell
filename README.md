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

Run the full test suite with Pest/PHPUnit:
```bash
php artisan test
```

Build production assets with Vite:
```bash
npm run build
```

---

## License

The Inkwell platform is open-source software licensed under the [MIT license](LICENSE).
