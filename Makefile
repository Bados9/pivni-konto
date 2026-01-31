.PHONY: up down build logs shell-php shell-node migrate fixtures jwt-keys

# Start all containers
up:
	docker compose up -d

# Stop all containers
down:
	docker compose down

# Build containers
build:
	docker compose build

# Show logs
logs:
	docker compose logs -f

# PHP shell
shell-php:
	docker compose exec php sh

# Node shell
shell-node:
	docker compose exec node sh

# Install backend dependencies
composer-install:
	docker compose exec php composer install

# Run database migrations
migrate:
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Load fixtures
fixtures:
	docker compose exec php bin/console doctrine:fixtures:load --no-interaction

# Generate JWT keys
jwt-keys:
	docker compose exec php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Create database
db-create:
	docker compose exec php bin/console doctrine:database:create --if-not-exists

# Full setup (first run)
setup: up composer-install jwt-keys db-create migrate fixtures
	@echo "Setup complete! Access the app at http://localhost"

# Reset database
db-reset:
	docker compose exec php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php bin/console doctrine:database:create
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec php bin/console doctrine:fixtures:load --no-interaction
