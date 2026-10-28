<template x-if="$store.projectEditor.active && $store.projectEditor.isDomain">
    <div class="wa-project-properties" x-data="waProjectProperties()">
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
</template>
