# Code Review - Zbývající problémy

Datum: 2026-01-29

## Opraveno (Kritické a Vysoké)

- [x] Rate limiting na auth endpointy
- [x] API Platform GetCollection security
- [x] UUID validace vstupů
- [x] .env zabezpečení

---

## Střední priorita

### 1. N+1 problém v leaderboard
- [x] **OPRAVENO** - Přepsáno na jeden dotaz s LEFT JOIN

---

### 2. Neefektivní výpočet streak
- [x] **OPRAVENO** - Načtení všech dnů najednou a výpočet v PHP

---

### 3. AchievementService načítá všechny entries
- [x] **OPRAVENO** - Použity agregační dotazy v repository (getAchievementStatsByUser)

---

### 4. Chybějící databázové indexy
- [x] **OPRAVENO** - Přidány indexy do entity, **nutné vytvořit migraci:**
```bash
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate
```

---

### 5. Token v localStorage (Frontend)
**Soubor:** `frontend/src/services/api.js:5`

JWT token v localStorage je náchylný k XSS útokům.

**Řešení:**
- Použít HttpOnly cookies pro refresh token
- Držet access token pouze v paměti (Pinia store)
- Implementovat token refresh endpoint

---

### 6. Hardcoded redirect ve frontend API
- [x] **OPRAVENO** - Použit event systém (CustomEvent 'auth:unauthorized')

---

## Nízká priorita

### 7. Magic strings pro role
- [x] **OPRAVENO** - Vytvořen enum GroupRole a použit v GroupController a AchievementService

---

### 8. Chybí DTO objekty
Přímé mapování request dat na entity bez validační vrstvy.

**Řešení:** Vytvořit DTO třídy pro requesty (QuickAddEntryRequest, CreateGroupRequest, atd.)

---

### 9. Chybí logging
Žádné logování důležitých akcí.

**Řešení:** Přidat Monolog logging pro audit trail (login, registrace, smazání).

---

### 10. Docker container jako root
**Soubor:** `docker-compose.yml:64`

```yaml
node:
    user: root
```

**Řešení:** Vytvořit neprivilegovaného uživatele v `docker/node/Dockerfile`.

---

### 11. Chybí unit testy
Projekt nemá žádné automatizované testy.

**Řešení:** Přidat PHPUnit testy pro:
- Repository metody
- Service logiku (AchievementService)
- Controller endpointy (funkční testy)

---

### 12. Invite kód je krátký
- [x] **OPRAVENO** - Prodlouženo na 16 znaků, **nutné vytvořit migraci pro změnu sloupce**

---

### 13. User enumeration při registraci
- [x] **OPRAVENO** - Použita generická chybová zpráva

---

## Poznámky

- Po opravě indexů (#4) spustit `EXPLAIN ANALYZE` na dotazy pro ověření
- Pro caching (#3) zvážit použití Symfony Cache s Redis pro další optimalizaci
- Testy (#11) jsou kritické před nasazením do produkce
- Token v localStorage (#5) vyžaduje větší refactoring backend i frontend
