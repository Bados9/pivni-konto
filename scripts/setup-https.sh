#!/usr/bin/env bash
# Jednorázové získání certifikátu pro pivnikonto.duckdns.org přes webroot běžícího nginxu
# (bez výpadku) + hook, který po automatické obnově reloadne nginx v kontejneru.
# Spustit jako root na VPS PŘED nasazením verze s prod-ssl.conf. Opakované spuštění neškodí.
set -euo pipefail

DOMAIN="${DOMAIN:-pivnikonto.duckdns.org}"
EMAIL="${EMAIL:-}"
WEBROOT="${WEBROOT:-/var/www/pivnikonto/certbot/www}"
NGINX_CONTAINER="${NGINX_CONTAINER:-pivnikonto-nginx-1}"

command -v certbot >/dev/null || { echo "certbot chybí: apt-get install -y certbot"; exit 1; }
mkdir -p "$WEBROOT"

SERVER_IP="$(curl -4fs https://ifconfig.me)"
DNS_IP="$(getent ahostsv4 "$DOMAIN" | awk 'NR==1{print $1}' || true)"
[ "$DNS_IP" = "$SERVER_IP" ] || { echo "DNS: $DOMAIN -> '$DNS_IP', server má $SERVER_IP. Oprav DuckDNS a spusť znovu."; exit 1; }

if [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
  [ -n "$EMAIL" ] || read -rp "E-mail pro Let's Encrypt: " EMAIL
  certbot certonly --webroot -w "$WEBROOT" -d "$DOMAIN" --agree-tos -m "$EMAIL" --no-eff-email --non-interactive
fi

mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx-container.sh <<HOOK
#!/bin/sh
docker exec $NGINX_CONTAINER nginx -s reload
HOOK
chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx-container.sh

echo "OK: certifikát v /etc/letsencrypt/live/$DOMAIN, obnovu hlídá systemd timer certbotu."
echo "Teď je možné nasadit verzi s prod-ssl.conf."
