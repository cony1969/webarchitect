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
upravené cez výsledky ostane medzi pridanými ovládačmi. CSS ponúka celý katalóg vlastností v Select2 vrátane vlastných názvov už použitých
v komponente. Horné vyhľadávanie filtruje ponuku Select2; samotné vyhľadávanie
definíciu nemení. Nový riadok sa pridáva tlačidlom +.

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

## Select2 pre CSS vlastnosti

Pole Vlastnosť používa Select2 s lokálnym katalógom 671 CSS názvov z MDN Data
2.37.2 (`css/properties.json`, CC0, prevzaté 2026-10-28). Zdroj:
https://github.com/mdn/data/blob/main/css/properties.json . Názvy sú uložené v
`resources/data/css-properties.json`, licencia v `resources/data/MDN-DATA-LICENSE`.
Abstraktný záznam `--*` sa neponúka; konkrétne vlastné premenné `--nazov` možno
pridať v Select2. Existujúce neznáme názvy z JSON zostávajú zachované.

Horné vyhľadávanie filtruje ponuku každého CSS Select2. Vnútorné vyhľadávanie
Select2 môže výsledky ďalej zúžiť. Obe podmienky sa vyhodnocujú spoločne; vyhľadávanie rozpoznáva slovenské
slová a ignoruje diakritiku. Bežné názvy sú pred vendorovými rozšíreniami. Filter nemení existujúce riadky ani hodnoty.
Tlačidlo + otvorí nový výber s aktuálnym filtrom.

Pri samostatných farebných vlastnostiach sa hodnota zobrazuje iba farebným
tlačidlom. Kód ostáva v JSON a v otvorenom pickeri. Viacfarebné hodnoty majú
samostatné tlačidlá pre jednotlivé farby. Zložené vlastnosti ako border a box-shadow
zachovávajú textový vstup pre rozmery a štýl aj pickery pre jednotlivé farby.

## Grafický box model v CSS komponentu

V záložke CSS je vnorený diagram margin / border / padding / obsah. Úprava strany
vytvorí alebo aktualizuje príslušný fyzický longhand v rovnakom zozname CSS riadkov,
napríklad `margin-top` alebo `border-left-width`. Nevzniká druhý zdroj nastavení.
JSON, náhľad a existujúce ukladanie používajú tieto isté deklarácie. Prepínače
Spojiť strany aplikujú ďalšiu úpravu na všetky štyri strany. Okraj má výber strany,
štýlu a farebné tlačidlo; nová šírka bez explicitného štýlu pridá `solid`.

Diagram číta inline štýl cez CSSStyleDeclaration, vrátane existujúcich shorthandov
a priority deklarácií. Nezobrazuje vypočítané rozmery ani hodnoty externých tried.
Prázdne pole označuje nenastavenú inline vlastnosť. Číslo bez jednotky znamená px,
iné jednotky, `auto` pre margin, `calc()` a `var()` sa zachovávajú. Prehliadač
validuje hodnotu pred zápisom. Vymazanie odstráni iba daný longhand a môže odkryť
pôvodný shorthand. Zmena strany sa zaradí za pôvodné deklarácie; dôležité inline
štýly a prítomné Bootstrap utility zachovajú účinok cez `!important`. Ostatné
triedy, korene a štýly komponentu sa nemenia.

## Grafické zarovnanie textu

V CSS záložke komponentu je ikonkový prepínač pre `text-align`: nenastavené,
vľavo, na stred, vpravo a do bloku. Výber vytvorí alebo upraví jediný CSS riadok;
Nenastavené odstráni iba explicitný `text-align`. Triedy a ostatné štýly ostávajú
zachované. Pri existujúcej Bootstrap textovej utilite alebo dôležitom inline
zarovnaní sa zapíše `!important`, aby bol nový výber účinný. Nenastavené obnoví
správanie pôvodných tried alebo dedenie. Toto nastavenie zarovnáva text v prvku,
nie polohu samotného kontajnera.

Zmeny CSS riadka aj JSON obnovia stav ikoniek. Pri inej platnej hodnote ako
`start`, `end` či `inherit` sa zobrazí jej názov bez automatického prepísania.
Prepínač má prístupné názvy, stav výberu a klávesové ovládanie šípkami/Home/End.

## Pseudo stavy jednotlivých CSS vlastností

Každý CSS riadok má jeden Select2 pre stav: Bežný stav, :hover, :focus,
:focus-visible, :active, :disabled, :checked a ďalšie jednoduché stavy a pseudo
prvky (30 možností spolu). Výber umožňuje hľadať podľa CSS názvu aj slovenského
popisu. Funkčné selektory s parametrami, napríklad :nth-child() a :has(), zatiaľ
nemajú grafický editor. Popisy sú v resources/data/css-pseudos.json.

Bežný stav uloží deklaráciu do style. Vybraný pseudo stav sa uloží ako style_rules
pri tom istom prvku: name, value, pseudos. Grafický editor používa jeden stav na
riadok. Importované staršie pravidlo s viacerými stavmi rozdelí na samostatné
riadky so zachovaním významu. Na inú hodnotu toho istého CSS názvu slúži kopírovanie riadka. Box a zarovnanie
upravujú iba bežný stav a zachovávajú jeho pseudo varianty. JSON aj API overujú
povolené selektory, názvy, hodnoty a limity aj pri priamom vložení JSON.

Náhľad komponentu aj zloženej stránky vygeneruje izolované selektory pre každý
výskyt prvku. Pri pseudo pravidlách sa bežný inline štýl prenesie do rovnakého
stylesheetu, aby neblokoval pseudo pravidlo vyššou inline prioritou. Definícia
v databáze sa tým nemení. Ostatné komponenty ani CMS tieto pravidlá neovplyvnia.
::before a ::after potrebujú content; podporu a použiteľné vlastnosti jednotlivých
stavov/prvkov určuje prehliadač. Hotový spoločný publikovaný CSS súbor domény
zostáva samostatným ďalším krokom.

## Šírka komponentu a plocha stránky

Bootstrap záložka obsahuje rýchlu voľbu Roztiahnuť na celú šírku rodiča. Nahradí
kontajnerové triedy triedou container-fluid, existujúce šírky stĺpca (vrátane
responzívnych alternatív) triedou col-12 a percentuálne šírky triedou w-100.
Prvok bez existujúcich tried col sa automaticky nemení na stĺpec. Bežné inline
prvky alebo d-inline dostanú d-block, aby na nich width účinkovalo; d-none sa zachová. Ostatné triedy,
CSS, pseudo varianty a obsah zostávajú zachované. Voľba Šírka komponentu 100 %
spustí tú istú úpravu. Vlastné CSS min/max-width, dôležitý width, offsety či
vonkajšie odstupy môžu ďalej obmedziť rozloženie; rýchla voľba ich neprepisuje.

Šírka stĺpca (z 12) ponúka col, col-auto a 1–12 pre vybraný breakpoint. Napríklad
md + 12 zapíše col-md-12 a zachová odlišné nastavenia iných breakpointov.
Stĺpce sa používajú vo vnútri .row; container-fluid určuje šírku kontajnera.
Plná šírka vnoreného prvku sa počíta voči jeho rodičovi, nie mimo neho.

Mriežka náhľadu predstavuje plochu stránky. Náhľad nepridáva vlastné vodorovné
odsadenie body, takže šírka container-fluid zodpovedá celej ploche mriežky.
Zmena tried sa ako ostatné úpravy uloží až cez Uložiť zmeny.
