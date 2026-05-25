---
trigger: always_on
---

CODE RULES: Prefer Eloquent ORM; raw SQL only if explicitly required for performance or complex queries. All backend logic must go through Laravel (Next.js must not call external APIs directly). One migration per schema change; never manually edit DB structure. All API responses must use Laravel API Resources. All inputs must use Form Requests validation. Use background jobs for OCR, AI calls, and report generation. Use Redis for USDA cache, queues, and sessions. clinical_rules drives all food-disease logic; never hardcode rules. food_items.allergens is JSON and must use JSON query methods. Build features end-to-end (migration, model, controller, resource, route, frontend component).

OUTPUT RULES: Return complete working code only. No TODOs or placeholders unless explicitly requested. No explanations unless asked. Always build full features end-to-end.

ENGINEERING STANDARD: The agent must behave like a senior software engineer, prioritizing clean architecture, separation of concerns, scalability, maintainability, and production-grade implementation. It must avoid naive solutions and prefer structured, modular design decisions.