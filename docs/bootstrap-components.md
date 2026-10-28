# Bootstrap komponenty — postupné vytváranie

## 1. Kontajner (Container)

Hotová predloha je v `config/ui/components/bootstrap/zakladne-rozlozenie/bootstrap-container.json`. Obsahuje jeden prázdny element `<div class="container"></div>`, bez ukážkového obsahu, vlastného CSS a JavaScriptu. Šírku, vodorovné odsadenie a centrovanie rieši Bootstrap. Trieda je kompatibilná s Bootstrapom 5.0 v prostredí Web Architectu aj 5.3.2 na Poštovni; žiadny z balíkov sa týmto krokom nemení.

Oficiálna dokumentácia: https://getbootstrap.com/docs/5.3/layout/containers/

Založenie osobnej upraviteľnej predlohy:

```sh
php artisan cms:install-bootstrap container --user=EMAIL
```

Predloha sa objaví v Komponenty → Bootstrap → Základné rozloženie. Ceruzka otvorí existujúci JSON editor; uloženie upraví osobný komponent a zvýši jeho revíziu. Opätovné spustenie príkazu existujúci komponent vrátane používateľských úprav ponechá bez zmeny. Nevytvára duplicity a neobnovuje používateľom odstránenú predlohu.

Zdroj v Gite je východisková definícia. Osobná upravená verzia sa ukladá v PostgreSQL. Úpravy prebiehajú v samostatnom editore komponentov v strednej karte; projektové použitia odkazujú na vlastnú definíciu projektu.

Nasledujúce kroky po overení Containeru: riadok (`row`), stĺpec (`col`) a až potom zložené komponenty. Každý komponent vytvoríme a overíme samostatne.

## Editor komponentov

Ceruzka pri existujúcom komponente otvorí vlastnú kartu v strednom paneli.
Vlastnosti vpravo obsahujú názov, slug, umiestnenie v knižnici a kategóriu.
Prepínač **Náhľad / JSON · Ace** mení zobrazenie v tej istej karte.
Prázdny Container má pomocný rám; rám a jeho popis nie sú súčasťou uloženej definície.
Náhľad je izolovaný iframe bez spúšťania JavaScriptu a bez odosielania formulárov.

**Uložiť zmeny** používa revíziu otvoreného komponentu. **Vložiť do projektu**
otvorí výber zdroja (knižnica alebo vlastné komponenty existujúcich projektov), cieľového projektu a stránky.
Prvé použitie vytvorí vlastnú definíciu cieľového projektu. Ďalšie použitia rovnakého zdroja
zachovajú už upravenú projektovú verziu; nikdy ju neprepíšu pôvodným vzorom.
Kópia z iného projektu je nezávislá vrátane vnorených odkazov na komponenty.
Projektové verzie sú dostupné v karte projektu v sekcii Komponenty projektu.

Stránka ukladá uzly `cms-component` s odkazom na definíciu projektu. Náhľad stránky
rieši tieto odkazy, skladá existujúce uzly, CSS a JS a kontroluje vlastníctvo, vnorenie a cykly.
Náhľad ostáva izolovaný bez vykonávania skriptov; publikovanie webu je samostatný budúci krok.
Vloženie je transakčné, kontroluje revíziu zdroja aj stránky a pri opakovaní tej istej
požiadavky nepridá druhé použitie. Nový úmyselný klik môže vložiť ďalšie použitie.
Neplatný JSON ponechá posledný platný náhľad a blokuje uloženie.
Neuložené zmeny sú označené v karte; prepínanie kariet ich zachová.

V migrovanej ani staršej kópii aplikácie sa nenašla integrácia Ace; doplnená je lokálna
verzia **ace-builds 1.45.2** z oficiálneho npm balíka. Súbory v `public/assets/libs/ace/`
obsahujú jadro, režim JSON, JSON worker, tému tomorrow_night a searchbox vrátane BSD licencie.
Inicializácia (`ace.edit`, `session.setMode`) a zrušenie inštancie sú v `public/js/component-editor.js`.
Ace má samostatnú inštanciu pre každú otvorenú kartu a pri zmene rozmerov sa prispôsobí.

## Záložky vlastností komponentu

Pravý panel **iba Editora komponentov** má záložky Základné nastavenia, Bootstrap a CSS.
Základné nastavenia zachovávajú metadáta a otvorenie Ace. Bootstrap ponúka bežné
nastavenia, pridanie ďalšieho ovládača, zobrazenie všetkých nastavení a vyhľadateľný
Select2 výber všetkých dostupných tried vrátane vlastných tried. Katalóg možností sa
odvodzuje z nainštalovaného `public/css/bootstrap.min.css` (5.0.2).
Voľba mení len príslušnú skupinu tried pre vybraný breakpoint; ostatné triedy zachová.

CSS tvorí zoznam `vlastnosť: hodnota` s tlačidlom +. Riadky bez názvu vlastnosti
sa pri ukladaní vynechajú aj na serveri. Hodnoty podporujú úvodzovky, funkcie,
URL a `!important`; samostatný riadok nesmie obsahovať ďalšiu CSS deklaráciu.
Pri viacerých koreňových prvkoch sa dá zvoliť upravovaný prvok. Tieto nastavenia
menia jeho inline štýl, nie globálne pole CSS komponentu. Neupravené štýly sa pri
uložení iného nastavenia neprepisujú.

Zmeny vlastností sa premietajú do JSON a náhľadu, úpravy JSON zasa do ovládačov.
Ukladanie používa existujúce oprávnenia a kontrolu revízie. Projektová verzia zostáva
oddelená od knižnice; editory projektu a domény tieto záložky nemajú.

Vyhľadávanie v záložkách Bootstrap a CSS rozpoznáva slovenské aj anglické názvy,
bez ohľadu na veľké písmená, diakritiku či pomlčky. Bootstrap pri hľadaní prechádza
aj rozšírené nastavenia; po vymazaní dotazu sa vráti pôvodný výber. Nastavenie
upravené cez výsledky ostane medzi pridanými ovládačmi. CSS ponúka bežné vlastnosti
aj vlastné názvy už použité v komponente. Kliknutie pridá riadok alebo zameria
existujúci, bez vytvorenia duplicity; samotné vyhľadávanie definíciu nemení.

## Farby domény a komponentov

Vlastnosti domény majú záložky Všeobecné a Bootstrap. Každá doména ukladá vlastnú
paletu ôsmich významových farieb v `settings.bootstrap.colors`. Hex hodnoty sa
validujú na serveri a normalizujú na šesťmiestny zápis. Paleta sa prenesie aj pri
kopírovaní domény, bez ovplyvnenia pôvodnej alebo susednej domény.

Náhľad stránky načíta paletu svojej domény. Pri súčasnom Bootstrape 5.0.2 sa okrem
premenných dopĺňajú farebné utility, tlačidlá a outline tlačidlá, odkazy a alerty;
primary aj pre bežné aktívne navigačné prvky. Ďalšie zložené komponenty budú
pribúdať postupne spolu s ich tematizáciou. Predvolená paleta CSS nemení.
Tieto štýly sa vkladajú do izolovaného náhľadu, nie do rozhrania CMS.
Publikovanie alebo prestavba pôvodného webu zostáva ďalším krokom.

Výber farieb používa existujúci lokálny Spectrum color picker. V komponentoch je
picker pri CSS farbách vrátane jednotlivých farieb v orámovaní, tieňoch a gradientoch.
Zmena zachová rozmery, funkcie, ostatné farby aj `!important`. URL, komentáre a
reťazce sa neinterpretujú ako farby. Textový vstup ostáva dostupný aj pre CSS
premenné a moderné farebné formáty; picker ich neprepisuje bez výberu používateľa.
Priehľadnosť je dostupná pre CSS komponentu; významová paleta Bootstrapu používa
nepriehľadné farby. Klik na Zrušiť nemení definíciu.
