# Pivní Konto - Development Guidelines

## Project Structure

- `backend/` - Symfony 7.2 API (PHP 8.3+)
- `frontend/` - Vue 3 + Vite SPA
- `.github/workflows/` - CI/CD pipelines

## Git Workflow

**IMPORTANT:** All code changes must go through Pull Requests.

1. Create a feature branch from `master`
2. Make your changes
3. Open a Pull Request
4. Wait for CI tests to pass
5. Get code review if needed
6. Merge to master (triggers automatic deployment)

**Never push directly to master/main branch.**

## Running Tests

### Backend (PHPUnit)

```bash
# All tests
make test-backend

# Unit tests only
make test-backend-unit

# Functional tests only
make test-backend-functional

# Or directly
cd backend && ./vendor/bin/phpunit
```

### Frontend (Vitest)

```bash
# Run tests once
make test-frontend

# Watch mode
make test-frontend-watch

# Or directly
cd frontend && npm run test
```

### All Tests

```bash
make test
```

## Local Development

```bash
# Start containers
make up

# Full setup (first time)
make setup

# Run migrations
make migrate

# Reset database
make db-reset
```

## Code Style

### PHP
- Follow PSR-12
- Use strict types
- Avoid else/elseif (use early returns)
- No nested ternary operators

### JavaScript/Vue
- Use Composition API with `<script setup>`
- Use Pinia for state management
- Prefer async/await over .then()

## CI/CD

- **test.yml** - Runs on every PR to master
  - Backend PHPUnit tests
  - Frontend Vitest tests
  - Syntax and build checks

- **deploy.yml** - Runs on merge to master
  - Runs all tests
  - Deploys to production VPS

## Required GitHub Secrets

Configure these in repository settings:
- `SSH_HOST` - VPS IP address
- `SSH_USER` - SSH username
- `SSH_PRIVATE_KEY` - Private SSH key
- `SSH_PORT` - SSH port (default 22)
