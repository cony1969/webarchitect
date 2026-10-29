# Web Architect — pokračovanie na inom zariadení

Aktualizované: **29. 10. 2026**. Stav po vytvorení pomenovaných layoutov.
Tento dokument je odovzdanie kontextu pre ďalšie zariadenie alebo agenta.
Pri pokračovaní overiť aktuálny Git a databázu; údaje nižšie sú stavom k uvedenému dátumu.

## 1. Účel a smerovanie projektu

Web Architect je osobný CMS a vizuálny nástroj na skladanie webov z opakovane použiteľných komponentov, sekcií, layoutov a stránok.
Používateľom je Jozef Králik; pracovný účet je **dex.kralik@gmail.com**, v aktuálnej databáze ID **2**.
Najprv sa vyvíja pre vlastnú potrebu, neskôr môže byť sprístupnený ďalším používateľom.

Pracuje sa na ASUS-e. Budúce weby klientov sa môžu prenášať cez Git alebo FTP, ale to teraz nie je prioritou.
Prvým skúšobným existujúcim webom je **postovna.online**. Nie je čisto statický: používa jednoduché PHP skladanie spoločných častí a sekcií.

Zámerom je, aby sa výsledná stránka skladala z existujúcich komponentov a ich projektových verzií.
**Vue sa nepoužije.** Pre výsledný web sa plánuje spoločný generovaný CSS súbor; tento publikačný krok zatiaľ nie je hotový.

## 2. Kde sa pracuje a ako nadviazať

| Položka | Hodnota |
| --- | --- |
| Bežiaca aplikácia | https://wa.conygroup.eu/dashboard |
| SSH host | `asus` |
| Skutočný projekt na serveri | `/www/wwwroot/wa.conygroup.eu` |
| GitHub | https://github.com/cony1969/webarchitect |
| Git remote | `git@github.com:cony1969/webarchitect.git` |
| Pracovná vetva | `codex/asus-import-preview` |
| Posledný funkčný commit pred týmto dokumentom | `84e8a11` — Build named layouts from reusable Bootstrap components |
| PHP na ASUS-e | `/www/server/php/84/bin/php` — pri kontrole 8.4.27 |
| Aplikácia | Laravel 13, Blade, Alpine.js, JavaScript; PostgreSQL |
| Bootstrap v editore | lokálne CSS 5.0.2 |
| JSON editor / výbery / farby | lokálny Ace, Select2, Spectrum |

Pôvodný lokálny pracovný priečinok na Macu je `/Users/jozefkralik/Documents/ChatGPT/webarchitect`.
**Nie je to plný aktuálny checkout aplikácie**; slúžil najmä na dočasné súbory a snímky.
Zdrojom pravdy je serverový projekt a jeho Git, dáta CMS sú v PostgreSQL.
Kópia tohto dokumentu je v lokálnom aj serverovom projektovom priečinku a v repozitári.

Prvé príkazy na novom zariadení so správne nastaveným SSH aliasom a prístupom:

```sh
ssh asus 'cd /www/wwwroot/wa.conygroup.eu && git status --short && git branch --show-current && git log -5 --oneline'
```

Pri poslednej kontrole bol okrem našich commitov **samostatne zmenený `composer.lock`**.
Táto zmena sa do commitu layoutov nezahrnula; neprepisovať ju ani automaticky necommitovať.
Neobnovovať server zo starého lokálneho priečinka a nepoužiť slepý `git reset --hard` alebo `git pull` cez neoverené zmeny.

Prístupové údaje sú v chránenom `.env` na serveri a v existujúcej SSH konfigurácii zariadenia.
Heslá, tokeny, `.env`, databázové dumpy ani privátne prenosové skripty nepatria do tohto dokumentu ani do Gitu.
Pri ďalšej práci s históriou pôvodného webu: `sub/war` na pokerclub.sk bol pôvodný zdroj; aktuálne úpravy patria na **wa.conygroup.eu**.

## 3. Rozhranie a panely

Zachovať existujúci trojpanelový layout, hornú lištu a stredné karty.
Rozhranie je antracitové s tyrkysovými akcentmi. Logo je kružidlo s pravítkom; kružidlo je tmavo tyrkysové.
Logo je na začiatku topbaru, z loga je pripravená favicon.

### Ľavý panel

Sekcie sú **accordion**, bez pôvodných zvislých resizerov medzi sekciami:

- **Komponenty**: záložky Natívne, Bootstrap, Ostatné; v nich kategórie a komponenty. Ceruzka otvorí komponent v samostatnej strednej karte. Veľa starších položiek katalógu je stále iba zástupných; existencia názvu neznamená hotovú definíciu.
- **Layouts**: zoznam osobných/dostupných predlôh a tlačidlo na nový layout. Klik otvorí layout editor v strednej karte.
- **Projekty**: strom projekt → doména/subdoména → stránky. Je tu aj tlačidlo **Načítať web z ASUS-u**, ktoré otvára kartu analýzy existujúceho webu.

Akcie stromu sú vpravo: **+**, ceruzka, plný kôš. Koše sú zarovnané pod seba aj pri vnorených položkách.
Klik na akciu musí byť oddelený od kliknutia na riadok; kôš nesmie otvoriť editor stránky.
Po odstránení stránky sa zatvára aj jej otvorená karta.

### Stredný panel

Obsahuje otvorené editory vo vlastných kartách. Aktívna karta má viditeľný tyrkysový spodný border; výška lišty bola zvýšená o 2 px.
Zmeny v rozpracovanom editore zostávajú pri prepínaní kariet v pamäti. Neuložený stav je označený.
Zoznam otvorených kariet a aktívna karta sa ukladajú cez existujúce používateľské nastavenia.
Po obnovení sa uložené dokumenty načítajú z API podľa UUID; samotné neuložené úpravy nie sú trvalo uložený dokument.

Náhľady sú izolované iframe. Blueprint mriežka predstavuje plochu stránky.
Pomocné rámy, názvy na borderoch a editorové odsadenia sa do výsledného webu nepridávajú.
Spodný stavový riadok zobrazuje režim a kontext/objekt pod kurzorom.

### Pravý panel

- **Vlastnosti**: menia sa podľa aktívneho editora a vybraného prvku.
- **Vzhľad**: accordion so záložkami Templates a Iné; časť obsahu je zatiaľ základ/pripravené miesto pre ďalšiu prácu.

Vybrané záložky vlastností sa ukladajú do **Laravel `session()`**, podľa používateľa a typu panela.
Ukladajú sa aj prepnutie Náhľad/JSON komponentu a záložky Vzhľadu.
Výber v knižnici, kategórie, strom a otvorené karty majú vlastné existujúce ukladanie; pri zmenách ho zachovať.

## 4. Editory — aktuálny stav

### Editor projektu — hotový základ

Projekt je pomenovaný obal domén a subdomén. Upravuje sa **iba názov**; technológia patrí doméne.
Má **Uložiť zmeny** a **Uložiť ako nový**. Kópia projektu nevytvára automaticky jeho domény a stránky.
Stred aj pravé vlastnosti ukazujú počty domén/subdomén, stránok, projektových komponentov a sekcií.
Obsahuje prehľad domén a dostupných projektových komponentov.

### Editor domény — hotový konfiguračný základ

Každá doména/subdoména má samostatnú kartu a technické nastavenia; rôzne subdomény môžu používať rôzny základ.
Ukladá sa adresa, popis, Git adresa bez hesiel/tokenov, prostredie a stav.
Základ webu je Select2: **Laravel 13** ako predvolený, PHP, Python, Node.js, statický HTML/CSS/JS.
Pre Laravel sú voľby **Vytvoriť zo vzoru**, **Vytvoriť prihlasovaciu stránku**, **Vytvoriť databázu**; databázovým smerovaním je PostgreSQL.
Tieto voľby **zatiaľ ukladajú nastavenia**. Reálne negenerujú web, databázu, prihlasovanie ani DNS.

Pravé záložky sú **Všeobecné / Bootstrap**.
Bootstrap má color pickery pre osem významových farieb domény. Paleta sa premieta do náhľadu stránok danej domény.
Pri lokálnom Bootstrape 5.0.2 sú doplnené aj štýly bežných utilít/tlačidiel/navigácie; úplná tematizácia všetkých budúcich komponentov ešte nie je hotová.

### Editor komponentov — funkčný

V strednej karte je **Náhľad / JSON · Ace**, **Uložiť zmeny** a **Vložiť do projektu**.
Pôvodné tlačidlo Uložiť ako nový bolo pri komponentoch zámerne nahradené vložením do projektu.
Neplatný JSON blokuje uloženie a ponechá posledný platný náhľad.

Pravé vlastnosti patria tomuto editoru a majú tri záložky:

1. **Základné nastavenia**: názov, slug, umiestnenie v knižnici, kategória, revízia, stav a otvorenie Ace.
2. **Bootstrap**: bežné ovládače, responzívne breakpointy, možnosť pridať ďalšie/zobraziť všetky; Select2 katalóg tried vychádza z nainštalovaného CSS.
3. **CSS**: jednotlivé deklarácie, grafické zarovnanie, box model a pseudo stavy.

Vyhľadávanie vlastností rozumie slovenským aj anglickým názvom, ignoruje diakritiku a zároveň filtruje CSS Select2.
CSS názvy pochádzajú z lokálneho katalógu 671 vlastností MDN Data; možno zachovať vlastné názvy aj CSS premenné.
Tlačidlo **+** pridáva riadok. Riadok bez názvu vlastnosti sa neuloží; kontroluje to aj server.
Samostatné farebné vlastnosti majú hodnotu ako farebné tlačidlo Spectrum bez stále zobrazeného kódu.
Zložené hodnoty, napríklad border alebo tieň, zachovávajú text a pickery jednotlivých farieb.

Grafický box model upravuje margin, padding, border a jednotlivé strany; voliteľne ich spája.
Ikonkový prepínač zarovnania vytvára `text-align`. Obe pomôcky zapisujú tie isté CSS deklarácie ako zoznam, nemajú oddelený zdroj údajov.
Pod deklaráciou je Select2 pseudo stavu, napr. bežný stav, `:hover`, `:focus`, `:active`, `:disabled`, `::before`; spolu 30 možností.
Odlišná hodnota tej istej vlastnosti pre ďalší stav sa rieši ďalším riadkom. Funkčné selektory s parametrami zatiaľ nemajú grafický editor.

Bežné deklarácie sú v `attributes.style`, pseudo deklarácie v `attributes.style_rules`.
Samostatné pole CSS dokumentu existuje tiež. Náhľad ich skladá a izoluje; **spoločný publikovaný CSS súbor ešte neexistuje**.
Pri viacerých koreňových prvkoch možno vybrať upravovaný koreň. Plnohodnotný vizuálny výber všetkých vnútorných prvkov je ďalšia práca.

Plná šírka je prepínacie tlačidlo, nie checkbox: zapne šírku rodiča a ďalší klik obnoví pôvodné triedy.
Rozlišovať `.container-fluid`, šírku prvku `w-100` a Bootstrap stĺpce `col-…` vo vnútri `.row`.

### Projektové verzie komponentov — hotové

**Vložiť do projektu** umožňuje vybrať zdroj z knižnice alebo z už existujúceho vlastného projektu, cieľový projekt a stránku.
Prvé použitie vytvorí vlastnú definíciu cieľového projektu; ďalšie použitia rovnakého zdroja zachovajú túto upravenú verziu.
Kópia z iného projektu je nezávislá vrátane vnorených závislostí.
Úprava jedného projektu tak nemení ostatné weby ani pôvodný Bootstrap komponent.
Stránky odkazujú na projektové definície pomocou `cms-component`.

### Layout editor — posledná dokončená etapa

Layout sa otvára ako stredná karta a ukladá vlastný dokument s revíziou.
Je to predloha rozloženia, technicky `cms_sections.kind=layout_template`; nie je totožná s bežnou obsahovou sekciou.
Aktuálne podporuje:

- vloženie existujúceho Kontajnera, ďalšieho Kontajnera alebo textu do vybraného vhodného prvku;
- vnorené komponentové inštancie, strom štruktúry a odstránenie vybraného prvku;
- názvy na borderoch náhľadu; klik na názov označí konkrétnu inštanciu a zosúladí pravé vlastnosti;
- plnú šírku a výšku rodiča pre dané použitie; pôvodná definícia komponentu sa nemení;
- otvorenie vybraného zdrojového komponentu cez pravý panel v editore komponentov;
- pri obyčajných štrukturálnych uzloch úpravu názvu oblasti a Bootstrap tried;
- pomocné náhľadové rámy iba pre vložené komponenty, nie pre každý interný `div.container` v ich definícii.

Pomenované layouty sú pripravené pre účet ID 2:

| Názov | Obsah |
| --- | --- |
| `blank` | pôvodná skúšobná zostava dvoch vnorených kontajnerov; zachovaná |
| `table` | kontajner + tabuľka, filter, vyhľadávanie, akcie a stránkovanie |
| `form` | kontajner + formulár s názvom, e-mailom, popisom a tlačidlami |
| `sidebar` | bočná navigácia + hlavný obsah |
| `topbar` | horná navigácia + hlavný obsah |
| `topbar + sidebar` | horná navigácia + bočný panel + hlavný obsah |

Starý preklep `tobbar + sidebar` bol opravený; otvorené staré karty sa mapujú na správny layout.
Tabuľkové a formulárové ovládače sú zatiaľ ukážková štruktúra bez napojenia na živé dáta/backend.
Navigačné rozloženia sa prispôsobujú šírke iframe; na malej šírke sa skladajú pod seba.
V poslednej kontrole sa odstránilo vynútenie plnej výšky pri obsahu nových predlôh, ktoré vedelo pri automatickej výške orezať obsah.
Pri ďalších zmenách plnej výšky overiť aj kombináciu automatickej výšky rodiča a responzívneho gridu.

Používajú sa nové skutočné definície **Content, Navbar, Sidebar, Table, Form** a existujúci **Container**.
Layouty teraz odkazujú na osobnú knižnicu. Úprava zdrojovej definície sa môže prejaviť vo viacerých layoutoch toho istého používateľa.
**Prenos layoutu do projektu/stránky musí v ďalšej etape zachovať princíp projektových kópií.** Tento krok ešte nie je hotový.
V editore zatiaľ nie je všeobecný vizuálny výber a vkladanie ľubovoľného komponentu ani drag-and-drop/presúvanie všetkých oblastí.

### Editor stránky — čiastočný

Stránky sa vytvárajú a zobrazujú v strome; otvorená karta má zložený náhľad vložených komponentov a vlastnosti.
Existuje renderer komponentových odkazov, CSS a palety domény.
**Plný vizuálny page editor** s priradením layoutu, slotmi, skladaním/presúvaním obsahu ešte nie je dokončený.

### Editor sekcií — plánovaný

Databázový model a všeobecné dokumentové API existujú.
Samostatný hotový vizuálny editor opakovane použiteľných obsahových sekcií ešte nie je implementovaný.
Pôvodný zámer štyroch obsahových editorov (projekt, komponent, sekcia, stránka) je doplnený o samostatný editor domény a layoutu.

## 5. Hotové technické základy a opravy

- Prenos pôvodného projektu na ASUS a migrácia CMS z MariaDB/súborov na PostgreSQL.
- Dokumenty JSONB s UUID, vlastníctvom, revíziami a históriou. Konflikt súbežného uloženia vracia 409.
- Oddelenie spoločných predlôh, osobných verzií a projektových komponentov; kontrola vlastníctva aj na serveri.
- Bezpečné skladanie komponentov: explicitný cieľ vnoreného obsahu, kontrola závislostí, cyklov a limitov.
- Lokálne knižnice Ace/Select2/Spectrum, bez zavedenia Vue.
- Opravené mazanie stránok, oddelenie koša od riadka a zatvorenie zodpovedajúcej karty.
- Mazanie dokumentov stromu používa **soft delete**. Samostatný používateľský kôš/obnova ešte nie je hotový.
- Zdieľaný Bootstrap modal pre hlásenia a potvrdenia; nepoužívať natívne `alert`, `confirm`, `prompt`.
- Opravené posúvanie celého shellu v Safari a zmiznutie iframe po zatvorení/odstránení iných kariet. Pri regresii kontrolovať presun iframe medzi DOM uzlami.
- Stav záložiek vlastností v serverovej session a zachovanie existujúceho ukladania knižnice a kariet.
- Inštalácia Bootstrap predlôh a pomenovaných layoutov je opakovateľná; existujúce používateľské úpravy sa neprepisujú.

## 6. Import Poštovne — hotová analýza, import ešte nie

Tlačidlo **Načítať web z ASUS-u** otvorí analýzu explicitne povoleného zdroja.
Aktuálne je povolený účet ID 2 a `/www/wwwroot/postovna.online`, alias `www.postovna.online`.
Analýza číta súbory, zisťuje stránky, sekcie, spoločné časti, assety, formuláre, chýbajúce odkazy a fingerprint.
Nevykonáva zdrojové PHP, neodosiela formuláre a nečíta `.env` zdrojového webu.
Nejde o úplný crawler ani inventár všetkých subdomén servera.

Pri prvom rozbore: 4 koreňové stránky (vrátane 2 vzorových HTML), 14 súborov sekcií, z toho 8 rozpoznaných ako použitých.
Zdroje a ich aktuálny stav overovať novou analýzou. `moja.postovna.online` je odkazovaná klientská zóna, nie automaticky importovaný dostupný zdroj.

Používateľ chce neskôr **Načítať aj Aktualizovať**, opakovane použiteľné editovateľné komponenty a zachovanie vlastných úprav.
Súhlasil s prácou v aktuálnom priečinku Poštovne po zálohe, netreba budovať druhý verejný web.
Starší `docs/site-import.md` navrhuje pracovnú kópiu; táto časť návrhu je prekonaná týmto rozhodnutím používateľa.
Analýza nie je hotový import, Poštovňa zatiaľ nie je kompletne prestavaná do CMS komponentov.
Git Poštovne je samostatný repozitár; pred prácou overiť jeho aktuálny stav, nezamieňať ho s Gitom Web Architectu.

## 7. Plánované práce a odporúčané poradie

Prvý bod je výslovne dohodnutá najbližšia úloha. Ostatné body sú návrh ďalšieho postupu; používateľ chce pokračovať postupne, krok po kroku.

1. **Ako prvú prácu odstrániť prázdny priestor nad komponentom/kontajnerom v blueprint náhľade.** Ide o pomocné horné odsadenie editora rezervované pre názvy na rámčekoch, nie o uložený margin/padding komponentu. Upraviť náhľad tak, aby nevytváral zbytočný prázdny pás, a pritom zachovať čitateľné a klikateľné názvy na borderoch. Overiť náhľad komponentov aj layoutov v otvorenom Safari. Pri odovzdaní je táto oprava iba naplánovaná; ešte nebola vykonaná.
2. **Dotiahnuť skladanie layoutu.** Doplniť výber ľubovoľného hotového komponentu z knižnice, jasné cieľové oblasti/sloty, presun a poradie prvkov. Najprv overiť uloženie a opätovné otvorenie existujúcich predlôh.
3. **Pridať samostatné Bootstrap Row a Col.** V nových predlohách už grid existuje ako štrukturálne uzly; samostatné upraviteľné komponenty ešte nie. Zjednotiť ich výber, responzívne šírky a povolené vnorenie.
4. **Prepojiť layout so stránkou.** Výber layoutu pri stránke, vytvorenie potrebných projektových kópií, stabilné sloty pre obsah a zachovanie lokálnych úprav. Jasne rozlíšiť úpravu definície a konkrétnej inštancie.
5. **Dokončiť editor stránok a sekcií.** Výber/vkladanie komponentov a sekcií, zmena poradia, uloženie a opätovné použitie; zachovať dnešné stredné karty a pravé vlastnosti.
6. **Postupne dopĺňať Bootstrap knižnicu.** Zástupné položky nahrádzať skutočnými definíciami. Pri Poštovni najprv porovnať existujúce a použité komponenty/layout, nie generovať neoverené duplicity.
7. **Publikačný renderer a spoločný CSS súbor.** Zachovať editovateľný JSON ako zdroj pravdy, pri generovaní získať deterministické selektory a pseudo pravidlá, zlúčiť CSS použitých komponentov a paletu domény. Bootstrap môže zostať samostatná knižničná závislosť. Overiť deduplikáciu, poradie/kaskádu, izoláciu inštancií a invalidáciu cache. Vue nezavádzať.
8. **Načítať/Aktualizovať Poštovňu.** Záloha, fingerprint, väzby na pôvodné súbory/komponenty, opakovateľný import bez duplicít, konflikty používateľských zmien a vizuálne porovnanie. Formuláre a obsluhu PHP riešiť osobitne. Vzory HTML neimportovať automaticky ako produkčné stránky.
9. **Až neskôr skutočné vytváranie webov/databáz**, Laravel vzory a prihlasovanie, publikovanie na ASUS a budúci export ku klientovi cez Git/FTP.

## 8. Dôležité súbory pre pokračovanie

Cesty v tejto časti sú relatívne ku koreňu serverového repozitára.

| Oblasť | Súbory |
| --- | --- |
| Routy | `routes/web.php` |
| Shell a karty | `resources/views/components/layouts/engine.blade.php`, `resources/views/components/ui/canvas-tabs.blade.php` |
| Ľavý a pravý panel | `resources/views/components/ui/sidebar-left/`, `sidebar-right/`, `sidebar-accordion.blade.php` |
| Projekt a doména | `public/js/project-editor.js`, `resources/views/components/ui/project-editor/`, `domain-editor/` |
| Komponenty a náhľad | `public/js/component-editor.js`, `component-controls.js`, `pseudo-styles.js`, `resources/views/components/ui/component-editor/` |
| CSS ovládače | `public/js/box-model.js`, `alignment-controls.js`, `color-picker.js`, `resources/data/css-properties.json`, `css-pseudos.json` |
| Layout editor | `app/Services/Cms/LayoutEditor.php`, `public/js/layout-editor.js`, `resources/views/components/ui/layout-editor/` |
| Predlohy layoutov | `app/Services/Cms/StarterLayouts.php`, `app/Console/Commands/InstallStarterLayouts.php` |
| Bootstrap definície | `app/Services/Cms/BootstrapComponents.php`, `config/ui/components/bootstrap/` |
| Projektové kópie a skladanie | `app/Services/Cms/ProjectComponents.php`, `PageComposition.php`, `public/js/page-preview.js` |
| Dokumenty a história | `app/Services/Cms/DocumentData.php`, `DocumentStore.php`, `app/Models/Cms/` |
| Strom a layout knižnica | `app/Services/Cms/ProjectTree.php`, `SectionLibrary.php` |
| Farby domény | `app/Services/Cms/BootstrapPalette.php`, `DomainSettings.php` |
| Session záložky | `public/js/session-tabs.js`, `app/Http/Controllers/UI/SessionTabController.php` |
| Import / analýza | `app/Services/Cms/AsusSiteAnalyzer.php`, `config/site-import.php`, `public/js/site-import.js` |
| Spoločný modal | `resources/views/components/ui/modal.blade.php` |

Staršie podrobné dokumenty: `docs/cms-storage.md`, `docs/project-editor.md`, `docs/site-import.md`, `docs/bootstrap-components.md`.
Niektoré opisujú staršie etapy a budúci stav, ktorý už bol čiastočne dokončený. Aktuálny súhrn je tento dokument a zdrojový kód.
Napríklad skoré tvrdenie „renderer neexistuje“ už neplatí pre náhľad; publikovanie stále hotové nie je.

Kanonický dokument:

```json
{
  "schema_version": 1,
  "nodes": [
    {
      "id": "UUID",
      "type": "div",
      "attributes": {"class": "container"},
      "children": []
    }
  ],
  "css": "",
  "js": ""
}
```

Odkaz na komponent má `type: "cms-component"`, UUID komponentu v `attributes.component_id` a prípadne `content_target: "root"`.
`full_width` a `full_height` patria inštancii v layoute. `editor_label` pomenúva štrukturálnu oblasť.
`source_key` je logická identita, nie používateľom zadaná cesta na zápis súboru.
Živé dáta sa ukladajú do PostgreSQL, JSON v `config/ui/components` sú východiskové predlohy.

## 9. Overovanie a nasadenie

**Kontroly UI robiť v už otvorenom Safari používateľa. Neotvárať vlastný prehliadač ani izolovaný browser.**
Na inom zariadení najprv zistiť dostupné okná a prihlásenie. Nezamieňať účty ani iné otvorené projekty.
Viaceré úlohy môžu zdieľať Safari; pred akciou znovu overiť aktuálnu stránku.

Testovacia kópia na ASUS-e: `/home/cony/.local/state/wa-cms-foundation/testapp`.
Má samostatnú PostgreSQL databázu `webarchitect_test` a rolu `webarchitect_test_app`; bootstrap testov ich kontroluje.
Testy používajú `RefreshDatabase`, preto sa **nesmú spúšťať proti produkcii** ani s produkčnými prístupmi.
Pred testovaním zosúladiť potrebné zdrojové súbory testovacej kópie s aktuálnym commitom bez prepísania jej `.env.testing`.

```sh
ssh asus 'cd /home/cony/.local/state/wa-cms-foundation/testapp && /www/server/php/84/bin/php vendor/bin/pest -c phpunit-cms.xml --filter="StarterLayoutsTest|BootstrapContainerTest|LayoutEditorTest"'

ssh asus 'cd /home/cony/.local/state/wa-cms-foundation/testapp && node --test tests/js/layoutEditor.test.mjs'
```

Ďalšie tematické testy sú v `tests/Postgres/` a `tests/js/`.
Pred posledným commitom prešli súvisiace PHP testy (18 testov), JavaScript layout editora (8 testov) a po responzívnej úprave znovu `StarterLayoutsTest` (4 testy, 72 assertions).
V Safari boli overené všetky pomenované layouty a otvorenie Table v editore komponentov.
To nie je tvrdenie, že bola spustená celá historická testovacia sada.

Pri Blade zmene:

```sh
ssh asus 'cd /www/wwwroot/wa.conygroup.eu && sudo -u www /www/server/php/84/bin/php artisan view:cache'
```

Pri potrebnom obnovení PHP:

```sh
ssh asus 'sudo systemctl reload wa-project-php'
```

Pri prenose cez rsync zachovať vlastníkov a práva: používali sa `--no-perms --no-owner --no-group`.
Pri JS/CSS zmene aktualizovať príslušnú verziu assetu v `engine.blade.php`, aby Safari nenačítalo starý kód.

Pomocné CLI príkazy (nahradiť EMAIL skutočným vlastníkom):

```sh
php artisan cms:install-bootstrap container --user=EMAIL
php artisan cms:install-layouts --user=EMAIL
```

Druhý príkaz dopĺňa chýbajúce/prázdne predlohy, zámerne neprepisuje už upravený layout.
Nepoužívať ho ako spôsob vynúteného resetu alebo aktualizácie používateľských zmien.

## 10. Zálohy a záväzné pracovné pravidlá

Pred zásahom do živých dát spraviť súkromnú zálohu a kontrolovať rozsah účtu/projektu.
Privátne prevádzkové podklady sú mimo webrootu v `/home/cony/.local/state/wa-cms-foundation` a pri posledných layoutoch v `/home/cony/.local/state/wa-starter-layouts`.
Pred vytváraním pomenovaných layoutov vznikli `before.dump` a `code-before.tar.gz` v druhom adresári.
Sú to historické body obnovy, nie automaticky najnovšia záloha; neobnovovať ich cez novšie dáta bez kontroly.

- Nikdy nepoužívať natívne aplikačné dialógy; vždy spoločný Bootstrap modal.
- Záložky vlastností pamätať v serverovej session podľa účtu a typu panela.
- Hlavičky tabuliek `nowrap`, horizontálne rolovanie pri nedostatku miesta; číselné stĺpce aj hlavičky zarovnávať vpravo, boolean ikony centrovať, vyhľadávanie napravo.
- Zachovať rozhranie, stredné karty, používateľské výbery a uložené nastavenia. Nezavádzať Vue.
- Komponenty jednotlivých projektov musia zostať nezávislé; pri kopírovaní neprepísať už upravený cieľ.
- Náhľad nesmie vykonávať používateľské/zdrojové PHP alebo JS ani odosielať formuláre. Layout má iba vlastný kontrolovaný most na výber prvku cez `postMessage` s overením zdroja/tokena.
- Spoločné predlohy a cudzie účty nemeniť priamou úpravou používateľského dokumentu. Zachovať revízne kontroly a transakcie.
- Nenahrádzať soft delete trvalým mazaním. Nespúšťať `migrate:fresh` ani reset produkčnej databázy.
- Zachovať nesúvisiace zmeny v Gite a neukladať tajomstvá. Po dokončení otestovanej etapy uložiť príslušné zmeny do Gitu.

Na pôvodnom zariadení sú tieto spoločné pravidlá aj v osobnom skille `~/.codex/skills/asus-projects/SKILL.md`.
Na inom zariadení tento skill nemusí existovať; preto sú pravidlá uvedené aj tu.
