Frontend: Next.js (App Router, TypeScript, Tailwind CSS)
Backend: Laravel 13 (v13.11.2, PHP 8.3+, REST API, Sanctum auth)
Database: MySQL
Cache / Queue: Redis
OCR: PaddleOCR 3.0 (Python FastAPI microservice at http://paddleocr:5000)
AI: Claude Haiku 4.5 (simple tasks) + Claude Sonnet 4.6 (complex reasoning) via Anthropic API — called from Laravel only, never frontend
Nutrition Data: USDA FoodData Central API (cached in Redis)
PDF Reports: DomPDF (barryvdh/laravel-dompdf) — Blade templates → PDF, background job generation
Containerized: Docker Compose (MySQL, Redis, PaddleOCR)
