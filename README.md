# Pivní Konto / Beer Ledger

Aplikace pro sledování konzumace piva mezi přáteli.

## Požadavky

- Docker & Docker Compose

## Rychlý start

```bash
# Spuštění
make setup

# Aplikace běží na:
# - Frontend: http://localhost:5173 (dev server)
# - API: http://localhost/api
# - API dokumentace: http://localhost/api/docs
```

## Příkazy

```bash
make up              # Spustit kontejnery
make down            # Zastavit kontejnery
make logs            # Zobrazit logy
make shell-php       # Shell v PHP kontejneru
make shell-node      # Shell v Node kontejneru
make migrate         # Spustit migrace
make fixtures        # Načíst testovací data
make db-reset        # Reset databáze
```

## Struktura

```
pivni-konto/
├── backend/         # Symfony API
├── frontend/        # Vue.js aplikace
├── docker/          # Docker konfigurace
└── docker-compose.yml
```

## API Endpoints

### Autentizace
- `POST /api/auth/register` - Registrace
- `POST /api/auth/login` - Přihlášení
- `GET /api/auth/me` - Aktuální uživatel

### Skupiny
- `GET /api/groups/my` - Moje skupiny
- `POST /api/groups/create` - Vytvořit skupinu
- `POST /api/groups/join` - Připojit se kódem

### Záznamy
- `POST /api/entries/quick-add` - Přidat pivo
- `DELETE /api/entries/{id}` - Smazat záznam

### Statistiky
- `GET /api/stats/me` - Moje statistiky
- `GET /api/stats/leaderboard/{groupId}` - Žebříček skupiny

## Technologie

- **Backend**: PHP 8.3, Symfony 7, API Platform
- **Frontend**: Vue.js 3, Tailwind CSS, Pinia
- **Databáze**: PostgreSQL 16
- **Cache**: Redis
- **Infrastruktura**: Docker, Nginx
