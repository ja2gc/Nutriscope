**Stack:**

**Backend:** Laravel 13.8 (PHP 8.3)
- Framework: Laravel w/ Sanctum auth
- Database: SQLite (default setup, can be configured)
- Cache: Redis (Predis)
- PDF: dompdf
- HTTP: Guzzle
- Testing: PHPUnit
- Dev tools: Tinker, Pint (linting), Pail (logging)
- Activity logging: Spatie

**Frontend:** Next.js 16.2.6 (React 19)
- UI: Radix UI components
- Styling: Tailwind CSS v4
- Charting: Recharts
- Icons: Lucide React
- Testing: Vitest
- TypeScript 5

**Build & Dev:** Vite (bundler via Next.js), npm, concurrent processes for backend/queue/logs/frontend

Single monorepo, full-stack.
