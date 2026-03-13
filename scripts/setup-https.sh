#!/bin/bash
set -e

DOMAIN="46-225-59-170.sslip.io"
EMAIL="info@pivnikonto.cz"
CERTBOT_DIR="/var/www/pivnikonto/certbot/www"

echo "=== HTTPS Setup for $DOMAIN ==="

# 1. Install certbot
if ! command -v certbot &> /dev/null; then
    echo "Installing certbot..."
    apt-get update && apt-get install -y certbot
fi

# 2. Create certbot webroot directory
mkdir -p "$CERTBOT_DIR"

# 3. Temporarily use HTTP-only nginx config for cert issuance
echo "Stopping containers..."
cd /var/www/pivnikonto
docker compose -f docker-compose.prod.yml down

# Create a temporary nginx config that only serves HTTP (for ACME challenge)
cat > /tmp/nginx-certbot.conf << 'NGINX'
server {
    listen 80;
    server_name _;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 200 'OK';
        add_header Content-Type text/plain;
    }
}
NGINX

# Start nginx with temporary config
docker run -d --name certbot-nginx \
    -p 80:80 \
    -v /tmp/nginx-certbot.conf:/etc/nginx/conf.d/default.conf:ro \
    -v "$CERTBOT_DIR":/var/www/certbot:ro \
    nginx:alpine

# 4. Get certificate
echo "Requesting certificate for $DOMAIN..."
certbot certonly --webroot \
    -w "$CERTBOT_DIR" \
    -d "$DOMAIN" \
    --email "$EMAIL" \
    --agree-tos \
    --non-interactive

# 5. Cleanup temporary nginx
docker stop certbot-nginx && docker rm certbot-nginx

# 6. Start the app with HTTPS
echo "Starting app with HTTPS..."
docker compose -f docker-compose.prod.yml up -d --build

# 7. Setup auto-renewal cron
if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
    echo "Adding certbot renewal cron..."
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --deploy-hook 'cd /var/www/pivnikonto && docker compose -f docker-compose.prod.yml exec -T nginx nginx -s reload'") | crontab -
fi

echo ""
echo "=== HTTPS Setup Complete ==="
echo "Your app is now available at: https://$DOMAIN"
echo "Certificate auto-renewal is configured (daily check at 3 AM)."
echo ""
echo "NEXT STEPS:"
echo "1. Generate VAPID keys:  docker compose -f docker-compose.prod.yml exec php php bin/console app:generate-vapid-keys"
echo "   (or manually: openssl ecparam -genkey -name prime256v1 -noout | openssl ec -outform DER 2>/dev/null | tail -c +8 | head -c 32 | base64 -w0 | tr '+/' '-_')"
echo "2. Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in .env.prod"
echo "3. Update CORS_ALLOW_ORIGIN in .env.prod to include https://$DOMAIN"
