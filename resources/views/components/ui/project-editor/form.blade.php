<div class="wa-project-editor" x-data="waProjectForm(tab.id, tab.projectId)">
    <header class="wa-project-toolbar">
        <div>
            <span class="wa-project-eyebrow">EDITOR PROJEKTU</span>
            <h1 x-text="draft.form.name || 'Nový projekt'"></h1>
            <div class="wa-project-meta">
                <span class="wa-project-badge" x-text="store.typeName(draft)"></span>
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
            <h2>Základné údaje</h2>
            <div class="wa-project-grid">
                <label class="wa-project-field">Názov projektu <span class="wa-required">*</span>
                    <input type="text" maxlength="160" x-model="draft.form.name" @input="updateName()" autocomplete="off" placeholder="Napr. Firemný web" :aria-invalid="!!draft.errors.name">
                </label>
                <label class="wa-project-field">Slug <span class="wa-required">*</span>
                    <input type="text" maxlength="180" x-model="draft.form.slug" @input="draft.autoSlug = false" autocomplete="off" spellcheck="false" placeholder="firemny-web" :aria-invalid="!!draft.errors.slug">
                    <small>Jedinečný názov projektu v rámci vášho účtu.</small>
                </label>
                <label class="wa-project-field wa-project-full">Popis projektu
                    <textarea rows="4" maxlength="5000" x-model="draft.form.settings.description" placeholder="Čo má projekt robiť a pre koho je určený?"></textarea>
                </label>
                <label class="wa-project-field">Hlavná doména
                    <input type="text" maxlength="253" x-model="draft.form.settings.primary_domain" placeholder="projekt.sk" autocomplete="off" spellcheck="false">
                    <small>Plánovaná adresa bez https://. DNS sa nemení.</small>
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
            <h2>Plán vytvorenia projektu</h2>
            <p class="wa-project-muted">Typ projektu a voľby vytvorenia nastavíte vpravo v paneli Vlastnosti.</p>
            <div class="wa-project-plan">
                <div><span>Typ projektu</span><strong x-text="store.typeName(draft)"></strong></div>
                <template x-if="draft.form.settings.project_type === 'laravel13'">
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

    <template x-if="draft.copyOpen">
        <div class="wa-project-modal-backdrop" @keydown.escape.window="if (!draft.saving) draft.copyOpen = false" @click.self="if (!draft.saving) draft.copyOpen = false">
            <section class="wa-project-copy-dialog" role="dialog" aria-modal="true" :aria-labelledby="'project-copy-title-' + key" @keydown.tab="trapCopy($event)">
                <h2 :id="'project-copy-title-' + key">Uložiť ako nový projekt</h2>
                <p class="wa-project-muted">Vznikne nový projekt s aktuálnymi nastaveniami. Pôvodný projekt a jeho domény aj stránky zostanú zachované. Domény a stránky sa do kópie neprenášajú.</p>
                <form @submit.prevent="store.save(key, true)">
                    <fieldset :disabled="draft.saving">
                        <label class="wa-project-field">Názov nového projektu
                            <input type="text" maxlength="160" x-model="draft.copyName" @input="draft.copySlug = waProjectSlug(draft.copyName)" x-init="$nextTick(() => $el.focus())" required>
                        </label>
                        <label class="wa-project-field">Slug nového projektu
                            <input type="text" maxlength="180" x-model="draft.copySlug" required spellcheck="false">
                        </label>
                        <div x-show="draft.error" class="wa-project-message is-error" role="alert"><p x-text="draft.error"></p><template x-for="(messages, field) in draft.errors" :key="field"><p x-text="messages.join(' ')"></p></template></div>
                        <div class="wa-project-actions">
                            <button type="button" class="btn wa-project-secondary" @click="draft.copyOpen = false">Zrušiť</button>
                            <button type="submit" class="btn wa-project-save" x-text="draft.saving ? 'Ukladám…' : 'Uložiť ako nový'"></button>
                        </div>
                    </fieldset>
                </form>
            </section>
        </div>
    </template>
</div>
