@props(['app' => [], 'layout' => [], 'components' => []])

@php
    $loadedComponents = app(\App\Services\Cms\ComponentLibrary::class)->catalog(auth()->user(), true);

    $userSettings = [];
    if (auth()->check()) {
        $userSettings = \DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->whereIn('name', ['sidebar_open_category', 'sidebar_components_import_context'])
            ->pluck('value', 'name')
            ->toArray();
    }
    
    // Načítame uloženú kategóriu primárne z user_settings, fallback session
    $savedCategory = (string) ($userSettings['sidebar_open_category'] ?? session('sidebar_open_category', ''));
    $savedImportContext = [];
    if (!empty($userSettings['sidebar_components_import_context'])) {
        $decodedImportContext = json_decode((string) $userSettings['sidebar_components_import_context'], true);
        if (is_array($decodedImportContext)) {
            $savedImportContext = $decodedImportContext;
        }
    }
@endphp

<div class="tab-content" 
     style="color: #fff; font-size: 13px;"
     x-data="{
         catalog: @js($loadedComponents),
         itemKey(tab, category, item) { return tab + '|' + category + '|' + item.id + '|' + item.name; },
         openCategory: @js($savedCategory),
         importContext: @js($savedImportContext),
         selectedItemKey: @js($savedImportContext['itemKey'] ?? ''),

         slugify(value) {
             return String(value || '')
                 .toLowerCase()
                 .normalize('NFD')
                 .replace(/[\u0300-\u036f]/g, '')
                 .replace(/[^a-z0-9]+/g, '-')
                 .replace(/^-+|-+$/g, '');
         },

         toSafePath(path) {
             return String(path || '')
                 .split('/')
                 .map((segment) => this.slugify(segment))
                 .filter((segment) => segment !== '')
                 .join('/');
         },

         selectImportTarget(tabKey, categoryName, item) {
             const itemName = item && item.name ? item.name : 'item';
             const itemId = item && item.id ? item.id : '';
             const itemKey = tabKey + '|' + categoryName + '|' + itemId + '|' + itemName;
             this.selectedItemKey = itemKey;

             const itemPathRaw = item && item.path ? String(item.path) : '';
             const targetPath = itemPathRaw !== ''
                 ? this.toSafePath(itemPathRaw)
                 : [this.slugify(tabKey), this.slugify(categoryName), this.slugify(itemName)].filter((s) => s !== '').join('/');

             this.importContext = {
                 tab: tabKey,
                 category: categoryName,
                 itemId: itemId,
                 itemName: itemName,
                 itemKey: itemKey,
                 targetPath: targetPath !== '' ? targetPath : this.slugify(tabKey),
             };

             window.sidebarComponentsImportContext = this.importContext;

             fetch('/sidebar/save-setting', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                 body: JSON.stringify({ name: 'sidebar_components_import_context', value: this.importContext })
             });
         },

         openImportEditor(tabKey, categoryName, item) {
             this.selectImportTarget(tabKey, categoryName, item || {});

             const itemName = item && item.name ? item.name : 'Komponent';
             const itemPath = item && item.path ? String(item.path) : '';
             const pathParts = itemPath.split('/').filter((part) => part !== '');
             const fileName = pathParts.length > 0 ? pathParts[pathParts.length - 1] : '';
             const fileSlug = this.slugify(fileName.replace(/\.json$/i, ''));
             const inferredSlug = fileSlug !== '' ? fileSlug : this.slugify(itemName);

             window.dispatchEvent(new CustomEvent(item.placeholder ? 'open-modal-import' : 'add-new-tab', {
                 detail: {
                     mode: 'component', title: itemName, componentPath: itemPath,
                     tab: tabKey,
                     category: categoryName,
                     targetPath: this.importContext && this.importContext.targetPath ? this.importContext.targetPath : this.slugify(tabKey),
                     name: itemName,
                     slug: inferredSlug,
                     itemPath: itemPath,
                     loadFromJson: true,
                 }
             }));
         },

         toggleCategory(catName, tabKey, categoryName) {
             // Akordeón: ak kliknem na otvorenú, zatvorí sa. Inak otvorí novú.
             this.openCategory = (this.openCategory === catName) ? '' : catName;

             if (this.openCategory !== '') {
                 const targetPath = [this.slugify(tabKey), this.slugify(categoryName)].filter((s) => s !== '').join('/');
                 this.importContext = {
                     tab: tabKey,
                     category: categoryName,
                     itemId: '',
                     itemName: '',
                     itemKey: '',
                     targetPath: targetPath !== '' ? targetPath : this.slugify(tabKey),
                 };
                 window.sidebarComponentsImportContext = this.importContext;

                 fetch('/sidebar/save-setting', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                     body: JSON.stringify({ name: 'sidebar_components_import_context', value: this.importContext })
                 });
             }
             
             // Odoslanie stavu do user_settings
             fetch('/sidebar/save-setting', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                 body: JSON.stringify({ name: 'sidebar_open_category', value: this.openCategory })
             });
         },

         init() {
             if (this.importContext && Object.keys(this.importContext).length > 0) {
                 window.sidebarComponentsImportContext = this.importContext;
             }
         }
     }"
     x-on:component-catalog-updated.window="catalog = $event.detail.catalog"
     x-init="init()"
>
    <template x-for="(categories, tabKey) in catalog" :key="tabKey">
        <div x-show="activeTab === tabKey">
            <template x-for="category in categories" :key="category.category">
                <div style="margin-bottom:10px">
                    <button type="button" @click="toggleCategory(tabKey + '-' + slugify(category.category), tabKey, category.category)" :aria-expanded="openCategory === tabKey + '-' + slugify(category.category)" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 0;color:#3bc0c3;font-weight:600;background:none;border:0;text-align:left;width:100%">
                        <span x-text="openCategory === tabKey + '-' + slugify(category.category) ? '▼' : '►'" style="font-size:9px;width:10px"></span><span x-text="category.category"></span>
                    </button>
                    <div x-show="openCategory === tabKey + '-' + slugify(category.category)" style="padding-left:14px;display:flex;flex-direction:column;gap:4px;margin-top:2px">
                        <template x-for="item in category.items" :key="item.path || item.id">
                            <div class="tree-item" draggable="true" :data-id="item.id" @click="selectImportTarget(tabKey, category.category, item)" @dblclick="openImportEditor(tabKey, category.category, item)" :style="{ borderColor: selectedItemKey === itemKey(tabKey, category.category, item) ? '#3bc0c3' : 'transparent' }" style="padding:5px 8px;background:var(--wa-surface-raised);color:#fff;border-radius:3px;cursor:grab;font-size:12px;border:1px solid transparent;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                <span style="display:inline-flex;align-items:center;gap:6px;min-width:0"><span aria-hidden="true" x-text="tabKey === 'native' ? '📄' : tabKey === 'bootstrap' ? '📦' : '🧩'"></span><span x-text="item.name"></span></span>
                                <span style="display:inline-flex;align-items:center;gap:8px;margin-left:auto">
                                    <button type="button" class="wa-component-catalog-action" title="Upraviť komponent" :aria-label="'Upraviť ' + item.name" @click.stop="openImportEditor(tabKey, category.category, item)"><svg viewBox="0 0 16 16" width="12" height="12" fill="none" aria-hidden="true"><path d="M2 11.5V14H4.5L12 6.5L9.5 4L2 11.5Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M8.8 4.7L11.3 7.2" stroke="currentColor" stroke-width="1.2"/></svg></button>
                                    <span title="Zmazať" style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;opacity:.9;pointer-events:none;color:#ef4444"><svg viewBox="0 0 16 16" width="12" height="12" fill="none" aria-hidden="true"><path d="M2.5 4H13.5M6 2.8H10M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
