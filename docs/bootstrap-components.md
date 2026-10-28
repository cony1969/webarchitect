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
