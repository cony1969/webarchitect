<template x-if="$store.componentEditor.current">
    <div class="wa-project-properties wa-component-properties" x-data="waComponentProperties()" style="padding:12px 0 20px">
        <div class="wa-component-property-tabs"><x-ui.tabs :tabs="[['id'=>'basic','title'=>'Základné nastavenia'],['id'=>'bootstrap','title'=>'Bootstrap'],['id'=>'css','title'=>'CSS']]" active-var="ui.tab" change-handler="changePropertyTab" accessible-prefix="component-property" /></div>
        <div id="component-property-basic" role="tabpanel" aria-labelledby="component-property-tab-basic" x-show="ui.tab === 'basic'">
            <fieldset class="wa-project-fields" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.codeError" @input="store.propertiesChanged(key)">
                <label class="wa-project-field">Názov komponentu <span class="wa-required">*</span><input type="text" maxlength="120" x-model="draft.form.name"></label>
                <label class="wa-project-field mt-3">Slug<input type="text" x-model="draft.form.slug" readonly><small class="wa-project-muted">Slug označuje túto definíciu komponentu.</small></label>
                <label class="wa-project-field mt-3">Umiestnenie<input type="text" x-model="draft.form.target_path" readonly></label>
                <label class="wa-project-field mt-3">Kategória<input type="text" x-model="draft.form.category" readonly></label>
            </fieldset>
            <p class="wa-project-note" x-show="draft.projectId">Vlastná verzia projektu: <strong x-text="draft.projectName"></strong></p>
            <div class="wa-component-summary mt-4"><div><span>Verzia</span><strong x-text="draft.revision"></strong></div><div><span>Uloženie</span><strong x-text="store.dirty(key) ? 'Neuložené zmeny' : 'Uložené'"></strong></div></div>
            <p class="wa-project-muted mt-3">Bootstrap triedy a CSS upravíte v ďalších záložkách. Štruktúru komponentu môžete upraviť cez JSON v strednej karte.</p>
            <button type="button" class="btn wa-project-secondary" @click="draft.view = 'json'" :disabled="!draft.loaded">Otvoriť JSON v Ace</button>
        </div>
        <label class="wa-project-field wa-property-search" x-show="ui.tab !== 'basic'">Vyhľadať vlastnosť
            <input type="search" x-model="ui.search" @keydown.escape.stop="ui.search = ''" placeholder="napr. odsadenie / padding" aria-label="Vyhľadať vlastnosť po slovensky alebo anglicky" autocomplete="off" spellcheck="false">
        </label>
        <div x-show="ui.tab !== 'basic'" class="wa-control-target">
            <label class="wa-project-field" x-show="ui.rootOptions.length > 1">Prvok komponentu<select x-model.number="ui.rootIndex" @change="store.selectPropertyRoot(key)"><template x-for="root in ui.rootOptions" :key="root.index"><option :value="root.index" x-text="root.label"></option></template></select></label>
            <p class="wa-project-muted" x-show="ui.rootOptions.length === 1">Nastavenia koreňového prvku komponentu.</p>
            <p class="wa-project-message" x-show="!ui.editable && draft.loaded">Pri odkaze na komponent otvorte jeho vlastnú definíciu v editore.</p>
        </div>
        <div id="component-property-bootstrap" role="tabpanel" aria-labelledby="component-property-tab-bootstrap" x-show="ui.tab === 'bootstrap'">
            <fieldset class="wa-project-fields" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.codeError || !ui.editable">
                <label class="wa-project-field mb-3">Šírka obrazovky<select x-model="ui.breakpoint"><template x-for="(label, value) in config.breakpoints" :key="value"><option :value="value" x-text="label"></option></template></select></label>
                <template x-for="field in fields" :key="field.id">
                    <label class="wa-project-field mb-3" x-data="waBootstrapField(field)"><span x-text="field.label"></span><select x-ref="field" :aria-label="field.label"></select></label>
                </template>
                <p class="wa-project-muted" role="status" x-show="ui.search.trim() && !fields.length">Žiadna vlastnosť nezodpovedá vyhľadávaniu.</p>
                <div class="wa-control-add-setting" x-show="!ui.search.trim()">
                    <label class="wa-project-field">Ďalšie nastavenie<select x-model="extraField"><option value="">Vyberte nastavenie…</option><template x-for="field in config.fields.filter(f => !f.common && !ui.extraFields.includes(f.id))" :key="field.id"><option :value="field.id" x-text="field.label"></option></template></select></label>
                    <button type="button" class="wa-control-plus" aria-label="Pridať Bootstrap nastavenie" @click="addField()" :disabled="!extraField">+</button>
                </div>
                <button type="button" class="wa-control-link" x-show="!ui.search.trim()" @click="ui.allFields = !ui.allFields" x-text="ui.allFields ? 'Zobraziť bežné nastavenia' : 'Zobraziť všetky nastavenia'"></button>
                <p class="wa-project-muted wa-control-class-list">Triedy: <span x-text="ui.classTokens.join(' ') || 'Žiadne'"></span></p>
                <button type="button" class="wa-control-link" @click="ui.allClasses = !ui.allClasses" x-text="ui.allClasses ? 'Skryť výber všetkých tried' : 'Pridať / zobraziť všetky triedy'"></button>
                <template x-if="ui.allClasses"><label class="wa-project-field mt-2" x-data="waAllBootstrapClasses()">Bootstrap a vlastné triedy<select x-ref="classes" multiple aria-label="Všetky Bootstrap triedy"></select></label></template>
            </fieldset>
        </div>
        <div id="component-property-css" role="tabpanel" aria-labelledby="component-property-tab-css" x-show="ui.tab === 'css'">
            <fieldset class="wa-project-fields" :disabled="!draft.loaded || draft.loading || draft.saving || !!draft.codeError || !ui.editable">
                <p class="wa-project-muted" role="status" x-show="ui.search.trim()" x-text="cssResults.length ? 'Filter Select2: ' + cssResults.length + ' vlastností' : 'Žiadna vlastnosť nezodpovedá filtru.'"></p>
                <div class="wa-css-heading"><strong>CSS vlastnosti</strong><button type="button" class="wa-control-plus" aria-label="Pridať CSS riadok" @click="addStyleRow()">+</button></div>
                <div class="wa-css-row-labels" aria-hidden="true"><span>Vlastnosť</span><span>Hodnota</span></div>
                <template x-for="(row, index) in ui.styleRows" :key="row.id">
                    <div class="wa-css-row" :data-css-row="row.id" @input="store.stylesChanged(key)">
                        <span class="wa-css-property" x-data="waCssProperty(row)"><select data-css-property x-ref="property" :aria-label="'CSS vlastnosť ' + (index + 1)"></select></span>
                        <span aria-hidden="true">:</span>
                        <div class="wa-css-value">
                            <input type="text" data-css-value x-show="!colorOnly(row)" :aria-label="'CSS hodnota ' + (index + 1)" x-model="row.value" maxlength="20000" placeholder="napr. 20px" spellcheck="false" autocomplete="off">
                            <div class="wa-css-colors" :class="{'is-color-only': colorOnly(row)}" x-show="colorsFor(row).length">
                                <template x-for="(token, colorIndex) in colorsFor(row)" :key="colorIndex">
                                    <span x-data="waColorPicker({get: () => colorsFor(row)[colorIndex]?.color || '', set: value => setRowColor(row, colorIndex, value), disabled: () => !draft?.loaded || draft.loading || draft.saving || !!draft.codeError || !ui.editable, showValue: false, label: () => 'Vybrať farbu pre ' + row.name + ' · ' + (colorIndex + 1)})"><button type="button" class="wa-color-trigger" x-ref="color" :aria-label="'Vybrať farbu pre ' + row.name + ' · ' + (colorIndex + 1)"><span aria-hidden="true">▾</span></button></span>
                                </template>
                            </div>
                        </div>
                        <button type="button" :aria-label="'Odstrániť CSS riadok ' + (index + 1)" @click="removeStyleRow(index)" class="wa-css-remove">×</button>
                    </div>
                </template>

                <p class="wa-project-note">Riadok bez názvu vlastnosti sa neuloží. CSS sa pridá do štýlu vybraného prvku.</p>
            </fieldset>
        </div>
    </div>
</template>
