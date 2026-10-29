@php
    $userSettings = \DB::table('user_settings')
        ->where('user_id', auth()->id())
        ->pluck('value', 'name')
        ->toArray();

    $savedTabsData = json_decode($userSettings['open_canvas_tabs'] ?? null, true);
    
    $initialTabs = $savedTabsData['tabs'] ?? [
        ['id' => 'tab-1', 'title' => 'Hlavný web - Layout', 'mode' => 'layout', 'route' => null]
    ];
    $initialActiveTab = $savedTabsData['activeTab'] ?? 'tab-1';

    // 3. Výpočet najvyššieho ID pre počítadlo nových tabov
    $highestIdNum = 1;
    foreach ($initialTabs as $t) {
        $num = (int) filter_var($t['id'], FILTER_SANITIZE_NUMBER_INT);
        if ($num > $highestIdNum) {
            $highestIdNum = $num;
        }
    }
@endphp

@props(['slot' => ''])

<div class="canvas-tabs-wrapper"
     style="flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100%; position: relative;"
     x-data="waCanvasTabs(@js($initialTabs), @js($initialActiveTab), @js(csrf_token()))"
     @add-new-page-tab.window="addTab('Stránka', 'page')"
     @add-new-layout-tab.window="addTab('Šablóna', 'layout')"
     @add-new-component-tab.window="addTab('Komponent', 'component')"
     @add-new-tab.window="handleAddNewTab($event)"
     @cms-page-composed.window="pageComposed($event.detail)"
     @cms-page-deleted.window="pageDeleted($event.detail)"
     x-on:component-document-loaded.window="componentLoaded($event.detail)"
     x-on:component-document-saved.window="componentSaved($event.detail)"
     @project-document-saved.window="projectSaved($event.detail)"
     @layout-document-loaded.window="layoutLoaded($event.detail)"
     @layout-document-saved.window="layoutLoaded($event.detail)"
     @project-document-loaded.window="projectLoaded($event.detail)"
     @keydown.window="saveShortcut($event)">
    <div x-show="tabSaveError" class="wa-project-message is-error m-2" role="alert"><span x-text="tabSaveError"></span> <button type="button" @click="persistTabs()">Skúsiť znova</button></div>
    <div class="canvas-tabs-bar" role="tablist" aria-label="Otvorené editory">
        <template x-for="tab in tabs" :key="tab.id">
            <div class="wa-canvas-tab" role="presentation" :data-tab-id="tab.id" @click="setActiveTab(tab.id)" :class="{ 'is-active': activeTab === tab.id }">
                <button type="button" class="wa-canvas-close" :aria-label="'Zavrieť kartu ' + tab.title" @click.stop="closeTab(tab.id, $event)" :disabled="!!saving(tab.id)">×</button>
                <button type="button" class="wa-canvas-label" role="tab" :aria-selected="activeTab === tab.id" :aria-controls="'canvas-' + tab.id" :tabindex="activeTab === tab.id ? 0 : -1" :aria-label="tab.title + (dirty(tab.id) ? ', neuložené zmeny' : '')" @keydown.left.prevent="stepTab(tab.id, -1)" @keydown.right.prevent="stepTab(tab.id, 1)">
                    <span aria-hidden="true" x-text="({project:'📁',domain:'🌐',import:'📥',layout:'📐',component:'🧩',profile:'👤'})[tab.mode] || '📄'"></span>
                    <span x-text="tab.title + (dirty(tab.id) ? ' •' : '')"></span>
                </button>
            </div>
        </template>
        <button type="button" class="wa-canvas-add" aria-label="Nová pracovná plocha" @click="addTab('Nová plocha', 'page')">+</button>
    </div>

    <!-- 2. PLÁTNO (CANVAS) -->
    <div class="canvas-scroll-container p-0" 
         style="flex: 1; overflow: auto; display: flex; align-items: flex-start; justify-content: center; background: #eaedf1;"
         :style="{ background: (['project', 'domain', 'import', 'component', 'layout'].includes(currentMode) || !!pages.current) ? 'var(--wa-surface-base)' : '#eaedf1' }"
         @mousemove="updateInspector($event)"
    >
        <template x-for="(tab, tabIndex) in tabs" :key="tab.id + '-canvas'">
            <div x-show="activeTab === tab.id"
                 :id="'canvas-' + tab.id"
                 class="editor-canvas shadow-sm"
                 :style="{
                     backgroundImage: (['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) ? 'none' : 'linear-gradient(rgba(59, 192, 195, 0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(59, 192, 195, 0.15) 1px, transparent 1px)',
                     backgroundColor: (['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) ? 'var(--wa-surface-base)' : '#ffffff',
                     maxWidth: (['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) ? 'none' : '1200px',
                     minHeight: 'calc(100vh - 190px)',
                     padding: (['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) || tab.route ? '0px' : '30px',
                     marginTop: (['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) ? '0px' : '20px'
                 }"
                 style="width: 100%; background-size: 20px 20px; border-radius: 4px; box-sizing: border-box; position: relative; transition: all 0.3s ease; margin-top: 20px;"
            >
                <template x-if="tab.mode === 'page' && tab.pageId"><x-ui.page-editor.preview /></template>
                <template x-if="tab.mode === 'component'">
                    <x-ui.component-editor.form />
                </template>

                <template x-if="tab.mode === 'layout'"><x-ui.layout-editor.form /></template>
                <template x-if="tab.mode === 'project'">
                    <x-ui.project-editor.form />
                </template>

                <template x-if="tab.mode === 'domain'">
                    <x-ui.domain-editor.form />
                </template>

                <template x-if="tab.mode === 'import'">
                    <x-ui.site-import.form />
                </template>

                <template x-if="tab.mode === 'profile'">
                    <div x-html="document.getElementById('html-profile-tab-template')?.innerHTML" style="width: 100%;"></div>
                </template>

                <template x-if="tab.mode !== 'profile' && !(['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) && tab.route">
                    <iframe :src="tab.route" 
                            style="width: 100%; height: calc(100vh - 250px); border: none; background: transparent; border-radius: 4px;">
                    </iframe>
                </template>

                <template x-if="tab.mode !== 'profile' && !(['project', 'domain', 'import', 'component', 'layout'].includes(tab.mode) || (tab.mode === 'page' && tab.pageId)) && !tab.route">
                    <div>
                        <div style="border: 2px dashed #cbd5e1; border-radius: 6px; padding: 40px; text-align: center; color: #94a3b8; font-family: sans-serif; font-size: 14px;">
                            <p style="margin: 0; font-weight: 600;">Režim: <span x-text="tab.mode.toUpperCase()" style="color: #3bc0c3;"></span> — <span x-text="tab.title + (dirty(tab.id) ? ' •' : '')"></span></p>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #94a3b8;">Pustite podporované prvky sem...</p>
                        </div>

                        <template x-if="tab.id === 'tab-1'">
                            <div style="margin-top: 20px;">{{ $slot }}</div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <template x-teleport="body"><div><x-ui.modal show="pendingClose !== null" title="'Zavrieť kartu'" submit="finishClose(pendingClose)" submit-label="Zahodiť zmeny a zavrieť" :danger="true" cancel="pendingClose = null" modal-id="canvas-close-modal"><p>Táto karta obsahuje neuložené zmeny. Chcete ich zahodiť?</p></x-ui.modal></div></template>
    <template x-teleport="body"><div><x-ui.modal show="!!layouts.current?.reloadOpen" title="'Načítať uložený layout'" submit="layouts.load(layouts.activeKey)" submit-label="Načítať" :danger="true" cancel="layouts.current.reloadOpen = false" modal-id="layout-reload-modal"><p>Neuložené zmeny v tejto karte sa zahodia.</p></x-ui.modal></div></template>
    <!-- 3. INFORMAČNÝ INSPECTOR PANEL -->
    <x-ui.canvas-inspector hovered-element="hoveredElement" />
</div>
