FROM node:20-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN attempt=1; \
    while [ "$attempt" -le 2 ]; do \
        rm -rf node_modules; \
        if npm ci --no-audit --no-fund && test -x node_modules/.bin/next; then \
            exit 0; \
        fi; \
        echo "npm ci attempt $attempt failed or was incomplete" >&2; \
        attempt=$((attempt + 1)); \
    done; \
    exit 1

COPY . .

# LARAVEL_API_URL is used server-side in Next.js API routes — bake it in at build time
ARG LARAVEL_API_URL=http://backend/api
ENV LARAVEL_API_URL=$LARAVEL_API_URL

RUN npm run build

# --- Lean runner using Next.js standalone output ---
FROM node:20-alpine AS runner

WORKDIR /app

ENV NODE_ENV=production
ENV PORT=3000

COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
COPY --from=builder /app/public ./public

EXPOSE 3000

CMD ["node", "server.js"]
