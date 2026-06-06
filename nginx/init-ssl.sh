#!/bin/bash
# Run this ONCE on the server to get your first SSL certificate.
# Usage: bash nginx/init-ssl.sh your-domain.duckdns.org your@email.com

DOMAIN=$1
EMAIL=$2

if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
    echo "Usage: $0 <domain> <email>"
    exit 1
fi

# Replace YOUR_DOMAIN placeholder in nginx config
sed -i "s/YOUR_DOMAIN/$DOMAIN/g" nginx/default.conf

# Create dummy cert so nginx can start before the real cert exists
mkdir -p nginx/certbot/conf/live/$DOMAIN
openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout nginx/certbot/conf/live/$DOMAIN/privkey.pem \
    -out    nginx/certbot/conf/live/$DOMAIN/fullchain.pem \
    -subj "/CN=localhost" 2>/dev/null

echo "Starting nginx with dummy cert..."
docker compose -f docker-compose.prod.yml up -d nginx

echo "Requesting real certificate from Let's Encrypt..."
docker compose -f docker-compose.prod.yml run --rm certbot \
    certonly --webroot \
    --webroot-path=/var/www/certbot \
    --email $EMAIL \
    --agree-tos \
    --no-eff-email \
    -d $DOMAIN

echo "Reloading nginx with real certificate..."
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload

echo "Done! Your site should be live at https://$DOMAIN"
