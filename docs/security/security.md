All API routes protected by auth:sanctum
Role guards on every route group: role:RND, role:FSS, role:Admin
All inputs validated via Laravel Form Requests
File uploads: PDF/JPG/PNG only, max 5MB
  - Screening forms: PDF/JPG/PNG, max 5MB per file
  - Lab results: PDF/JPG/PNG, max 5MB per file
  - Procurement documents: PDF/JPG/PNG, max 5MB per file
  - Receipt images: JPG/PNG only, max 5MB
  - All uploads stored in private disk, served via signed URLs
Anthropic API key: Laravel backend only, never in frontend
USDA API key: Laravel backend only
APP_DEBUG=false in production
Audit logging on all sensitive models via spatie/laravel-activitylog
Rate limiting: login (5/min), AI endpoints (10/min), OCR endpoints (10/min)
Daily AI token limit: 100,000 tokens enforced in AIService
Monthly spend cap: $10 set in Anthropic console
Extraction pipeline: extracted data stored with confidence scores, always requires RND review before finalizing
Report files: stored in private storage, accessible only to generating user and Admin role