# 🚀 NUTRISCOPE PROJECT SETUP GUIDE (LOCAL + DOCKER)

## 📌 OVERVIEW

Nutriscope is a full-stack system composed of:

- 🐘 Laravel Backend (PHP) — shared API for all roles
- 🌐 Next.js Frontend (React) — web console for RND + Admin
- 📱 Expo Mobile App (React Native) — FSS (Food Service) role
- 🐳 Docker Services:
  - MySQL
  - Redis
  - PaddleOCR
  - OMR Service

> 📖 **New to the app?** See the [Demo & Workflow Guide](DEMO_GUIDE.md) for the
> RND / FSS / Admin roles, how their work hands off, and a ready-made demo script.

---

# ⚠️ IMPORTANT RULE

You will run:
- Backend → `php artisan serve` (local)
- Frontend → `npm run dev` (local)
- Database + services → Docker

DO NOT run backend/frontend inside Docker.

---

# 🧰 REQUIREMENTS

## Windows Users
Install:
- PHP 8.3+
- Composer
- Node.js (LTS)
- Docker Desktop (AMD64)
- Git

## Mac (Brew) Users
Install via Homebrew:

```bash
brew install git
brew install php@8.3
brew install composer
brew install node
```

Install Docker Desktop manually:
[https://www.docker.com/products/docker-desktop/](https://www.docker.com/products/docker-desktop/)

---

# 📥 1. CLONE THE PROJECT

```bash
git clone <repo-url>
cd Nutriscope
```

---

# 🐳 2. START DOCKER SERVICES

```bash
docker compose up -d --build
```

Check running containers:

```bash
docker ps
```

Expected services:

* mysql
* redis
* paddleocr
* omr

---

# ⚙️ 3. BACKEND SETUP (LARAVEL)

```bash
cd backend
```

---

## 📦 Install Node dependencies (for Vite)

```bash
npm install
```

---

## 📄 Create environment file

```bash
cp .env.example .env
```

Then update `.env`:

* Add USDA API key
* Set DB credentials matching Docker MySQL

---

## 🔑 Generate app key

```bash
php artisan key:generate
```

---

## 🗄️ Run migrations + seed database

```bash
php artisan migrate
php artisan db:seed
```

If data is incomplete due to API limits:

```bash
php artisan db:seed
```

(run again if needed)

---

## 🚀 Start backend server

```bash
php artisan serve
```

Backend runs at:

[http://127.0.0.1:8000](http://127.0.0.1:8000)

---

# 🌐 4. FRONTEND SETUP (NEXT.JS)

Open a new terminal:

```bash
cd frontend
```

---

## 📦 Install dependencies

```bash
npm install
```

---

## 📄 Create environment file

```bash
cp .env.example .env.local
```

`.env.local` sets `LARAVEL_API_URL` (where Next.js proxies API calls). Default
`http://localhost:8000/api` matches the local backend — no change needed unless your
backend runs elsewhere.

---

## 🚀 Run frontend

```bash
npm run dev
```

Frontend runs at:

[http://localhost:3000](http://localhost:3000)

---

# 📱 5. MOBILE SETUP (EXPO — FSS APP)

The Food Service (FSS) role runs on a **mobile app**, not the web. It's an Expo
(React Native) app that talks to the same Laravel backend over your LAN.

> Full details + Windows firewall steps: [`mobile/README.md`](mobile/README.md).

## Prerequisites

- Node.js LTS (18+)
- **Expo Go** app installed on your phone ([expo.dev/go](https://expo.dev/go))
- Phone and PC on the **same Wi-Fi network**

Open a new terminal:

```bash
cd mobile
```

## 📦 Install dependencies

```bash
npm install
```

## 📄 Create environment file

```bash
cp .env.example .env
```

Then set `EXPO_PUBLIC_API_URL` to **your PC's LAN IP** (NOT `localhost` — the app
runs on the phone, so localhost would point at the phone):

```
EXPO_PUBLIC_API_URL=http://192.168.1.X:8000
```

Find your LAN IP with `ipconfig` (Windows) / `ifconfig` (Mac/Linux) — the IPv4
address of your Wi-Fi adapter.

## 🔌 Start the backend so the phone can reach it

The default `php artisan serve` only binds to localhost. For the phone, bind to all
interfaces (run from `backend/`):

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Windows: if the phone still can't connect, allow port 8000 through the firewall:

```bash
netsh advfirewall firewall add rule name="Laravel Dev" dir=in action=allow protocol=TCP localport=8000
```

## 🚀 Start the Expo dev server

```bash
npx expo start
```

Scan the QR code in the terminal with **Expo Go** on your phone.

> Sign in with the **FSS** demo account (see Seeded Accounts below). FSS can sign in
> **only** on the mobile app; RND/Admin can sign in **only** on the web.

---

# 🧠 ENV FILE RULES

* Always copy `.env.example → .env`
* NEVER commit `.env`
* Store all API keys inside `.env`
* Example keys:

  * USDA API Key
  * DB credentials
  * OCR / AI service keys

---

# 🐳 DOCKER ROLE (IMPORTANT)

Docker is ONLY used for:

* MySQL database
* Redis cache
* OCR / OMR services

DO NOT install or run:

* Laravel inside Docker
* Next.js inside Docker

---

# ⚠️ COMMON ISSUES

## ❌ PHP version error

You need PHP 8.3+

Check:

```bash
php -v
```

Fix (Mac Brew):

```bash
brew link php@8.3 --force
```

---

## ❌ Database connection failed

Make sure:

* Docker is running
* MySQL container is up
* `.env` DB values match Docker config

---

## ❌ Port already in use

Backend:

```bash
lsof -i :8000
kill -9 <PID>
```

Frontend (Mac/Linux):

```bash
lsof -i :3000
kill -9 <PID>
```

Frontend (Windows):

```bash
netstat -ano | findstr :3000
taskkill /PID <PID> /F
```

---

## ❌ npm install fails

Clear cache and retry:

```bash
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

---

# 🧭 STARTUP ORDER (VERY IMPORTANT)

Always follow this order:

1. **Docker Services:**
```bash
docker compose up -d
```

2. **Backend (Terminal 1):**
```bash
cd backend
npm install
php artisan migrate
php artisan serve
```

3. **Frontend (Terminal 2):**
```bash
cd frontend
npm install
npm run dev
```

4. **Mobile — optional, only for the FSS app (Terminal 3):**
```bash
cd mobile
npm install
npx expo start
```
Note: for the phone to reach the backend, start it with
`php artisan serve --host=0.0.0.0 --port=8000` and set `EXPO_PUBLIC_API_URL`
to your PC's LAN IP (see the Mobile Setup section above).

---

# 🔥 OPTIONAL: RESET DATABASE

If something breaks:

```bash
php artisan migrate:fresh --seed
```

WARNING: This deletes all data.

---

# 🧑‍💻 TEAM RULES

* Do NOT modify `.env.example`
* Always create `.env` locally
* Always start Docker first
* Always use PHP 8.3+
* Always use Node.js LTS
* Always install dependencies (`npm install`) before running dev servers
* Use `npm` for package management (not pnpm or yarn)

---

# 🚀 FINAL NOTE

If everything is set up correctly, you should have:

* Backend → [http://127.0.0.1:8000](http://127.0.0.1:8000)
* Frontend → [http://localhost:3000](http://localhost:3000)
* Database → running in Docker

---

# 👤 SEEDED ACCOUNTS

See `backend/database/seeders/AdminUserSeeder.php` for full list.

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@nutriscope.local | nutriscope2024! |
| RND | rnd@nutriscope.local | nutriscope2024! |
| FSS | fss@nutriscope.local | nutriscope2024! |
