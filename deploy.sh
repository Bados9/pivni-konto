#!/bin/bash
set -e

echo "🚀 Deploying Pivní Konto..."

# Discard any local changes (e.g. from npm install in node container)
echo "🧹 Cleaning local changes..."
git checkout -- .

# Pull latest code
echo "📥 Pulling latest code..."
git pull origin master

# Build and start containers
echo "🐳 Building containers..."
docker compose -f docker-compose.prod.yml build

echo "🔄 Starting containers..."
docker compose -f docker-compose.prod.yml up -d

# Wait for database
echo "⏳ Waiting for database..."
sleep 5

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
docker compose -f docker-compose.prod.yml exec -T php composer install --no-dev --optimize-autoloader --no-scripts

# Generate JWT keys if not exist
echo "🔑 Checking JWT keys..."
docker compose -f docker-compose.prod.yml exec -T php php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Clear cache (must run before migrations/commands to register new code)
echo "🧹 Clearing cache..."
docker compose -f docker-compose.prod.yml exec -T php php bin/console cache:clear

# Run migrations
echo "📊 Running migrations..."
docker compose -f docker-compose.prod.yml exec -T php php bin/console doctrine:migrations:migrate --no-interaction

# Load beer data (safe to re-run, skips existing)
echo "🍺 Loading beer data..."
docker compose -f docker-compose.prod.yml exec -T php php bin/console app:load-beers

echo "✅ Deployment complete!"
echo "🌐 App running at http://$(curl -s ifconfig.me)"
