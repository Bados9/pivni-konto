# Custom Beers - Návrh funkcionality

## Cíl

Umožnit uživatelům navrhnout nové pivo (zadají pouze název). Pivo se po schválení adminem zpřístupní všem uživatelům.

---

## Aktuální stav

- Entita `Beer` má pole: `name`, `brewery`, `style`, `abv`, `logo`, `createdAt`
- Piva se seedují přes `app:load-beers` (~175 českých piv)
- API endpoint `POST /api/beers` vyžaduje `ROLE_ADMIN`
- `GET /api/beers` vrací všechna piva (bez paginace, ~175 záznamů)
- EasyAdmin má plný CRUD pro piva
- Achievement `breweries_5` ("Turista") počítá `COUNT(DISTINCT b.brewery)` — čistě string porovnání, case-sensitive, ignoruje NULL brewery

---

## Návrh změn

### 1. Backend — Beer entita

Přidat pole:

| Pole | Typ | Default | Popis |
|------|-----|---------|-------|
| `status` | string (enum) | `pending` | `pending` / `approved` / `rejected` |
| `submittedBy` | ManyToOne → User | null | Kdo pivo navrhl |

Enum hodnoty pro `status`:
- **pending** — čeká na schválení, viditelné pouze autorovi
- **approved** — schváleno adminem, viditelné všem
- **rejected** — zamítnuto (duplicita, nesmysl...)

### 2. Backend — API úpravy

#### POST /api/beers (nový endpoint pro uživatele)
- Přístupné pro `ROLE_USER`
- Uživatel posílá pouze `name` (string, povinné)
- Server automaticky nastaví `status = pending`, `submittedBy = current user`
- Validace: unikátní název (case-insensitive) — zamezit duplicitám
- State processor (podobně jako BeerEntryProcessor) pro auto-nastavení submittedBy

#### GET /api/beers
- Pro běžné uživatele vrací:
  - Všechna piva se `status = approved`
  - **Plus** piva se `status = pending` kde `submittedBy = current user`
- Pro admina vrací vše

### 3. Backend — EasyAdmin

- Přidat pole `status` a `submittedBy` do BeerCrudController
- Filtr podle statusu (pending/approved/rejected)
- Akce pro hromadné schválení
- Při schválení admin doplní: `brewery`, `abv`, `style` (případně `logo`)

### 4. Backend — Migrace

```
ALTER TABLE beer ADD status VARCHAR(20) NOT NULL DEFAULT 'approved';
ALTER TABLE beer ADD submitted_by_id UUID DEFAULT NULL;
ALTER TABLE beer ADD CONSTRAINT FK_beer_submitted_by FOREIGN KEY (submitted_by_id) REFERENCES "user"(id);
```

Existující piva dostanou `status = approved`, `submittedBy = NULL`.

### 5. Frontend — BeerSelect komponenta

- Přidat tlačítko/odkaz "Nenašel jsi své pivo? Navrhni nové"
- Jednoduchý modal/formulář s jedním polem: název piva
- Po odeslání:
  - Nové pivo se okamžitě objeví v seznamu uživatele (jako pending)
  - Vizuální odlišení pending piv (např. šedý text, ikona ⏳)
  - Tooltip: "Čeká na schválení"
- Po schválení adminem se pivo zobrazí normálně všem

### 6. Frontend — Notifikace (nice-to-have)

- Po schválení piva zobrazit uživateli toast notifikaci
- Volitelné: email notifikace

---

## Achievementy — analýza dopadů

### Jak to funguje teď

Achievementy se vyhodnocují **v reálném čase** při každém novém záznamu (BeerEntry). Relevantní achievementy pro piva:

| Achievement | Podmínka | Dotaz |
|-------------|----------|-------|
| `variety_5` | 5 různých piv | `COUNT(DISTINCT b.id)` |
| `variety_15` | 15 různých piv | `COUNT(DISTINCT b.id)` |
| `variety_30` | 30 různých piv | `COUNT(DISTINCT b.id)` |
| `breweries_5` | 5 různých pivovarů | `COUNT(DISTINCT b.brewery)` kde `brewery IS NOT NULL` |
| `loyal_fan` | 100× stejné pivo | `MAX(count) z GROUP BY beer_id` |
| `loyal_fan_500` | 500× stejné pivo | dtto |

### Co se stane, když admin doplní brewery dodatečně

**Není problém.** Achievementy se počítají dynamicky z aktuálních dat:

1. Uživatel zadá pending pivo bez brewery → zapíše si BeerEntry s tímto pivem
2. `breweries_5` achievement ho **nepočítá** (brewery je NULL, dotaz má `WHERE brewery IS NOT NULL`)
3. Admin schválí pivo a doplní brewery → hodnota v Beer entitě se změní
4. Při dalším záznamu se achievementy **přepočítají** a nový pivovar se započítá
5. Achievement se odemkne, pokud uživatel dosáhl prahu

**Důležité:** Achievement check běží při každém novém BeerEntry. Takže po doplnění brewery adminem se achievement odemkne až při příštím záznamu uživatele (ne okamžitě po schválení). To je akceptovatelné chování.

### Variety achievementy (různá piva)

Pending pivo **má vlastní ID** → počítá se do `COUNT(DISTINCT b.id)` okamžitě. Žádný problém.

### Loyal fan achievementy

Fungují na `beer_id` → pokud uživatel pije stále stejné pending pivo, počítá se. Žádný problém.

### Case-sensitivity pivovarů

Existující problém (nesouvisí s touto feature): `COUNT(DISTINCT b.brewery)` je case-sensitive. "Pivovar Bernard" a "pivovar bernard" by se počítaly jako dva různé pivovary. Řešení: normalizace brewery při ukládání (trim, consistent casing). Lze řešit samostatně.

---

## Ochrana proti zneužití

1. **Rate limiting** — max 3 návrhy piv za hodinu na uživatele
2. **Validace názvu** — min 2 znaky, max 100 znaků, žádné speciální znaky
3. **Duplicita** — case-insensitive kontrola proti existujícím pivům (approved + pending)
4. **Pending limit** — max 10 neschválených piv na uživatele

---

## Pořadí implementace

1. **Migrace** — nová pole `status`, `submittedBy`
2. **Beer entita** — enum, relace, validace
3. **API** — POST endpoint pro uživatele, filtrování GET
4. **EasyAdmin** — správa pending piv
5. **Frontend** — UI pro návrh piva, vizuální odlišení
6. **Testy** — unit + functional pro nové chování
