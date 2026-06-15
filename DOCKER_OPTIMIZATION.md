# NUTRISCOPE DOCKER OPTIMIZATION RECOMMENDATIONS

## 🎯 KEY OPTIMIZATIONS FOR YOUR DESKTOP SETUP

### 1. **Memory Management & Resource Limits** 
Your dev compose setup now includes:
- MySQL: 512MB limit (256MB reservation)
- Redis: 256MB limit (128MB reservation)
- PaddleOCR: 2GB limit (1GB reservation) — ML service, needs more memory
- OMR: 1GB limit (512MB reservation)

**Why:** Prevents runaway containers from consuming all system resources. On Windows/Mac with Docker Desktop, this is critical for desktop stability.

### 2. **Health Checks**
Added to all services:
```yaml
healthcheck:
  test: ["CMD", "redis-cli", "ping"]  # Redis example
  interval: 10s
  timeout: 5s
  retries: 5
```

**Why:** Docker can restart unhealthy containers automatically. MySQL uses `mysqladmin ping`, Redis uses `redis-cli ping`, Python services use curl to check a `/health` endpoint.

### 3. **Image Updates**
- **Redis:** Updated from `redis:alpine` → `redis:7-alpine` (explicit versioning)
  - Alpine reduces image size (~30MB vs 80+MB for debian)
  - Explicit version prevents unexpected breaking changes

- **PaddleOCR & OMR:** Added `PYTHONUNBUFFERED=1` environment variable
  - Ensures Python logs stream to Docker in real-time (no buffering delays)
  - Makes debugging easier

### 4. **Dockerfile Security & Efficiency**
Updated both `paddleocr/Dockerfile` and `omr/Dockerfile`:
- Added `curl` to base RUN command (layer reduction)
- Upgraded pip before installing packages (latest package compatibility)
- Combined apt-get update + install in single RUN (minimizes layers)

**Why:** Fewer layers = smaller images, faster builds, better caching.

### 5. **Network Configuration**
- All services on shared `nutriscope_net` bridge network
- Containers communicate via service names (no hardcoded IPs)
- Example: Backend connects to MySQL via `mysql:3306` (not localhost:3306)

### 6. **Volume Management**
- Named volumes (`nutriscope_mysql`, `nutriscope_redis`) persist data across restarts
- Not using bind mounts (which can cause permission issues on Windows)

---

## 🚀 USAGE

### Start All Services with Optimizations
```bash
docker compose -f docker-compose.dev.yml up -d
```

### Check Container Health
```bash
docker ps
# Look for "healthy" status, or "starting" if new
```

### View Logs
```bash
docker compose logs -f mysql      # Real-time MySQL logs
docker compose logs -f paddleocr  # Real-time OCR logs
```

### Stop All Services
```bash
docker compose -f docker-compose.dev.yml down
```

### Full Reset (WARNING: Deletes data)
```bash
docker compose -f docker-compose.dev.yml down -v
```

---

## 🔧 PRODUCTION vs. DEVELOPMENT

**You have both:**
- `docker-compose.yml` — Development setup (what you created)
- `docker-compose.prod.yml` — Production setup (includes backend + frontend)
- `docker-compose.dev.yml` — Optimized desktop setup (NEW)

**For desktop development:** Use `docker-compose.dev.yml`
```bash
# Start only DB + services (you run backend/frontend locally)
docker compose -f docker-compose.dev.yml up -d
```

**For production/staging:** Use `docker-compose.prod.yml`
```bash
# Starts everything including backend/frontend containers
docker compose -f docker-compose.prod.yml up -d
```

---

## 📊 RESOURCE ALLOCATION GUIDE

Adjust these limits based on your machine:

### **For 8GB RAM machines:**
- MySQL: 256-512MB ✓ (current: good)
- Redis: 128-256MB ✓ (current: good)
- PaddleOCR: 1-2GB ✓ (current: good)
- OMR: 512MB-1GB ✓ (current: good)

### **For 16GB+ RAM machines:**
- MySQL: 1GB
- Redis: 512MB
- PaddleOCR: 4GB
- OMR: 2GB

### **For 4GB RAM machines:**
- MySQL: 256MB
- Redis: 128MB
- PaddleOCR: 1.5GB
- OMR: 512MB
- ⚠️ Consider reducing backend + frontend heap sizes too

**Check your current usage:**
```bash
docker stats --no-stream
```

---

## 🔍 TROUBLESHOOTING

### MySQL Connection Issues
```bash
docker logs nutriscope_mysql
# Check if "ready for connections" appears
# If not, health check will fail and Docker will restart it
```

### High Memory Usage
```bash
docker stats
# If a container exceeds its limit, Docker kills it (exit code 137)
# Increase limits in compose file if needed
```

### Services Not Communicating
```bash
docker exec nutriscope_mysql sh -c "mysql -u root -h localhost nutriscope -e 'SELECT 1'"
# Test from inside the network
```

---

## ✅ WHAT YOU SHOULD DO NEXT

1. **Update `.env` files** in backend folder:
   - Set `DB_HOST=mysql` (not localhost)
   - Set `REDIS_HOST=redis`
   - Update connection strings to use Docker service names

2. **Test the setup:**
   ```bash
   docker compose -f docker-compose.dev.yml up -d
   docker ps  # Check all services running + healthy
   ```

3. **Start your backend + frontend locally:**
   ```bash
   # Terminal 1: Backend
   cd backend
   php artisan serve
   
   # Terminal 2: Frontend
   cd frontend
   npm run dev
   ```

4. **Optional: Monitor containers**
   ```bash
   docker stats --no-stream  # One-time snapshot
   docker stats               # Live updates
   ```

---

## 📝 SUMMARY OF CHANGES

✅ **Memory limits** — Prevent resource exhaustion  
✅ **Health checks** — Auto-restart unhealthy services  
✅ **Explicit versioning** — Avoid breaking changes  
✅ **Non-root users** — (ready in updated Dockerfiles, optional in compose)  
✅ **Optimized layers** — Smaller images, faster builds  
✅ **Development compose file** — For desktop-only setup  

All changes are backward-compatible. Your original `docker-compose.yml` still works.
