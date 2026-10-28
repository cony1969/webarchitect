# Editor projektov

Projekt sa otvorí do samostatnej karty v strednom paneli kliknutím na názov projektu alebo ikonu ceruzky. Plus v hlavičke Projekty otvorí nový projekt. Pri každom projekte je samostatné plus pre doménu a pri doméne plus pre stránku.

Editor má vlastný rozpracovaný stav pre každú kartu. Prepnutie kariet zachováva neuložené polia. Bodka pri názve označuje neuložené zmeny; pri zatvorení karty alebo opustení stránky prehliadač upozorní na ich zahodenie. Uložené karty sa obnovujú podľa UUID projektu, pričom vlastné dáta sa vždy načítajú z API. Rozpracované neuložené údaje sa po potvrdenom obnovení stránky zahodia.

- **Uložiť zmeny** vytvorí nový záznam alebo upraví otvorený projekt a zvýši revíziu. Klávesová skratka je Cmd/Ctrl+S.
- **Uložiť ako nový** vyžiada názov a jedinečný slug; uloží aktuálne upravené nastavenia pod novým UUID a otvorí novú kartu. Pôvodný projekt a prípadné neuložené zmeny v jeho karte zostávajú zachované. Domény ani stránky sa nekopírujú.
- Chyby validácie a konflikt revízií zostávajú viditeľné pri nezmenených rozpracovaných údajoch. Pri konflikte možno načítať uloženú verziu alebo uložiť svoju prácu ako nový projekt.

Panel Vlastnosti používa lokálne priložený Select2. Predvolený typ je Laravel 13; ďalšie možnosti sú PHP, Python, Node.js a statický HTML/CSS/JS web. Pre Laravel sa ukladajú voľby vytvorenia zo vzoru, prihlasovacej stránky a databázy. Predvolený databázový systém je PostgreSQL; alternatívy sú MariaDB/MySQL a SQLite. Ponúkané vzory (základný, firemný, administrácia) zatiaľ definujú plán budúceho generátora.

V strednej karte sú názov, slug, popis, plánovaná hlavná doména, Git repozitár, prostredie a stav záznamu. Hlavná doména je metadátom plánovanej adresy; neprepisuje strom domén a nemení DNS. Git adresa neobsahuje prihlasovacie údaje a nespúšťa klonovanie. Údaje sa ukladajú v existujúcom JSONB `cms_projects.settings`; migrácia schémy nie je potrebná. Projektové voľby, vlastníctvo, slug a revízie kontroluje server.

Podľa potvrdeného rozsahu tejto etapy sa **ukladá iba konfigurácia**. Uloženie nevolá Composer ani shell, negeneruje web, nemení DNS, nezakladá účet a nevytvára databázu. Budúci generátor môže uloženú konfiguráciu použiť po samostatnom spustení vytvorenia projektu.

`GET /api/ui/project-tree` vracia vlastný strom, jeho revíziu a mapu pôvodných ID na UUID. Odpovede vytvorenia/úpravy/kópie projektu obsahujú tú istú snímku pod `project_tree`, aby sa ľavý panel aktualizoval bez obnovenia celej stránky. Čítanie snímky a zmeny používajú rovnaký zámok vlastníka. Stará cesta uloženia celého stromu naďalej kontroluje revíziu; pripravované formuláre preto nemôžu potichu prepísať zmeny iného okna.

## Overenie

Izolovaná PostgreSQL sada: `php vendor/bin/pest -c phpunit-cms.xml`. Testy overujú predvolené hodnoty, všetky typy, validáciu nastavení, vlastníctvo, aktualizáciu, kópiu rozpracovaných nastavení, konflikty, stabilné poradie projektov a absenciu vytvorenia skutočnej databázy. Pôvodné testy CMS zostávajú súčasťou tej istej sady.

Prehliadačová kontrola na izolovanej kópii pokrýva otvorenie existujúceho projektu, Select2, prepínanie typov a kariet bez straty rozpracovaných údajov, nový Laravel projekt so všetkými tromi voľbami, uloženie zmeny, vytvorenie kópie a opätovné načítanie kariet. Testovacia kópia používa výhradne `webarchitect_test` a má samostatný syntetický účet; dáta produkčných projektov sa pri skúške nemenia.
