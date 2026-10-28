# Web Architect: základ CMS a PostgreSQL

Dátum: 28. 10. 2026. Táto etapa pripravuje ukladanie pre štyri budúce editory. Zachováva existujúci trojpanelový layout, accordion panely, karty, logo a antracitovú tému. Samotné štyri editory a publikovanie stránok patria do ďalšej etapy.

## Model

| Tabuľka | Význam |
| --- | --- |
| `cms_projects` | Projekty vlastníka a ich nastavenia |
| `cms_domains` | Domény priradené projektu |
| `cms_components` | Komponenty a spoločné predlohy |
| `cms_component_entries` | Umiestnenia komponentov v kategóriách; jedna definícia môže mať viac položiek |
| `cms_sections` | Opakovane použiteľné sekcie; staré layouty majú `kind=layout_template` |
| `cms_pages` | Stránky v projekte/doméne alebo samostatné stránky |
| `cms_document_revisions` | Snímky uložených dokumentov podľa čísla revízie |
| `cms_collections` | Revízie kompatibilných stromov projektov a layoutov |
| `cms_imports` | Záznam jednorazového importu a kontrolné súčty zdrojov |

Dokumenty majú nemenné UUID, vlastníka, názov, slug, stav `draft` alebo `archived`, nastavenia a obsah JSONB. `legacy_id` zachováva identifikátor používaný existujúcim stromom a kartami. `source_key` je logické umiestnenie komponentu; nie je to cesta, na ktorú aplikácia zapisuje súbor. `source_payload` uchováva pôvodné metadáta pre bezstratový prenos. Bežné API ho nevracia.

Spoločné komponenty a layouty majú `user_id=NULL` a `owner_key=library`. Ich úprava cez API je zakázaná; používateľ vytvorí vlastnú kópiu. Starý importný formulár pri úprave predlohy vytvorí súkromnú alternatívu s rovnakým logickým umiestnením. Ostatným používateľom zostane pôvodná predloha. Väzby vlastníctva nedovoľujú zmazať účet s dokumentmi CMS; existujúci formulár v takom prípade zobrazí validačnú chybu a používateľa neodhlási. Odstránenie/odovzdanie dát účtu treba dokončiť spolu so správou dokumentov. Osobné layouty sa pri prvom uložení oddelia od spoločného zoznamu.

Obsah má spoločný formát:

```json
{
  "schema_version": 1,
  "nodes": [
    {
      "id": "00000000-0000-4000-8000-000000000001",
      "type": "div",
      "attributes": {"class": "hero"},
      "children": ["Text alebo ďalšie uzly"]
    }
  ],
  "css": "",
  "js": ""
}
```

Uzly majú jedinečné UUID; textové deti uchovávajú medzery. Limity sú 2 MB obsahu, 5000 uzlov/textových detí a hĺbka 48. HTML/CSS/JS sa v tejto etape iba ukladajú. Budúci náhľad musí bežať v izolovanom iframe; nesmie spúšťať používateľský obsah v rozhraní CMS. Nie je implementovaný renderer ani publikovanie.

## API pripravené pre editory

Všetky cesty používajú prihlásenú webovú session, CSRF ochranu a JSON odpovede pre `Accept: application/json`. `{kind}` je `projects`, `components`, `sections` alebo `pages`.

| Metóda a cesta | Správanie |
| --- | --- |
| `GET /api/cms/{kind}` | Stránkovaný zoznam vlastných dokumentov a dostupných spoločných predlôh |
| `GET /api/cms/{kind}/{uuid}` | Jeden dostupný dokument |
| `POST /api/cms/{kind}` | Vytvorenie dokumentu, odpoveď 201 |
| `PUT /api/cms/{kind}/{uuid}` | Úprava vlastného dokumentu |
| `POST /api/cms/{kind}/{uuid}/copy` | Nová vlastná kópia s novým UUID, odpoveď 201 |

Vytvorenie a kópia požadujú `name` a `slug`; voliteľné sú `document`, `settings`, `status`, `project_id`, pri stránkach aj `domain_id`. Úprava vyžaduje tiež aktuálnu `revision`. Staršia revízia dostane 409 a dáta sa nezmenia. Neplatné dáta majú 422, cudzí dokument 404, priama úprava predlohy 403. Vlastníka určuje server; vstupy `user_id`, `owner_key` a `id` sa odmietajú.

Kompatibilné cesty `/api/ui/save-projects` a `/api/ui/save-layouts` teraz zapisujú transakčne do databázy a tiež vyžadujú `revision`. Layouty prijímajú objekt `{layouts: [...], revision: N}`. Stará stránka dostáva revízie priamo pri vykreslení. Súbežné uloženia z jedného okna sa postupne odošlú; pri konflikte iného okna sa zobrazí chyba. Formulár importu komponentu posiela `component_id` a `revision`, ak bol načítaný existujúci komponent. API zmeny projektov a stránok zvýšia aj revíziu stromu, aby ho staré otvorené okno nemohlo potichu prepísať.

## Import a nasadenie

1. Zálohovať pôvodnú MariaDB, `.env`, Git commit a `config/ui/content` aj `config/ui/components` mimo webrootu.
2. Pripraviť samostatnú PostgreSQL databázu a rolu bez administrátorských oprávnení. Nastaviť `DB_CONNECTION=pgsql`, port 5432, `DB_CHARSET=utf8`, prázdne `DB_URL`; zachovať pôvodný `APP_KEY` a ostatné nastavenia.
3. Spustiť `php artisan migrate --force` na novej databáze. Pred importom CMS preniesť používateľov, nastavenia, sessions a ostatné tabuľky Laravelu so zachovaním ID a heslových hashov; nastaviť sekvencie. MariaDB záznamy v `migrations` nekopírovať, migrácie sa vykonávajú na PostgreSQL.
4. Počas krátkej údržby spraviť konečný prenos z MariaDB a porovnať každý prenesený riadok. Spustiť `php artisan cms:import-legacy` v aplikácii používajúcej PostgreSQL.
5. Porovnať stromy po používateľoch, katalóg, layouty, obsah komponentov a kontrolné súčty zdrojov. Prepnúť produkčné `.env`, obnoviť PHP proces a overiť prihlásenie, dashboard a HTTP health check.

Import prijíma aj `--path=/absolute/path/to/ui`. Opakované spustenie existujúci import iba oznámi; nemení a neduplikuje dáta. Celý import je jedna transakcia. Súbory JSON sa nemenia a za behu sa už nepoužívajú na ukladanie ani čítanie obsahu CMS. Statické konfigurácie rozhrania zostávajú v `config/ui`.

Pri aktuálnom prenose: 3 používatelia, 2 projekty, 2 domény, 7 stránok, 54 položiek katalógu, 53 rôznych komponentových záznamov a 6 layoutových predlôh. Iba 4 komponenty už majú skutočnú definíciu; ostatné zostávajú označené ako zástupné položky. Dve položky Title odkazujú na tú istú definíciu a zostávajú v oboch kategóriách.

Prevádzkové zálohy a jednorazové overovacie/prenosové skripty sú na ASUS v súkromnom adresári `/home/cony/.local/state/wa-cms-foundation` (prístup iba správcu). Heslá ani `.env.testing` sa necommitujú. PostgreSQL používa samostatné produkčné a testovacie role/databázy.

## Overenie

Na izolovanej kópii aplikácie s dev závislosťami vytvoriť `.env.testing` obsahujúci prihlasovacie údaje výhradne k databáze `webarchitect_test` a role `webarchitect_test_app`. Potom:

```sh
php vendor/bin/pest -c phpunit-cms.xml
```

Konfigurácia aj bootstrap testov vynucujú izolovanú databázu a rolu. Testy používajú `RefreshDatabase` a preto sa nesmú spúšťať proti produkčnej databáze. Pokrývajú idempotentný import, zachovanie zdrojov, vlastníctvo, spoločné predlohy, revízie a konflikty, kópie, samostatné stránky, spätný prenos HTML atribútov a vykreslenie pôvodného rozhrania.

Pôvodná sada `php vendor/bin/pest` zostáva oddelená a používa svoju predvolenú SQLite konfiguráciu.

## Obnova

Pred opätovným otvorením webu možno vrátiť kód na checkpoint `68b64d4`, obnoviť súkromnú zálohu pôvodného `.env` a znovu načítať PHP proces. Pôvodná MariaDB a súbory JSON ostávajú zachované. Po vzniku nových dát v PostgreSQL treba najprv spraviť jej zálohu a preniesť nové zmeny; samotné prepnutie späť na starú databázu by ich nezachovalo. V produkcii nespúšťať `migrate:fresh` ani `migrate:rollback` na odstránenie dát CMS.
