<div class="wa-project-editor wa-component-editor" x-data="waComponentForm(tab.id)">
    <header class="wa-project-toolbar">
        <div><span class="wa-project-eyebrow">EDITOR KOMPONENTU</span><h1 x-text="draft.form.name || tab.title"></h1><div class="wa-project-meta"><span x-text="store.dirty(key) ? 'Neuložené zmeny' : (draft.loaded ? 'Uložené · verzia ' + draft.revision : '')"></span></div></div>
        <div class="wa-project-actions">
            <button type="button" class="btn wa-project-save" @click="store.save(key)" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.placement?.saving || draft.shared"><span x-text="draft.saving ? 'Ukladám…' : 'Uložiť zmeny'"></span></button>
            <button type="button" class="btn wa-project-secondary" @click="store.openPlacement(key)" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.placement?.saving">Vložiť do projektu</button>
        </div>
    </header>
    <div x-show="draft.loading" class="wa-project-message" role="status">Načítavam komponent…</div>
    <div x-show="draft.notice" class="wa-project-message is-success" role="status" x-text="draft.notice"></div>
    <div x-show="draft.error" class="wa-project-message is-error" role="alert"><p x-text="draft.error"></p><template x-for="(messages, field) in draft.errors" :key="field"><p x-text="messages.join(' ')"></p></template><button type="button" class="btn wa-project-secondary" @click="store.load(key, true)" :disabled="draft.loading || draft.saving">Načítať uloženú verziu</button></div>
    <div x-show="draft.shared" class="wa-project-message">Toto je spoločná predloha. Vložte ju do projektu a upravujte jeho vlastnú verziu.</div>
    <template x-if="draft.placement">
        <section class="wa-project-card wa-component-copy" aria-label="Vložiť do projektu" x-data="waComponentPlacement(key)">
            <h2>Vložiť do projektu</h2>
            <p class="wa-project-muted">Projekt dostane vlastnú verziu zdroja. Ak ju už má, použije sa jeho upravená verzia. Na stránku sa pridá jej ďalšie použitie.</p>
            <p x-show="draft.placement.loading" role="status">Načítavam projekty a komponenty…</p>
            <fieldset class="wa-project-fields" :disabled="draft.placement.loading || draft.placement.saving">
                <label class="wa-project-field">Zdroj komponentu<select x-ref="sourceId" x-model="draft.placement.sourceId"><template x-for="source in draft.placement.sources" :key="source.id"><option :value="source.id" x-text="source.project_name + ' / ' + source.name"></option></template></select></label>
                <label class="wa-project-field mt-3">Cieľový projekt<select x-ref="projectId" x-model="draft.placement.projectId" @change="store.placementProjectChanged(key)"><template x-for="project in draft.placement.projects" :key="project.id"><option :value="project.id" x-text="project.name"></option></template></select></label>
                <label class="wa-project-field mt-3">Cieľová stránka<select x-ref="pageId" x-model="draft.placement.pageId"><template x-for="page in (draft.placement.projects.find(p => p.id === draft.placement.projectId)?.pages || [])" :key="page.id"><option :value="page.id" x-text="(page.domain_name ? page.domain_name + ' / ' : '') + page.name"></option></template></select></label>
                <p class="wa-project-muted mt-3" x-show="!draft.placement.loading && !draft.placement.pageId">Vo vybranom projekte najprv vytvorte doménu a stránku.</p>
                <div class="wa-project-actions mt-3"><button type="button" class="btn wa-project-save" @click="store.insert(key)" :disabled="!draft.placement.pageId"><span x-text="draft.placement.saving ? 'Vkladám…' : 'Vložiť komponent'"></span></button><button type="button" class="btn wa-project-secondary" @click="store.openPlacement(key)">Obnoviť výber</button><button type="button" class="btn wa-project-secondary" @click="draft.placement = null">Zrušiť</button></div>
            </fieldset>
        </section>
    </template>
    <div x-show="draft.loaded">
        <div class="wa-component-switch" role="group" aria-label="Zobrazenie komponentu">
            <button type="button" :aria-pressed="draft.view === 'preview'" @click="store.changeView(key, 'preview')">Náhľad</button>
            <button type="button" :aria-pressed="draft.view === 'json'" @click="store.changeView(key, 'json')">JSON · Ace</button>
            <button type="button" class="wa-component-format" x-show="draft.view === 'json'" @click="store.format(key)" :disabled="draft.saving">Formátovať JSON</button>
        </div>
        <div x-show="draft.codeError" class="wa-project-message is-error" role="alert"><span x-text="draft.codeError"></span> Náhľad zobrazuje poslednú platnú verziu.</div>
        <div x-show="draft.view === 'preview'" class="wa-component-preview">
            <iframe :data-wa-preview="tab.id" title="Náhľad komponentu" sandbox="" referrerpolicy="no-referrer" :srcdoc="draft.preview"></iframe>
            <p class="wa-project-muted">Prázdny kontajner je označený prerušovaným rámom. Rám ani popis sa do komponentu neukladajú.</p>
        </div>
        <div x-show="draft.view === 'json'" class="wa-component-code"><div x-ref="ace" class="wa-component-ace" aria-label="JSON kód komponentu"></div></div>
    </div>

</div>
