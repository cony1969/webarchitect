<div class="wa-project-editor wa-component-editor" x-data="waComponentForm(tab.id)">
    <header class="wa-project-toolbar">
        <div><span class="wa-project-eyebrow">EDITOR KOMPONENTU</span><h1 x-text="draft.form.name || tab.title"></h1><div class="wa-project-meta"><span x-text="store.dirty(key) ? 'Neuložené zmeny' : (draft.loaded ? 'Uložené · verzia ' + draft.revision : '')"></span></div></div>
        <div class="wa-project-actions">
            <button type="button" class="btn wa-project-save" @click="store.save(key)" :disabled="!draft.loaded || draft.loading || draft.saving || draft.shared"><span x-text="draft.saving ? 'Ukladám…' : 'Uložiť zmeny'"></span></button>
            <button type="button" class="btn wa-project-secondary" @click="store.openCopy(key)" :disabled="!draft.loaded || draft.loading || draft.saving">Uložiť ako nový</button>
        </div>
    </header>
    <div x-show="draft.loading" class="wa-project-message" role="status">Načítavam komponent…</div>
    <div x-show="draft.notice" class="wa-project-message is-success" role="status" x-text="draft.notice"></div>
    <div x-show="draft.error" class="wa-project-message is-error" role="alert"><p x-text="draft.error"></p><template x-for="(messages, field) in draft.errors" :key="field"><p x-text="messages.join(' ')"></p></template><button type="button" class="btn wa-project-secondary" @click="store.load(key, true)" :disabled="draft.loading || draft.saving">Načítať uloženú verziu</button></div>
    <div x-show="draft.shared" class="wa-project-message">Toto je spoločná predloha. Upravenú verziu uložte ako nový vlastný komponent.</div>
    <div x-show="draft.loaded">
        <div class="wa-component-switch" role="group" aria-label="Zobrazenie komponentu">
            <button type="button" :aria-pressed="draft.view === 'preview'" @click="draft.view = 'preview'">Náhľad</button>
            <button type="button" :aria-pressed="draft.view === 'json'" @click="draft.view = 'json'">JSON · Ace</button>
            <button type="button" class="wa-component-format" x-show="draft.view === 'json'" @click="store.format(key)" :disabled="draft.saving">Formátovať JSON</button>
        </div>
        <div x-show="draft.codeError" class="wa-project-message is-error" role="alert"><span x-text="draft.codeError"></span> Náhľad zobrazuje poslednú platnú verziu.</div>
        <div x-show="draft.view === 'preview'" class="wa-component-preview">
            <iframe title="Náhľad komponentu" sandbox="" referrerpolicy="no-referrer" :srcdoc="draft.preview"></iframe>
            <p class="wa-project-muted">Prázdny kontajner je označený prerušovaným rámom. Rám ani popis sa do komponentu neukladajú.</p>
        </div>
        <div x-show="draft.view === 'json'" class="wa-component-code"><div x-ref="ace" class="wa-component-ace" aria-label="JSON kód komponentu"></div></div>
    </div>
    <section x-show="draft.copyOpen" x-cloak class="wa-project-card wa-component-copy" aria-label="Uložiť komponent ako nový" @keydown.escape.stop="if (!draft.saving) draft.copyOpen = false">
        <h2>Uložiť ako nový komponent</h2>
        <label class="wa-project-field">Názov nového komponentu<input type="text" maxlength="120" x-model="draft.copyName" :disabled="draft.saving"></label>
        <label class="wa-project-field mt-3">Nový slug<input type="text" maxlength="160" x-model="draft.copySlug" :disabled="draft.saving" spellcheck="false"></label>
        <div class="wa-project-actions mt-3"><button type="button" class="btn wa-project-save" @click="store.save(key, true)" :disabled="draft.saving">Uložiť nový komponent</button><button type="button" class="btn wa-project-secondary" @click="draft.copyOpen = false" :disabled="draft.saving">Zrušiť</button></div>
    </section>
</div>
