# Import existujúceho webu z ASUS-u — etapa 1

V sekcii Projekty je tlačidlo **Načítať web z ASUS-u**. Otvorí samostatnú kartu v strednom paneli; okolité panely aj ostatné karty zostávajú zachované. Po výbere zdroja a tlačidle **Analyzovať web** sa zobrazí čítací prehľad. Karta sa zapamätá, analýza sa pri ďalšom otvorení spustí znovu ručne.

Táto etapa nevytvára projekt, pracovnú kópiu, stránky ani súbory. Nespúšťa PHP zdrojového webu, sieťové požiadavky, odosielanie formulárov, databázové dotazy zdrojového webu ani Git/FTP. Nadväzujúci krok bude import vybraných stránok a sekcií do oddelenej pracovnej kópie s možnosťou porovnania.

## Zdroj a oprávnenia

`config/site-import.php` obsahuje explicitný zoznam povolených účtov a zdrojov na tomto serveri. Prvý účet (ID 1) má povolenú `postovna.online`, koreň `/www/wwwroot/postovna.online`, alias `www.postovna.online`. Doména bola overená v konfigurácii virtuálneho hosta. Katalóg sa zatiaľ dopĺňa na serveri; nie je to automatický úplný inventár všetkých DNS subdomén. Pri ďalšom samostatnom webe sa pridá samostatná položka aj jeho skutočný document root. Alias toho istého koreňa nie je duplicitný projekt/doména.

Backend overuje oprávnenie pri čítaní katalógu aj analýze. Neprijíma ľubovoľnú cestu, URL ani koreň od klienta a neposkytuje čítanie obsahu súborov. Symbolické odkazy sa neprechádzajú. Skryté súbory, zálohy, cache a koreňové adresáre vendor/node_modules/storage sa vynechávajú. `assets/vendor` sa započítava, lebo obsahuje potrebné frontend knižnice. Analýza má limit počtu a celkovej veľkosti súborov; veľké jednotlivé zdrojové súbory sa neparsujú.

GET `/api/cms/site-import/sources` vracia dostupné zdroje bez serverových ciest. POST `/api/cms/site-import/analyze` prijíma iba `source_id` z katalógu. Je chránený autentifikáciou, štandardným CSRF a obmedzením frekvencie. Odpovede nie sú cachované.

## Čo analýza znamená

- Stránky: HTML/PHP vstupné súbory v koreňovom adresári. Nie je to crawler ani úplný router frameworku.
- Sekcie: súbory `sections/*.php`; použitie sa odhaduje zo statických include a poľa `$Sections`. PHP komentáre sa ignorujú.
- Spoločné časti: `includes/*.php`.
- Súbory: celkový počet a veľkosť povolených súborov; obrázky, CSS, JS, fonty a PDF samostatne.
- Formuláre: formuláre nájdené v HTML šablónach, aj vzorových. Nespúšťajú sa. Z akcií sa odstraňujú query, fragmenty a používateľské údaje URL; zdrojový PHP kód ani prihlasovacie údaje sa do odpovede nevkladajú.
- Databáza: iba indícia výskytu PHP databázových volaní, nie overenie behu. `.env` sa nečíta.
- Chýbajúce súbory: lokálne href/src/action/poster odkazy v HTML vrátane nepoužitých šablón. PHP reťazce generujúce HTML sa neinterpretujú. Referencie v CSS/JS sa zatiaľ nekontrolujú.
- Subdomény: súrodenecké zdroje v povolenom katalógu. Odkazy na ďalšie subdomény sa ukážu ako odkazy bez dostupného zdroja; samy nepreukazujú server ani vlastníctvo.
- Fingerprint: SHA-256 inventára a obsahu súborov na zistenie zmeny medzi analýzou a neskorším importom. Pri budúcom importe sa musí znovu overiť; zatiaľ sa nič neimportuje.

## Prvý skúšobný web

Poštovňa používa jednoduché PHP skladanie, nie čistý statický HTML web. Má 4 koreňové stránky: index.php, order.php a dve vzorové HTML stránky. V 14 súboroch sekcií sa rozpoznalo 8 použitých sekcií. Spoločná hlavička, pätička, head a skripty sú oddelené. Konfigurácia ASUS-u obsahuje hlavnú doménu a www alias. `moja.postovna.online` je odkazovaná klientská zóna, jej zdroj tu zatiaľ nie je povolený/dostupný. PDF odkazy smerujú na chýbajúce súbory; formuláre potrebujú samostatné spracovanie pri importe.

## Ďalší krok

Vybrať hlavné stránky (index/objednávka), vzorové HTML ponechať voliteľné. Vytvoriť súkromný snapshot povolených súborov, nesprístupniť PHP obsluhu ani prihlasovacie údaje. Previesť spoločné časti a použité sekcie do CMS dokumentov so zachovaním pôvodu, importovať stránky s väzbami a prelinkovať lokálne assety do pracovného náhľadu. Formuláre v náhľade musia zostať neodosielateľné. Až potom overiť vizuálnu zhodu a editovanie/uloženie. Nové importy majú mať transakciu, identifikátor behu, overenie fingerprintu a ochranu pred duplicitným spustením.

## Overenie

PostgreSQL testy `SiteImportPreviewTest` používajú iba dočasné syntetické súbory a oddelenú testovaciu databázu. Pokrývajú čítací režim, žiadne vykonanie PHP ani zmeny CMS, odfiltrovanie privátnych súborov a PHP reťazcov, oprávnenia, ľubovoľné cesty/URL, symlinky, limit veľkosti, zmeny fingerprintu, frontend vendor assety a rozlíšenie subdomény/aliasu. Prehliadačová skúška beží v oddelenej kópii Web Architectu; produkčný zdroj sa iba číta.
