# Bootstrap komponenty — postupné vytváranie

## 1. Kontajner (Container)

Hotová predloha je v `config/ui/components/bootstrap/zakladne-rozlozenie/bootstrap-container.json`. Obsahuje jeden prázdny element `<div class="container"></div>`, bez ukážkového obsahu, vlastného CSS a JavaScriptu. Šírku, vodorovné odsadenie a centrovanie rieši Bootstrap. Trieda je kompatibilná s Bootstrapom 5.0 v prostredí Web Architectu aj 5.3.2 na Poštovni; žiadny z balíkov sa týmto krokom nemení.

Oficiálna dokumentácia: https://getbootstrap.com/docs/5.3/layout/containers/

Založenie osobnej upraviteľnej predlohy:

```sh
php artisan cms:install-bootstrap container --user=EMAIL
```

Predloha sa objaví v Komponenty → Bootstrap → Základné rozloženie. Ceruzka otvorí existujúci JSON editor; uloženie upraví osobný komponent a zvýši jeho revíziu. Opätovné spustenie príkazu existujúci komponent vrátane používateľských úprav ponechá bez zmeny. Nevytvára duplicity a neobnovuje používateľom odstránenú predlohu.

Zdroj v Gite je východisková definícia. Osobná upravená verzia sa ukladá v PostgreSQL. Táto etapa pridáva obsah knižnice; vizuálne skladanie plátna a samostatný editor komponentov v strednej karte zostávajú ďalšou prácou. JSON editor je aktuálna cesta úpravy.

Nasledujúce kroky po overení Containeru: riadok (`row`), stĺpec (`col`) a až potom zložené komponenty. Každý komponent vytvoríme a overíme samostatne.

## Editor komponentov

Ceruzka pri existujúcom komponente otvorí vlastnú kartu v strednom paneli.
Vlastnosti vpravo obsahujú názov, slug, umiestnenie v knižnici a kategóriu.
Prepínač **Náhľad / JSON · Ace** mení zobrazenie v tej istej karte.
Prázdny Container má pomocný rám; rám a jeho popis nie sú súčasťou uloženej definície.
Náhľad je izolovaný iframe bez spúšťania JavaScriptu a bez odosielania formulárov.

**Uložiť zmeny** používa revíziu otvoreného komponentu. **Uložiť ako nový**
vyžaduje nový slug a vytvorí samostatnú položku knižnice z aktuálneho obsahu editora.
Duplicitný slug ani stará revízia neprepíšu existujúci komponent. Spoločnú predlohu
možno uložiť ako vlastnú kópiu. Neplatný JSON ponechá posledný platný náhľad a blokuje uloženie.
Neuložené zmeny sú označené v karte; prepínanie kariet ich zachová.

V migrovanej ani staršej kópii aplikácie sa nenašla integrácia Ace; doplnená je lokálna
verzia **ace-builds 1.45.2** z oficiálneho npm balíka. Súbory v `public/assets/libs/ace/`
obsahujú jadro, režim JSON, JSON worker, tému tomorrow_night a searchbox vrátane BSD licencie.
Inicializácia (`ace.edit`, `session.setMode`) a zrušenie inštancie sú v `public/js/component-editor.js`.
Ace má samostatnú inštanciu pre každú otvorenú kartu a pri zmene rozmerov sa prispôsobí.
