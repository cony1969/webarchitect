<template x-if="$store.projectEditor.active && $store.projectEditor.isDomain">
    <div class="wa-project-properties" x-data="waProjectProperties()">
        <x-ui.tabs :tabs="[['id'=>'general','title'=>'Všeobecné'],['id'=>'bootstrap','title'=>'Bootstrap']]" active-var="propertyTab" change-handler="changePropertyTab" accessible-prefix="domain-property" />
        <div id="domain-property-general" role="tabpanel" aria-labelledby="domain-property-tab-general" x-show="propertyTab === 'general'">
        <fieldset :disabled="$store.projectEditor.current.loading || $store.projectEditor.current.saving || !$store.projectEditor.current.loaded">
            <label class="wa-project-field" for="wa-project-type">Základ webu
                <select id="wa-project-type" x-ref="projectType" aria-label="Základ webu">
                    @foreach(config('domain-editor.types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <template x-if="$store.projectEditor.current.form.settings.site_type === 'laravel13'">
                <div class="wa-project-laravel-options">
                    <h3>Laravel 13</h3>
                    <label class="wa-project-check"><input type="checkbox" x-model="$store.projectEditor.current.form.settings.setup.from_template"><span>Vytvoriť zo vzoru</span></label>
                    <label class="wa-project-field" x-show="$store.projectEditor.current.form.settings.setup.from_template">Vzor projektu
                        <select x-model="$store.projectEditor.current.form.settings.setup.template">
                            @foreach(config('domain-editor.templates') as $value => $template)<option value="{{ $value }}">{{ $template['name'] }}</option>@endforeach
                        </select>
                    </label>
                    <label class="wa-project-check"><input type="checkbox" x-model="$store.projectEditor.current.form.settings.setup.login_page"><span>Vytvoriť prihlasovaciu stránku</span></label>
                    <label class="wa-project-check"><input type="checkbox" x-model="$store.projectEditor.current.form.settings.setup.create_database"><span>Vytvoriť databázu</span></label>
                    <label class="wa-project-field" x-show="$store.projectEditor.current.form.settings.setup.create_database">Databázový systém
                        <select x-model="$store.projectEditor.current.form.settings.setup.database_engine">
                            @foreach(config('domain-editor.databases') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </template>
            <p class="wa-project-note">Voľby sa uložia pre túto doménu. Vytvorenie webu bude nasledovať v ďalšom kroku.</p>
            <div class="wa-project-properties-info"><span>Verzia</span><strong x-text="$store.projectEditor.current.id ? $store.projectEditor.current.revision : 'Nová doména'"></strong></div>
            <div class="wa-project-properties-info"><span>Uloženie</span><strong x-text="$store.projectEditor.dirty($store.projectEditor.activeKey) ? 'Neuložené zmeny' : ($store.projectEditor.current.id ? 'Uložené' : 'Čaká na uloženie')"></strong></div>
        </fieldset>
        </div>
        <div id="domain-property-bootstrap" role="tabpanel" aria-labelledby="domain-property-tab-bootstrap" x-show="propertyTab === 'bootstrap'">
            <fieldset :disabled="!draft.loaded || draft.loading || draft.saving">
                <h3 class="wa-project-properties-title">Farby Bootstrapu</h3>
                @foreach(config('domain-editor.bootstrap_colors') as $name => $label)
                <div class="wa-domain-color">
                    <label for="wa-domain-color-{{ $name }}">{{ $label }} <code>{{ $name }}</code></label>
                    <div class="wa-domain-color-value">
                        <span x-data="waColorPicker({get: () => draft?.form.settings?.bootstrap?.colors?.{{ $name }}, set: value => draft.form.settings.bootstrap.colors.{{ $name }} = value, disabled: () => draft?.kind !== 'domain' || !draft.loaded || draft.loading || draft.saving, alpha: false, label: 'Vybrať farbu {{ $name }}'})"><button type="button" class="wa-color-trigger" x-ref="color" aria-label="Vybrať farbu {{ $name }}"><span aria-hidden="true">▾</span></button></span>
                        <input type="text" id="wa-domain-color-{{ $name }}" x-model="draft.form.settings.bootstrap.colors.{{ $name }}" maxlength="7" spellcheck="false" autocomplete="off" aria-label="Farba {{ $name }}" placeholder="#0d6efd">
                    </div>
                    <template x-if="draft.errors['settings.bootstrap.colors.{{ $name }}']"><p class="wa-color-error" role="alert" x-text="draft.errors['settings.bootstrap.colors.{{ $name }}'].join(' ')"></p></template>
                </div>
                @endforeach
                <button type="button" class="wa-control-link" @click="resetBootstrapColors()">Obnoviť predvolené farby</button>
                <p class="wa-project-note">Paleta sa uloží tlačidlom Uložiť zmeny pre túto doménu / subdoménu a použije sa v náhľadoch jej stránok.</p>
            </fieldset>
        </div>
    </div>
</template>
