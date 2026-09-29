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

<script>
    // Bezpečne prenesieme dáta z DB do localStorage ešte pred štartom Alpine
    localStorage.setItem('open_canvas_tabs', JSON.stringify(@js($initialTabs)));
    localStorage.setItem('active_canvas_tab', '{{ $initialActiveTab }}');
</script>

@props(['slot' => ''])

<div class="canvas-tabs-wrapper" 
     style="flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100%; position: relative;"
     x-data="{
         tabs: JSON.parse(localStorage.getItem('open_canvas_tabs')) || [
             { id: 'tab-1', title: 'Hlavný web - Layout', mode: 'layout', route: null }
         ],
         activeTab: localStorage.getItem('active_canvas_tab') || 'tab-1',
         tabCounter: {{ $highestIdNum }},
         hoveredElement: 'Pracovná plocha (Canvas)',

         // ODSTRÁNILI SME INIT() A WATCHERY, KTORÉ SPÔSOBOVALI ZACYKLENIE

         persistTabsToDb() {
             // Spustí sa asynchrónne na pozadí iba pri reálnej akcii používateľa
             fetch('{{ route('profile.save-tabs') }}', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': '{{ csrf_token() }}'
                 },
                 body: JSON.stringify({
                     tabs: this.tabs,
                     activeTab: this.activeTab
                 })
             });
         },

         get currentMode() {
             let active = this.tabs.find(t => t.id === this.activeTab);
             return active ? active.mode : 'page';
         },

         setActiveTab(tabId) {
             this.activeTab = tabId;
             this.persistTabs();
         },

         buildContentKey(mode, route, title, detail = null) {
             if (mode === 'profile') {
                 return 'profile';
             }

             if (mode === 'page') {
                 const projectKey = detail && detail.project
                     ? (detail.project.id || detail.project.name || 'project')
                     : 'project';
                 const domainKey = detail && detail.domain
                     ? (detail.domain.id || detail.domain.name || 'domain')
                     : 'domain';
                 const pageKey = detail && detail.page
                     ? (detail.page.id || detail.page.route || detail.page.url || detail.page.path || detail.page.name || title)
                     : title;

                 return 'page:' + projectKey + '|' + domainKey + '|' + pageKey;
             }

             if (route) {
                 return 'route:' + route;
             }
             if (detail && detail.page && detail.page.id) {
                 return 'page:' + detail.page.id;
             }
             return 'mode:' + mode + '|title:' + title;
         },

         addTab(title = 'Nová stránka', mode = 'page', appendNumber = true, route = null, detail = null) {
             if (mode === 'profile') {
                 route = null;
             }

             const contentKey = this.buildContentKey(mode, route, title, detail);

             const existingTab = this.tabs.find((t) =>
                 t.contentKey === contentKey ||
                 (mode === 'profile' && t.mode === 'profile') ||
                 (route && t.route === route)
             );

             if (existingTab) {
                 this.activeTab = existingTab.id;
                 this.persistTabs();
                 return;
             }

             this.tabCounter = Math.max(this.tabCounter || 0, 1) + 1;
             const newId = 'tab-' + Date.now() + '-' + Math.floor(Math.random() * 1000);

             const newTabObj = {
                 id: newId,
                 title: appendNumber ? (title + ' #' + this.tabCounter) : title,
                 mode: mode,
                 route: route,
                 contentKey: contentKey
             };

             this.tabs = [...this.tabs, newTabObj];
             this.activeTab = newId;
             this.$nextTick(() => this.persistTabs());
         },

         handleAddNewTab(event) {
             const detail = event && event.detail ? event.detail : {};
             const title = detail && detail.title ? detail.title : 'Nová stránka';
             const mode = detail && (detail.mode || detail.type) ? (detail.mode || detail.type) : 'page';
             const route = detail && (detail.route || (detail.page && (detail.page.route || detail.page.url)))
                 ? (detail.route || (detail.page && (detail.page.route || detail.page.url)))
                 : null;

             this.addTab(title, mode, false, route, detail);
         },


closeTab(tabId, e) {
    e.stopPropagation();
    if (this.tabs.length <= 1) {
        alert('Editor musí mať otvorenú aspoň jednu pracovnú plochu.');
        return;
    }
    let index = this.tabs.findIndex(t => t.id === tabId);
    this.tabs = this.tabs.filter(t => t.id !== tabId);

    if (this.activeTab === tabId) {
        let nextActiveIndex = index >= this.tabs.length ? this.tabs.length - 1 : index;
        this.activeTab = this.tabs[nextActiveIndex].id;
    }

    this.persistTabs();
},

         // Namiesto persistTabsToDb() to premenujte na persistTabs()
         persistTabs() {
             // 1. Uložíme lokálne do prehliadača
             localStorage.setItem('open_canvas_tabs', JSON.stringify(this.tabs));
             localStorage.setItem('active_canvas_tab', this.activeTab);

             // 2. Odošleme asynchrónne na backend do DB
             fetch('{{ route('profile.save-tabs') }}', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': '{{ csrf_token() }}'
                 },
                 body: JSON.stringify({
                     tabs: this.tabs,
                     activeTab: this.activeTab
                 })
             });
         },

         updateInspector(e) {
             let target = e.target;
             if (!target) return;

             if (target.id === 'web-architect-canvas' || target.classList.contains('editor-canvas')) {
                 this.hoveredElement = 'Pracovná plocha (Canvas)';
                 return;
             }

             let label = target.getAttribute('data-name') || target.getAttribute('data-id') || target.tagName.toLowerCase();
             this.hoveredElement = label.toUpperCase();
         }


     }"
     @add-new-page-tab.window="addTab('Stránka', 'page')"
     @add-new-layout-tab.window="addTab('Šablóna', 'layout')"
     @add-new-component-tab.window="addTab('Komponent', 'component')"
    @add-new-tab.window="handleAddNewTab($event)"
>
    <!-- 1. LIŠTA TABS -->
    <div class="canvas-tabs-bar" style="background: #242936; padding: 0px; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid #4e566d; flex: 0 0 auto;">
        <template x-for="(tab, tabIndex) in tabs" :key="(tab.id || 'tab') + '-' + tabIndex">
						<!-- Pôvodne tam bolo @click="activeTab = tab.id" -->
						<div @click="setActiveTab(tab.id)"
								:style="{
										color: activeTab === tab.id ? '#fff' : '#8a94ad',
                     borderBottom: activeTab === tab.id ? '2px solid #3bc0c3' : '2px solid transparent',
                     fontWeight: activeTab === tab.id ? '600' : '400'
                 }"
                 style="padding: 8px 0 8px 15px; margin-bottom: -2px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-size: 12px; transition: all 0.2s ease; user-select: none; border-radius: 0;"
                 @mouseover="if(activeTab !== tab.id) { $el.style.color='#fff' }"
                 @mouseout="if(activeTab !== tab.id) { $el.style.color='#8a94ad' }"
            >
                <x-ui.button 
                    style="color: #ef4444; font-size: 14px; width: 14px; height: 14px; border-radius: 3px;"
                    click="closeTab(tab.id, $event)"
                    @mouseover="$el.style.background='rgba(239, 68, 68, 0.2)'"
                    @mouseout="$el.style.background='transparent'"
                >
                    &times;
                </x-ui.button>
                <span x-text="tab.mode === 'layout' ? '📐 ' : (tab.mode === 'component' ? '🧩 ' : (tab.mode === 'profile' ? '👤 ' : '📄 '))"></span>
                <span x-text="tab.title"></span>
                
            </div>
        </template>

        <x-ui.button 
            style="color: #3bc0c3; font-size: 14px; padding: 8px 10px; margin-bottom: -2px;"
            click="addTab('Nová plocha', 'page')"
            @mouseover="$el.style.color='#fff'"
            @mouseout="$el.style.color='#3bc0c3'"
        >
            +
        </x-ui.button>
    </div>

    <!-- 2. PLÁTNO (CANVAS) -->
    <div class="canvas-scroll-container p-0" 
         style="flex: 1; overflow: auto; display: flex; align-items: flex-start; justify-content: center; background: #eaedf1;"
         @mousemove="updateInspector($event)"
    >
        <template x-for="(tab, tabIndex) in tabs" :key="(tab.id || 'canvas') + '-canvas-' + tabIndex">
            <div x-show="activeTab === tab.id"
                 :id="'canvas-' + tab.id"
                 class="editor-canvas shadow-sm"
                 :style="{
                     backgroundImage: 'linear-gradient(rgba(59, 192, 195, 0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(59, 192, 195, 0.15) 1px, transparent 1px)',
                     backgroundColor: '#ffffff',
                     maxWidth: tab.mode === 'component' ? '500px' : '1200px',
                     minHeight: tab.mode === 'component' ? '300px' : 'calc(100vh - 190px)',
                     padding: tab.route ? '0px' : '30px'
                 }"
                 style="width: 100%; background-size: 20px 20px; border-radius: 4px; box-sizing: border-box; position: relative; transition: all 0.3s ease; margin-top: 20px;"
            >
                <template x-if="tab.mode === 'profile'">
                    <div x-html="document.getElementById('html-profile-tab-template')?.innerHTML" style="width: 100%;"></div>
                </template>

                <template x-if="tab.mode !== 'profile' && tab.route">
                    <iframe :src="tab.route" 
                            style="width: 100%; height: calc(100vh - 250px); border: none; background: transparent; border-radius: 4px;">
                    </iframe>
                </template>

                <template x-if="tab.mode !== 'profile' && !tab.route">
                    <div>
                        <div style="border: 2px dashed #cbd5e1; border-radius: 6px; padding: 40px; text-align: center; color: #94a3b8; font-family: sans-serif; font-size: 14px;">
                            <p style="margin: 0; font-weight: 600;">Režim: <span x-text="tab.mode.toUpperCase()" style="color: #3bc0c3;"></span> — <span x-text="tab.title"></span></p>
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

    <!-- 3. INFORMAČNÝ INSPECTOR PANEL -->
    <x-ui.canvas-inspector hovered-element="hoveredElement" />
</div>
