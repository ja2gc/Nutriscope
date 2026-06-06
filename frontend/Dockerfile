FROM node:20-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

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
