@props(['app' => [], 'layout' => [], 'components' => []])

@php
    $fixedPath = config_path('ui/content/components.json');
    $loadedComponents = file_exists($fixedPath) ? json_decode(file_get_contents($fixedPath), true) : ['native' => [], 'bootstrap' => []];

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
         openCategory: '{{ $savedCategory }}',
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

             window.dispatchEvent(new CustomEvent('open-modal-import', {
                 detail: {
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
     x-init="init()"
>
    @foreach($loadedComponents as $tabKey => $tabCategories)
        <div x-show="activeTab === '{{ $tabKey }}'" x-transition>
            @if(!empty($tabCategories) && is_array($tabCategories))
                @foreach($tabCategories as $category)
                    @php
                        $categoryName = (string) ($category['category'] ?? 'Kategória');
                        $catId = $tabKey . '-' . Str::slug($categoryName);
                        $tabIcon = $tabKey === 'native' ? '📄' : ($tabKey === 'bootstrap' ? '📦' : '🧩');
                    @endphp
                    <div style="margin-bottom: 10px;">
                        <div @click="toggleCategory('{{ $catId }}', '{{ $tabKey }}', '{{ addslashes($categoryName) }}')" style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 4px 0; color: #3bc0c3; font-weight: 600; user-select: none;">
                            <span x-text="openCategory === '{{ $catId }}' ? '▼' : '►'" style="font-size: 9px; width: 10px;"></span>
                            <span>{{ $categoryName }}</span>
                        </div>

                        <div x-show="openCategory === '{{ $catId }}'" x-transition style="padding-left: 14px; display: flex; flex-direction: column; gap: 4px; margin-top: 2px;">
                            @foreach(($category['items'] ?? []) as $item)
                                @php
                                    $itemId = (string) ($item['id'] ?? '');
                                    $itemName = (string) ($item['name'] ?? 'Položka');
                                    $itemKey = $tabKey . '|' . $categoryName . '|' . $itemId . '|' . $itemName;
                                @endphp
                                <div class="tree-item" draggable="true" data-id="{{ $itemId }}"
                                     @click="selectImportTarget('{{ $tabKey }}', '{{ addslashes($categoryName) }}', { id: '{{ addslashes($itemId) }}', name: '{{ addslashes($itemName) }}', path: '{{ addslashes((string)($item['path'] ?? '')) }}' })"
                                     :style="selectedItemKey === '{{ addslashes($itemKey) }}' ? 'padding: 5px 8px; background: var(--wa-surface-hover); color: #fff; border-radius: 3px; cursor: grab; font-size: 12px; border: 1px solid #3bc0c3; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; gap: 8px;' : 'padding: 5px 8px; background: var(--wa-surface-raised); color: #fff; border-radius: 3px; cursor: grab; font-size: 12px; border: 1px solid transparent; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; gap: 8px;'"
                                     style="padding: 5px 8px; background: var(--wa-surface-raised); color: #fff; border-radius: 3px; cursor: grab; font-size: 12px; border: 1px solid transparent; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; gap: 8px;"
                                     @mouseover="$el.style.borderColor='var(--wa-border-strong)'; $el.style.background='var(--wa-surface-hover)'"
                                     @mouseout="$el.style.borderColor='transparent'; $el.style.background='var(--wa-surface-raised)'">
                                    <span style="display: inline-flex; align-items: center; gap: 6px; min-width: 0;">
                                        <span>{{ $tabIcon }}</span>
                                        <span>{{ $item['name'] ?? 'Položka' }}</span>
                                    </span>
                                    <span style="display: inline-flex; align-items: center; gap: 8px; margin-left: auto;">
                                        <span title="Upravit"
                                              aria-label="Upravit"
                                              @click.stop="openImportEditor('{{ $tabKey }}', '{{ addslashes($categoryName) }}', { id: '{{ addslashes($itemId) }}', name: '{{ addslashes($itemName) }}', path: '{{ addslashes((string)($item['path'] ?? '')) }}' })"
                                                                                            style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; cursor: pointer; color: #3bc0c3;">
                                            <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <path d="M2 11.5V14H4.5L12 6.5L9.5 4L2 11.5Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                                                <path d="M8.8 4.7L11.3 7.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                        <span title="Zmazat"
                                              aria-label="Zmazat"
                                                                                            style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; pointer-events: none; color: #ef4444;">
                                            <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <path d="M2.5 4H13.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                                <path d="M6 2.8H10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                                <path d="M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

</div>
