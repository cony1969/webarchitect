@props(['layout' => []])

<section class="sidebar-block block-middle" 
         :style="{ height: blockMiddleHeight + 'px' }" 
         x-data="{
             layouts: window.sidebarLayoutsData || [],
             validationError: '',

             normalizeId(prefix, value) {
                 const raw = String(value ?? '').trim();
                 if (raw === '') {
                     return prefix + '-' + Date.now();
                 }
                 if (raw.startsWith(prefix + '-')) {
                     return raw;
                 }
                 const digits = raw.replace(/[^0-9]/g, '');
                 return prefix + '-' + (digits !== '' ? digits : Date.now());
             },

             normalizeLayouts(items) {
                 const seen = new Set();

                 return (items || [])
                     .map((item) => ({
                         ...item,
                         id: this.normalizeId('layout', item && item.id),
                         name: String(item && item.name ? item.name : '').trim()
                     }))
                     .filter((item) => {
                         if (item.name === '') {
                             return false;
                         }

                         const key = item.name.toLowerCase();
                         if (seen.has(key)) {
                             return false;
                         }

                         seen.add(key);
                         return true;
                     });
             },

             triggerModal() {
                 window.dispatchEvent(new CustomEvent('open-engine-modal', {
                     detail: { title: 'Pridať nový layout', callback: 'layout-modal-submit' }
                 }));
             },

             handleInflow(name) {
                 this.validationError = '';
                 const layoutName = String(name || '').trim();
                 if (!layoutName) {
                     this.validationError = 'Názov layoutu nemôže byť prázdny.';
                     return;
                 }

                 const duplicateExists = (this.layouts || []).some((item) =>
                     String(item && item.name ? item.name : '').trim().toLowerCase() === layoutName.toLowerCase()
                 );

                 if (duplicateExists) {
                     this.validationError = 'Layout s týmto názvom už existuje.';
                     return;
                 }

                 this.layouts.push({
                     id: this.normalizeId('layout', Date.now()),
                     name: layoutName
                 });

                 this.saveToDb();
             },

             openLayout(layout) {
                 window.dispatchEvent(new CustomEvent('add-new-tab', {
                     detail: {
                         title: layout.name,
                         type: 'layout'
                     }
                 }));
             },

             saveToDb() {
                 this.layouts = this.normalizeLayouts(this.layouts);

                 fetch('/api/ui/save-layouts', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify(this.layouts)
                 }).catch(() => {
                     // Keep UI responsive even if persistence fails.
                 });
             }
         }"
         @layout-modal-submit.window="handleInflow($event.detail.value)"
         style="display: flex; flex-direction: column; overflow: hidden; flex: none !important;"
>
    <x-ui.block-header :title="$layout['blocks']['block_2']['title'] ?? 'Layouts'" click="triggerModal()" />
    <div class="block-content" style="padding: 0 15px; flex: 1; overflow-y: auto; color: #fff;">
        <template x-if="validationError !== ''">
            <div style="margin: 8px 0 10px 0; padding: 6px 8px; border: 1px solid #ef4444; background: rgba(239, 68, 68, 0.12); color: #fecaca; border-radius: 4px; font-size: 12px;"
                 x-text="validationError"></div>
        </template>

        <template x-for="layoutItem in layouts" :key="layoutItem.id">
            <div style="margin-bottom: 6px;">
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 6px; background: #2a2e3d; border-radius: 3px; font-size: 12px; color: #fff; cursor: pointer;"
                     @click="openLayout(layoutItem)">
                    <span>📐</span>
                    <span x-text="layoutItem.name"></span>
                    <span title="Zmazat"
                          aria-label="Zmazat"
                          style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; margin-left: auto; pointer-events: none; color: #ef4444;">
                        <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M2.5 4H13.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            <path d="M6 2.8H10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            <path d="M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </div>
        </template>

        <template x-if="layouts.length === 0">
            <div style="color: #4e566d; text-align: center; padding-top: 20px; font-style: italic;">
                Kliknite na + pre vytvorenie layoutu.
            </div>
        </template>
    </div>
</section>
