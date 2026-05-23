KEY INTEGRATIONS
PaddleOCR

Called from Laravel via HTTP POST to http://paddleocr:5000/ocr
Used in: Assessment biochemical tab, Add Patient document upload
Always processed as background Job (Laravel Queue + Redis)
Returns extracted text → Laravel parses and maps to fields
Manual override always available

Anthropic API

Called from Laravel AIService class only — never from frontend
Uses two models:

claude-haiku-4-5 for simple tasks (PES drafting, explanations, fallback meal suggestions)
claude-sonnet-4-6 for complex reasoning (M&E decisions, risk analysis, trend forecasting)


Rate limited: throttle:10,1 on all AI endpoints
Always dispatched as background Job for non-blocking UX
Daily token limit enforced in AIService before every call
All usage logged to ai_usage_logs

USDA FoodData Central

Called from Laravel FoodService class
Used in: Foods Library, Recipe Builder nutrient data
All responses cached in Redis for 7 days
Nutrient data stored locally in food_items table after first fetch
Meal plan auto-generation uses local food_items table only — does not call USDA during generation
API key in .env as USDA_API_KEY
Pre-seed 15-20 common Filipino hospital foods on first setup using USDA data