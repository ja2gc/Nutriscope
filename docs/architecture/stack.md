Frontend: Next.js (App Router, TypeScript, Tailwind CSS)
Backend: Laravel (PHP 8.2+, REST API, Sanctum auth)
Database: MySQL
Cache / Queue: Redis
OCR: PaddleOCR 3.0 (Python FastAPI microservice at http://paddleocr:5000)
AI: Claude Haiku 4.5 (simple tasks) + Claude Sonnet 4.6 (complex reasoning) via Anthropic API — called from Laravel only, never frontend
Nutrition Data: USDA FoodData Central API (cached in Redis)
Containerized: Docker Compose
