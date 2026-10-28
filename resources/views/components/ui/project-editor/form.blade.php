<div class="wa-project-editor" x-data="waProjectForm(tab.id, tab.projectId)">
    <header class="wa-project-toolbar">
        <div>
            <span class="wa-project-eyebrow">EDITOR PROJEKTU</span>
            <h1 x-text="draft.form.name || 'Nový projekt'"></h1>
            <div class="wa-project-meta">
                <span x-text="store.dirty(key) ? 'Neuložené zmeny' : (draft.id ? 'Uložené · verzia ' + draft.revision : 'Nový projekt')"></span>
            </div>
        </div>
        <div class="wa-project-actions">
            <button type="button" class="btn wa-project-save" @click="store.save(key)" :disabled="draft.loading || draft.saving || !draft.loaded"><span x-text="draft.saving ? 'Ukladám…' : 'Uložiť zmeny'"></span></button>
            <button type="button" class="btn wa-project-secondary" @click="store.openCopy(key)" :disabled="draft.loading || draft.saving || !draft.loaded">Uložiť ako nový</button>
        </div>
    </header>

    <div x-show="draft.loading" class="wa-project-message" role="status">Načítavam projekt…</div>
    <div x-show="draft.notice" class="wa-project-message is-success" role="status" x-text="draft.notice"></div>
    <div x-show="draft.error && !draft.copyOpen" class="wa-project-message is-error" role="alert">
        <p x-text="draft.error"></p>
        <template x-for="(messages, field) in draft.errors" :key="field"><p x-text="messages.join(' ')"></p></template>
        <button type="button" class="btn wa-project-secondary" x-show="draft.id" @click="store.load(key, true)" :disabled="draft.loading || draft.saving">Načítať uloženú verziu</button>
    </div>

    <fieldset :disabled="draft.loading || draft.saving || !draft.loaded" class="wa-project-fields">
        <section class="wa-project-card">
            <h2>Názov projektu</h2>
            <label class="wa-project-field">Názov projektu <span class="wa-required">*</span>
                <input type="text" maxlength="160" x-model="draft.form.name" autocomplete="off" placeholder="Napr. Moja firma" :aria-invalid="!!draft.errors.name">
            </label>
        </section>
    </fieldset>
    <section class="wa-project-card">
        <h2>Prehľad projektu</h2>
        <div class="wa-project-stats">
            <div><strong x-text="draft.overview.domains_count || 0"></strong><span>Domény / subdomény</span></div>
            <div><strong x-text="draft.overview.pages_count || 0"></strong><span>Stránky</span></div>
            <div><strong x-text="draft.overview.components_count || 0"></strong><span>Komponenty</span></div>
            <div><strong x-text="draft.overview.sections_count || 0"></strong><span>Sekcie</span></div>
        </div>
        <p class="wa-project-muted mt-3">Počty zahŕňajú iba položky priradené tomuto projektu.</p>
        <p class="wa-project-muted" x-show="draft.overview.updated_at">Posledná úprava: <span x-text="draft.overview.updated_at ? new Date(draft.overview.updated_at).toLocaleString('sk-SK') : ''"></span></p>
    </section>
    <section class="wa-project-card">
        <div class="wa-domain-list-heading"><h2>Domény a subdomény</h2><button type="button" class="btn wa-project-secondary" :disabled="!draft.id || draft.saving" @click="$dispatch('add-new-tab', { title: 'Nová doména', mode: 'domain', projectId: draft.id, projectName: draft.form.name })">Pridať doménu</button></div>
        <p class="wa-project-muted">Každá doména a subdoména má vlastný základ webu a vlastné nastavenia.</p>
        <p class="wa-project-muted" x-show="!draft.id">Najprv uložte projekt a potom pridajte doménu.</p>
        <template x-for="domain in (draft.overview.domains || [])" :key="domain.id">
            <button type="button" class="wa-domain-list-item" @click="$dispatch('add-new-tab', { title: domain.name, mode: 'domain', domainId: domain.id, projectId: draft.id, projectName: draft.form.name })">
                <span><strong x-text="domain.name"></strong><small x-text="window.waProjectEditorConfig.types[domain.site_type] || domain.site_type"></small></span>
                <span>Počet stránok: <span x-text="domain.pages_count"></span> <i class="fas fa-chevron-right" aria-hidden="true"></i></span>
            </button>
        </template>
        <p class="wa-project-muted" x-show="draft.id && !(draft.overview.domains || []).length">Projekt zatiaľ nemá doménu ani subdoménu.</p>
    </section>
    @if(in_array((int) auth()->id(), config('site-import.user_ids', []), true))
        <section class="wa-project-card"><h2>Existujúci web</h2><p class="wa-project-muted">Pred načítaním webu si pozrite jeho štruktúru a dostupné subdomény.</p><button type="button" class="btn wa-project-secondary" @click="$dispatch('add-new-tab', { title: 'Import z ASUS-u', mode: 'import' })">Načítať web z ASUS-u</button></section>
    @endif
    <x-ui.project-editor.copy />
</div>
