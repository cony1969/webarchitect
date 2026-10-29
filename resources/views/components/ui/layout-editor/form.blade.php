<div class="wa-project-editor wa-layout-editor" x-data="waLayoutForm(tab.id, tab)">
    <header class="wa-project-toolbar">
        <div><span class="wa-project-eyebrow">EDITOR LAYOUTU</span><h1 x-text="draft.form.name || 'Nový layout'"></h1><div class="wa-project-meta" x-text="store.dirty(key) ? 'Neuložené zmeny' : (draft.id ? (draft.shared ? 'Spoločná predloha' : 'Uložené · verzia ' + draft.revision) : 'Nový layout')"></div></div>
        <div class="wa-project-actions"><button type="button" class="btn wa-project-save" @click="store.save(key)" :disabled="draft.loading || draft.saving || !draft.loaded" x-text="draft.saving ? 'Ukladám…' : 'Uložiť zmeny'"></button></div>
    </header>
    <p x-show="draft.loading" class="wa-project-message" role="status">Načítavam layout…</p>
    <p x-show="draft.notice" class="wa-project-message is-success" role="status" x-text="draft.notice"></p>
    <div x-show="draft.error" class="wa-project-message is-error" role="alert"><p x-text="draft.error"></p><button x-show="draft.id" type="button" class="btn wa-project-secondary" :disabled="draft.loading || draft.saving" @click="if (store.dirty(key)) draft.reloadOpen = true; else store.load(key)">Načítať uloženú verziu</button></div>
    <p x-show="draft.shared" class="wa-project-message">Pri uložení vznikne vaša vlastná verzia layoutu.</p>
    <fieldset :disabled="draft.loading || draft.saving || !draft.loaded">
        <div class="wa-layout-insert">
            <label class="wa-project-field" x-show="store.containers.length > 1">Zdroj kontajnera<select x-model="draft.componentId"><option value="">Predvolený kontajner</option><template x-for="source in store.containers" :key="source.id"><option :value="source.id" x-text="source.name"></option></template></select></label>
            <div class="wa-project-actions"><button type="button" class="btn wa-project-secondary" @click="store.addContainer(key)" :disabled="!store.containers.length">+ Pridať kontajner</button><button type="button" class="btn wa-project-secondary" @click="store.addContainer(key, true)" :disabled="store.selected(key)?.attributes.content_target !== 'root'">+ Kontajner dovnútra</button><button type="button" class="btn wa-project-secondary" @click="store.addText(key)" :disabled="store.selected(key)?.attributes.content_target !== 'root'">+ Text dovnútra</button></div>
        </div>
        <p x-show="store.catalogError" class="wa-project-message is-error" role="alert"><span x-text="store.catalogError"></span> <button type="button" @click="store.loadCatalog()">Skúsiť znova</button></p>
        <section class="wa-project-card wa-layout-structure" aria-label="Štruktúra layoutu"><h2>Štruktúra layoutu</h2><p class="wa-project-muted" x-show="!draft.form.document.nodes.length">Pridajte Kontajner. Vybranému prvku môžete vložiť obsah a upraviť vlastnosti v pravom paneli.</p><template x-for="row in store.rows(key)" :key="row.id"><button type="button" class="wa-layout-node" :aria-pressed="draft.selectedId === row.id" :style="{ paddingLeft: (12 + row.depth * 18) + 'px' }" @click="store.select(key, row.id)"><span aria-hidden="true">↳</span> <span x-text="row.label"></span></button></template></section>
    </fieldset>
    <p x-show="draft.previewError" class="wa-project-message is-error" role="alert"><span x-text="draft.previewError"></span> Náhľad zobrazuje poslednú platnú verziu.</p>
    <div class="wa-component-preview"><iframe :data-wa-preview="tab.id" x-ref="preview" @load="syncSelection()" title="Náhľad layoutu" sandbox="allow-scripts" referrerpolicy="no-referrer" :srcdoc="draft.preview"></iframe></div>
    <p class="wa-project-muted mt-2">Rámy, popisy a odstupy na zvýraznenie vnorených kontajnerov sú pomôckou editora. Do výsledného webu sa nepridávajú.</p>
</div>
