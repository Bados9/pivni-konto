# Pivní Konto / Beer Ledger

> Aplikace pro sledování konzumace piva mezi přáteli

---

## Název aplikace

| Jazyk | Název | Popis |
|-------|-------|-------|
| **Česky** | Pivní Konto | Evokuje "účet" v hospodě, jednoduché a výstižné |
| **Anglicky** | Beer Ledger | Odpovídající překlad, "ledger" = účetní kniha |

---

## Technologický stack

### Backend
- **PHP 8.3** s frameworkem **Symfony 7**
- **API Platform 3** pro REST API (automatická dokumentace, filtry, pagination)
- **Doctrine ORM** pro práci s databází
- **LexikJWTAuthenticationBundle** pro JWT autentizaci

### Frontend
- **Vue.js 3** s Composition API
- **Tailwind CSS** pro styling (mobile-first)
- **PWA** (Progressive Web App) pro mobilní zážitek
- **Vite** jako build tool

### Databáze & Cache
- **PostgreSQL 16** jako hlavní databáze
- **Redis** pro cache, sessions a queues

### Infrastruktura
- **Docker** & **Docker Compose**
- **Nginx** jako reverse proxy a webserver

---

## Docker architektura

```
┌─────────────────────────────────────────────────────────────┐
│                        Docker Network                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐  │
│  │  Nginx  │───▶│   PHP   │───▶│ Postgres│    │  Redis  │  │
│  │  :80    │    │   FPM   │    │  :5432  │    │  :6379  │  │
│  └─────────┘    └─────────┘    └─────────┘    └─────────┘  │
│       │                                                     │
│       ▼                                                     │
│  ┌─────────┐                                               │
│  │  Node   │ (pouze pro build)                             │
│  │  Vite   │                                               │
│  └─────────┘                                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Kontejnery

| Kontejner | Image | Účel | Porty |
|-----------|-------|------|-------|
| `nginx` | nginx:alpine | Webserver, reverse proxy | 80, 443 |
| `php` | php:8.3-fpm-alpine | Laravel backend | 9000 (interní) |
| `postgres` | postgres:16-alpine | Databáze | 5432 |
| `redis` | redis:alpine | Cache, sessions, queues | 6379 |
| `node` | node:20-alpine | Frontend build (dev) | 5173 (dev) |

---

## Struktura projektu

```
pivni-konto/
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   └── Dockerfile
│   └── node/
│       └── Dockerfile
├── docker-compose.yml
├── docker-compose.prod.yml
├── backend/                    # Symfony aplikace
│   ├── src/
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Service/
│   │   ├── EventSubscriber/
│   │   └── DataFixtures/
│   ├── config/
│   │   ├── packages/
│   │   └── routes/
│   ├── migrations/
│   └── ...
├── frontend/                   # Vue.js aplikace
│   ├── src/
│   │   ├── components/
│   │   ├── views/
│   │   ├── stores/
│   │   ├── services/
│   │   └── router/
│   ├── public/
│   └── ...
└── docs/
    └── API.md
```

---

## Datový model

### Hlavní entity

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│    users     │       │    groups    │       │    beers     │
├──────────────┤       ├──────────────┤       ├──────────────┤
│ id           │       │ id           │       │ id           │
│ name         │       │ name         │       │ name         │
│ email        │       │ invite_code  │       │ brewery      │
│ password     │       │ created_by   │──┐    │ style        │
│ avatar       │       │ created_at   │  │    │ abv          │
│ created_at   │       └──────────────┘  │    │ logo         │
└──────────────┘              │          │    │ created_at   │
       │                      │          │    └──────────────┘
       │    ┌─────────────────┘          │           │
       │    │                            │           │
       ▼    ▼                            │           │
┌──────────────────┐                     │           │
│   group_user     │                     │           │
├──────────────────┤                     │           │
│ user_id       ───┼─────────────────────┘           │
│ group_id         │                                 │
│ role (admin/member)                                │
│ joined_at        │                                 │
└──────────────────┘                                 │
       │                                             │
       │         ┌───────────────────────────────────┘
       │         │
       ▼         ▼
┌─────────────────────┐
│    beer_entries     │
├─────────────────────┤
│ id                  │
│ user_id          ───┼──▶ users
│ group_id         ───┼──▶ groups
│ beer_id          ───┼──▶ beers (nullable - custom)
│ custom_beer_name    │    (pokud beer_id je null)
│ quantity            │    (počet piv, default 1)
│ volume_ml           │    (objem v ml, default 500)
│ consumed_at         │    (kdy bylo vypito)
│ note                │
│ created_at          │
└─────────────────────┘
```

### Tabulky pro statistiky (denormalizované)

```
┌─────────────────────────┐
│   daily_stats           │
├─────────────────────────┤
│ id                      │
│ user_id                 │
│ group_id                │
│ date                    │
│ total_beers             │
│ total_volume_ml         │
└─────────────────────────┘
```

---

## API Endpoints

### Autentizace
```
POST   /api/auth/register        # Registrace
POST   /api/auth/login           # Přihlášení
POST   /api/auth/logout          # Odhlášení
GET    /api/auth/user            # Aktuální uživatel
```

### Uživatelé
```
GET    /api/users/me             # Profil
PUT    /api/users/me             # Úprava profilu
```

### Skupiny
```
GET    /api/groups               # Moje skupiny
POST   /api/groups               # Vytvořit skupinu
GET    /api/groups/{id}          # Detail skupiny
PUT    /api/groups/{id}          # Upravit skupinu
DELETE /api/groups/{id}          # Smazat skupinu
POST   /api/groups/join          # Připojit se kódem
GET    /api/groups/{id}/members  # Členové skupiny
```

### Piva (databáze)
```
GET    /api/beers                # Seznam piv (s vyhledáváním)
GET    /api/beers/{id}           # Detail piva
POST   /api/beers                # Přidat pivo (admin)
```

### Záznamy pití
```
POST   /api/entries              # Přidat pivo (quick add)
GET    /api/entries              # Moje záznamy
GET    /api/entries/{id}         # Detail záznamu
PUT    /api/entries/{id}         # Upravit záznam
DELETE /api/entries/{id}         # Smazat záznam
```

### Statistiky
```
GET    /api/stats/me             # Moje statistiky
GET    /api/stats/group/{id}     # Statistiky skupiny
GET    /api/stats/leaderboard/{groupId}  # Žebříček
GET    /api/stats/history/{groupId}      # Historie (graf)
```

---

## Fáze implementace

### Fáze 1: Základ (MVP)
1. **Docker setup**
   - Konfigurace všech kontejnerů
   - Docker Compose pro dev i prod

2. **Backend základ**
   - Symfony instalace a konfigurace
   - API Platform setup
   - Databázové migrace (Doctrine)
   - JWT autentizace (LexikJWTAuthenticationBundle)

3. **Frontend základ**
   - Vue.js projekt s Vite
   - Tailwind CSS konfigurace
   - Router a základní layouty

### Fáze 2: Hlavní funkce
4. **Uživatelé a skupiny**
   - Registrace/přihlášení
   - Vytváření a správa skupin
   - Pozvánky přes kód

5. **Přidávání piv**
   - Quick add tlačítko
   - Výběr piva z databáze
   - Ruční zadání data/času
   - Historie záznamů

### Fáze 3: Statistiky a UX
6. **Statistiky**
   - Osobní přehledy
   - Skupinové žebříčky
   - Grafy a vizualizace

7. **PWA a notifikace**
   - Service worker
   - Offline podpora
   - Push notifikace (volitelné)

### Fáze 4: Polish
8. **Optimalizace**
   - Caching
   - Performance tuning
   - Testy

---

## UI/UX koncept (Mobile-first)

### Hlavní obrazovky

```
┌─────────────────────┐
│  ═══ Pivní Konto ═══│
│                     │
│  ┌─────────────────┐│
│  │  Dnes: 3 🍺     ││
│  │  Tento týden: 12││
│  └─────────────────┘│
│                     │
│  ╔═════════════════╗│
│  ║                 ║│
│  ║    🍺 + 1       ║│  ← Velké tlačítko
│  ║                 ║│
│  ╚═════════════════╝│
│                     │
│  [Vybrat pivo ▼]    │
│  [Změnit čas  ▼]    │
│                     │
├─────────────────────┤
│ 🏠  📊  👥  ⚙️     │  ← Navigace
└─────────────────────┘
```

### Navigace
- **Domů** - Quick add, dnešní přehled
- **Statistiky** - Grafy, žebříčky
- **Skupina** - Členové, porovnání
- **Profil** - Nastavení, historie

---

## Bezpečnost

- HTTPS only (v produkci)
- CORS konfigurace (NelmioCorsBundle)
- Rate limiting na API
- Validace všech vstupů (Symfony Validator)
- Sanitizace výstupů
- Bezpečné ukládání hesel (Argon2id)
- JWT tokeny s expirací a refresh tokeny

---

## Budoucí rozšíření

- [ ] Nativní mobilní aplikace (React Native / Flutter)
- [ ] Sociální funkce (komentáře, reakce)
- [ ] Achievements / odznaky
- [ ] Integrace s pivovary
- [ ] Export dat
- [ ] Tmavý režim
- [ ] Více jazykových mutací

---

## Příkazy pro spuštění

```bash
# Development
docker-compose up -d

# Produkce
docker-compose -f docker-compose.prod.yml up -d

# Symfony migrace
docker-compose exec php bin/console doctrine:migrations:migrate

# Symfony cache clear
docker-compose exec php bin/console cache:clear

# Frontend dev server
docker-compose exec node npm run dev
```
