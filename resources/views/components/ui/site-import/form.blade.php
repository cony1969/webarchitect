<div class="wa-project-editor" x-data="waSiteImport">
    <header class="wa-project-toolbar">
        <div><span class="wa-project-eyebrow">IMPORT Z ASUS-U · KROK 1</span><h1>Analýza existujúceho webu</h1><p class="wa-project-muted">Najprv si pozrieme, z čoho sa web skladá.</p></div>
    </header>
    <section class="wa-project-card">
        <label class="wa-project-field">Web na ASUS-e
            <select x-model="sourceId" :disabled="loading || analyzing" @change="report = null; error = ''">
                <option value="">Vyberte web</option>
                <template x-for="source in sources" :key="source.id"><option :value="source.id" x-text="source.name + ' — ' + source.domain"></option></template>
            </select>
        </label>
        <p class="wa-project-muted">Zoznam obsahuje povolené zdroje na našom serveri. Prístup cez Git alebo FTP doplníme neskôr.</p>
        <button type="button" class="btn wa-project-save" @click="analyze()" :disabled="loading || analyzing || !sourceId" x-text="analyzing ? 'Analyzujem…' : 'Analyzovať web'"></button>
        <p x-show="loading" class="wa-project-muted" role="status">Načítavam dostupné weby…</p>
        <p x-show="analyzing" class="wa-project-muted" role="status">Čítam štruktúru súborov. PHP ani formuláre sa nespúšťajú.</p>
    </section>
    <div x-show="error" class="wa-project-message is-error" role="alert" x-text="error"></div>
    <template x-if="report">
        <div>
            <section class="wa-project-card">
                <div class="wa-domain-list-heading"><h2 x-text="report.name"></h2><span class="wa-project-type-badge" x-text="report.site_type === 'php' ? 'PHP web' : 'Statický web'"></span></div>
                <p><strong x-text="report.domain"></strong><span class="wa-project-muted" x-text="report.aliases.length ? ' · alias: ' + report.aliases.join(', ') : ''"></span></p>
                <p class="wa-project-muted" x-show="report.site_type === 'php'">Web používa PHP. Stránky môžu byť skladané zo spoločnej hlavičky, pätičky a sekcií; nejde o čistý statický HTML web.</p>
                <div class="wa-project-stats">
                    <div><strong x-text="report.pages.length"></strong><span>Stránky na analýzu</span></div>
                    <div><strong x-text="report.sections.length"></strong><span>Sekcie v súboroch</span></div>
                    <div><strong x-text="report.files_count"></strong><span>Súbory</span></div>
                    <div><strong x-text="size(report.bytes)"></strong><span>Veľkosť zdroja</span></div>
                </div>
                <p class="wa-project-muted mt-3">Čas analýzy: <span x-text="new Date(report.analyzed_at).toLocaleString('sk-SK')"></span></p>
            </section>
            <section class="wa-project-card">
                <h2>Stránky a spoločné časti</h2>
                <template x-for="page in report.pages" :key="page.file"><div class="wa-domain-list-item"><span><strong x-text="page.route"></strong><small x-text="page.title || 'Bez názvu'"></small></span><span x-text="page.kind"></span></div></template>
                <p class="wa-project-muted mt-3">Spoločné súbory: <span x-text="report.includes.join(', ') || 'Nezistené'"></span></p>
                <p class="wa-project-muted">Rozpoznané použité sekcie: <span x-text="report.used_sections.length"></span> z <span x-text="report.sections.length"></span>. Určené z textu PHP bez jeho spustenia.</p>
                <div class="wa-project-message"><span x-text="report.used_sections.join(', ') || 'Automatické určenie sekcií nie je dostupné.'"></span></div>
            </section>
            <section class="wa-project-card">
                <h2>Domény a subdomény</h2>
                <p>Hlavná doména: <strong x-text="report.domain"></strong></p>
                <p class="wa-project-muted" x-show="report.aliases.length">Alias <span x-text="report.aliases.join(', ')"></span> používa rovnaký web.</p>
                <p class="wa-project-muted" x-show="!report.subdomains.length">V povolených zdrojoch ASUS-u nie je ďalší samostatný web tejto domény.</p>
                <template x-for="domain in report.subdomains" :key="domain.id"><p x-text="domain.domain"></p></template>
                <p class="wa-project-muted" x-show="report.linked_subdomains_unavailable.length">V obsahu sú odkazy na <strong x-text="report.linked_subdomains_unavailable.join(', ')"></strong>. Tieto subdomény zatiaľ nemajú dostupný zdroj pre import na ASUS-e; samotný odkaz nepotvrdzuje ich umiestnenie.</p>
            </section>
            <section class="wa-project-card">
                <h2>Obrázky, štýly a funkcie</h2>
                <dl class="wa-project-summary"><div><dt>Obrázky a ikony</dt><dd x-text="report.assets.images"></dd></div><div><dt>CSS / JavaScript</dt><dd x-text="report.assets.styles + ' / ' + report.assets.scripts"></dd></div><div><dt>Fonty / PDF</dt><dd x-text="report.assets.fonts + ' / ' + report.assets.documents"></dd></div><div><dt>Formuláre v šablónach</dt><dd x-text="report.forms.length"></dd></div></dl>
                <p class="wa-project-muted">Databáza: <span x-text="report.database === 'possible' ? 'Nájdené možné databázové volania; treba podrobnejšie overenie.' : 'V čítaných zdrojoch sa nenašli databázové volania. Nie je to overenie behu aplikácie.'"></span></p>
                <p class="wa-project-muted" x-show="report.external_asset_hosts.length">Externé závislosti: <span x-text="report.external_asset_hosts.join(', ')"></span>.</p>
                <p class="wa-project-muted" x-show="report.forms.length">Obsluha formulárov sa musí riešiť osobitne. PHP kód ani prístupové údaje sa týmto náhľadom neimportujú.</p>
            </section>
            <section class="wa-project-card">
                <h2>Čo treba overiť pri importe</h2>
                <p class="wa-project-muted">Kontrola odkazov v HTML/PHP šablónach vrátane nepoužitých vzorových sekcií našla <strong x-text="report.missing_files_count"></strong> odkazov na chýbajúce lokálne súbory. Odkazy vo vnútri CSS/JS zatiaľ nekontroluje.</p>
                <template x-for="file in report.missing_files" :key="file"><div class="wa-project-muted" x-text="file"></div></template>
                <p class="wa-project-muted" x-show="report.missing_files_count > report.missing_files.length">Zobrazených je prvých 30 položiek.</p>
                <template x-for="note in report.notes" :key="note"><p class="wa-project-muted" x-text="note"></p></template>
                <div class="wa-project-message is-success" role="status">Analýza je pripravená. Ďalší krok bude vytvorenie pracovnej kópie a prevod obsahu do editorov. Zatiaľ sa nevytvoril projekt ani neupravili súbory webu.</div>
            </section>
        </div>
    </template>
</div>
