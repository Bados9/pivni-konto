# Pivní Konto - Development Guidelines

## Project Structure

- `backend/` - Symfony 7.2 API (PHP 8.3+)
- `frontend/` - Vue 3 + Vite SPA
- `.github/workflows/` - CI/CD pipelines

## Git Workflow

**IMPORTANT:** All code changes must go through Pull Requests.

### Feature development
1. Create a feature branch from `dev`
2. Make your changes
3. Open a Pull Request targeting `dev`
4. Wait for CI tests to pass
5. Get code review if needed
6. **Squash merge** to `dev` — one clean commit per feature (for a bigger feature, pre-squash the branch into a few thematic commits and rebase-merge instead)
7. Delete the feature branch after merging

### Deploying to production
1. Open a Pull Request from `dev` to `master`
2. Wait for CI tests to pass
3. **Merge commit** to `master` (triggers automatic deployment). NEVER squash or rebase a release: both rewrite commit SHAs, so `master` and `dev` would diverge and every future release PR would drag old commits along. A merge commit makes `master` contain `dev`'s history exactly, so release PRs always list only new work
4. Run any release-specific manual steps listed in the release PR description on the VPS

**Never push directly to master or dev branch.** Everything goes through Pull Requests.

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

- **test.yml** - Runs on every PR to `dev` or `master`
  - Backend PHPUnit tests
  - Frontend Vitest tests
  - Syntax and build checks

- **deploy.yml** - Runs on merge to `master` only
  - Runs all tests
  - Deploys to production VPS

## Required GitHub Secrets

Configure these in repository settings:
- `SSH_HOST` - VPS IP address
- `SSH_USER` - SSH username
- `SSH_PRIVATE_KEY` - Private SSH key
- `SSH_PORT` - SSH port (default 22)
