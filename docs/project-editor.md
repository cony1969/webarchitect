# Projekt a domény

Projekt je pomenovaný kontajner domén a subdomén. Jediné upraviteľné pole je názov. Interný slug sa pri vytvorení generuje automaticky a pri premenovaní zostáva stabilný.

Editor projektu sa otvára v strednej karte cez názov alebo ceruzku v strome. Obsahuje prehľad počtu domén/subdomén, stránok, priradených komponentov a sekcií, dátum úpravy projektu a zoznam domén s počtom ich stránok a základom webu. Rovnaké počty sú v pravom paneli Vlastnosti. Počty patria iba prihlásenému vlastníkovi a danému projektu; spoločná knižnica sa do nich nezapočítava.

## Samostatná konfigurácia domény

Každá doména a subdoména má vlastnú strednú kartu a pravý panel Vlastnosti. Otvára sa ceruzkou pri doméne alebo zo zoznamu domén v projekte. Plus pri projekte vytvára novú doménu. Doména obsahuje adresu bez protokolu/cesty, popis, Git adresu bez prihlasovacích údajov, prostredie a stav. Základ webu sa vyberá lokálnym Select2: Laravel 13 (predvolený), PHP, Python, Node.js alebo statický HTML/CSS/JS web.

Pre Laravel sú dostupné voľby vytvoriť zo vzoru, prihlasovaciu stránku a databázu; predvolená databáza je PostgreSQL. Každá doména ukladá nezávislé nastavenia do `cms_domains.settings`. Táto etapa iba ukladá konfiguráciu. Nespúšťa generátor, Composer, klonovanie ani vytváranie webov, databáz, účtov alebo DNS.

## Ukladanie

Oba editory majú Uložiť zmeny (Cmd/Ctrl+S) a Uložiť ako nový. Kópia projektu preberá iba názov a nevytvára domény ani stránky. Kópia domény preberá aktuálnu konfiguráciu, vyžiada inú adresu a nekopíruje stránky. Rozpracované karty zachovávajú stav pri prepínaní; zatvorenie/obnovenie upozorní na neuložené zmeny. Uložené karty sa obnovujú podľa UUID, dáta sa znovu načítajú z API.

API kontroluje vlastníctvo, platnú adresu, duplicitu domény v projekte a revíziu. Konflikt iného okna vracia 409 bez zahodenia rozpracovaných polí. Starý strom zostáva kompatibilný, ale takisto kontroluje revíziu. Snímky obsahujú mapy UUID projektov aj domén. Zmeny domén a stromu obnovujú prehľad počtov otvoreného projektu.

## Prechod existujúcich údajov

Migrácia `2026_10_28_140000_move_site_configuration_to_domains` pridáva revíziu/stav domény a prenesie pôvodné projektové nastavenia do jej existujúcich domén. Vlastné hodnoty domény majú prednosť. Projektové technické nastavenia sa vyprázdnia; pôvodné nastavenia a zdrojové dáta sú uložené v súkromnom denníku `cms_imports`, source `domain-settings-v1`. Aj projekt bez domén má pôvodnú konfiguráciu v tomto denníku. Prenos je idempotentný, nemení ID ani väzby stránok a zvýši revíziu stromu proti starým oknám. Staré požiadavky s technickými nastaveniami projektu dostanú validačnú chybu a pokyn obnoviť stránku.

Pred produkčnou migráciou treba súkromnú PostgreSQL zálohu. Samotný rollback schémy neobnovuje pôvodné nastavenia: obnova údajov musí rešpektovať neskoršie úpravy používateľov.

## Overenie

Izolovaná PostgreSQL sada: `php vendor/bin/pest -c phpunit-cms.xml`. Overuje názov projektu, počty a vlastníctvo, nezávislé konfigurácie domén, päť základov webu, Laravel voľby, validáciu, kópie, históriu, revízne konflikty, prenos existujúcej konfigurácie a neprítomnosť skutočného vytvorenia databázy. Testy používajú výhradne oddelenú databázu a rolu; bootstrap ich pred deštruktívnym resetom kontroluje.

Prehliadačová kontrola prebieha na izolovanej kópii so syntetickým účtom. Pokrýva projekt s dvoma rôznymi základmi webu, uloženie domény, jej kópiu a prepočítanie projektových počtov, prepínanie kariet a konzolu.
