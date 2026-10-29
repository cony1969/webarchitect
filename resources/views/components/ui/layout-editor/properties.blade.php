<template x-if="$store.layoutEditor.current">
    <div class="wa-project-properties" x-data="waLayoutProperties" style="padding:20px 0">
        <fieldset :disabled="draft.loading || draft.saving || !draft.loaded">
            <label class="wa-project-field"><span>Názov layoutu <span aria-hidden="true">*</span></span><input type="text" maxlength="160" x-model="draft.form.name" @input="draft.notice = ''" required></label>
            <p class="wa-project-muted mt-3">Verzia <span x-text="draft.revision"></span></p>
            <hr>
            <h3 class="wa-project-properties-title">Vybraný prvok</h3>
            <p x-show="!node" class="wa-project-muted">Vyberte prvok v štruktúre layoutu v strednom paneli.</p>
            <template x-if="node?.type === 'cms-component'"><div>
                <p x-text="store.catalog.components.find(c => c.id === node.attributes.component_id)?.name || 'Komponent'"></p>
                <button type="button" class="btn wa-project-secondary w-100" :aria-pressed="!!node.attributes.full_width" :class="{ 'wa-layout-enabled': node.attributes.full_width }" @click="store.toggleWidth(store.activeKey)">↔ Roztiahnuť na celú šírku rodiča</button>
                <p class="wa-project-muted mt-2">Nastavenie aj vnorený obsah platia iba pre tento kontajner v layoute.</p>
            </div></template>
            <template x-if="node?.type === 'p'"><label class="wa-project-field">Text<textarea rows="4" :value="store.text(store.activeKey)" @input="store.setText(store.activeKey, $event.target.value)"></textarea></label></template>
            <button x-show="node" type="button" class="btn wa-project-secondary mt-3" @click="store.remove(store.activeKey)">Odobrať prvok z layoutu</button>
        </fieldset>
    </div>
</template>
