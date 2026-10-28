<template x-if="$store.componentEditor.current">
    <div class="wa-project-properties" x-data="{ get draft() { return $store.componentEditor.current; } }" style="padding:20px 0">
        <fieldset class="wa-project-fields" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.codeError" @input="$store.componentEditor.propertiesChanged($store.componentEditor.activeKey)">
            <label class="wa-project-field">Názov komponentu <span class="wa-required">*</span><input type="text" maxlength="120" x-model="draft.form.name"></label>
            <label class="wa-project-field mt-3">Slug<input type="text" x-model="draft.form.slug" readonly><small class="wa-project-muted">Nový slug môžete zadať cez Uložiť ako nový.</small></label>
            <label class="wa-project-field mt-3">Umiestnenie v knižnici<input type="text" x-model="draft.form.target_path" readonly></label>
            <label class="wa-project-field mt-3">Kategória<input type="text" x-model="draft.form.category" readonly></label>
        </fieldset>
        <div class="wa-component-summary mt-4"><div><span>Verzia</span><strong x-text="draft.revision"></strong></div><div><span>Uloženie</span><strong x-text="$store.componentEditor.dirty($store.componentEditor.activeKey) ? 'Neuložené zmeny' : 'Uložené'"></strong></div></div>
        <p class="wa-project-muted mt-3">Štruktúru, triedy a ďalšie nastavenia upravíte cez JSON v strednej karte.</p>
        <button type="button" class="btn wa-project-secondary" @click="draft.view = 'json'" :disabled="!draft.loaded">Otvoriť JSON v Ace</button>
    </div>
</template>
