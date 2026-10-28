<div class="wa-project-editor" x-data="waProjectForm(tab.id, tab.domainId, 'domain', tab.projectId)">
    <header class="wa-project-toolbar">
        <div>
            <span class="wa-project-eyebrow">EDITOR DOMÉNY</span>
            <h1 x-text="draft.form.name || 'Nová doména'"></h1>
            <div class="wa-project-meta">
                <span class="wa-project-badge" x-text="store.typeName(draft)"></span>
                <span x-text="store.dirty(key) ? 'Neuložené zmeny' : (draft.id ? 'Uložené · verzia ' + draft.revision : 'Nová doména')"></span>
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
            <h2>Základné údaje</h2>
            <div class="wa-project-grid">
                <label class="wa-project-field">Doména / subdoména <span class="wa-required">*</span>
                    <input type="text" maxlength="253" x-model="draft.form.name" autocomplete="off" placeholder="app.priklad.sk" :aria-invalid="!!draft.errors.name">
                </label>
                <label class="wa-project-field">Projekt
                    <input type="text" :value="draft.projectName" readonly>
                </label>
                <label class="wa-project-field wa-project-full">Popis webu
                    <textarea rows="4" maxlength="5000" x-model="draft.form.settings.description" placeholder="Čo má web na tejto doméne robiť?"></textarea>
                </label>
                <label class="wa-project-field">Git repozitár
                    <input type="text" maxlength="500" x-model="draft.form.settings.git_repository" placeholder="https://github.com/organizacia/projekt" autocomplete="off" spellcheck="false">
                    <small>HTTPS alebo SSH adresa bez hesiel a tokenov.</small>
                </label>
                <label class="wa-project-field">Prostredie
                    <select x-model="draft.form.settings.environment">
                        @foreach(config('project-editor.environments') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="wa-project-field">Stav záznamu
                    <select x-model="draft.form.status"><option value="draft">Rozpracovaný</option><option value="archived">Archivovaný</option></select>
                </label>
            </div>
        </section>

        <section class="wa-project-card">
            <h2>Plán vytvorenia webu</h2>
            <p class="wa-project-muted">Základ webu a voľby vytvorenia nastavíte vpravo v paneli Vlastnosti.</p>
            <div class="wa-project-plan">
                <div><span>Základ webu</span><strong x-text="store.typeName(draft)"></strong></div>
                <template x-if="draft.form.settings.site_type === 'laravel13'">
                    <div class="wa-project-plan-options">
                        <div><span>Vytvoriť zo vzoru</span><strong x-text="draft.form.settings.setup.from_template ? store.templateName(draft) : 'Bez vzoru'"></strong></div>
                        <div><span>Prihlasovacia stránka</span><strong x-text="draft.form.settings.setup.login_page ? 'Zahrnutá do plánu' : 'Nezahrnutá'"></strong></div>
                        <div><span>Databáza</span><strong x-text="draft.form.settings.setup.create_database ? store.databaseName(draft) : 'Nevytvárať'"></strong></div>
                    </div>
                </template>
            </div>
            <p class="wa-project-note"><i class="fas fa-info-circle" aria-hidden="true"></i> V tejto etape sa ukladá konfigurácia. Vytvorenie webu, prihlasovacej stránky a databázy bude samostatný ďalší krok.</p>
        </section>
    </fieldset>

    <x-ui.project-editor.copy />
</div>
