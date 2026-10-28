@props([
    'id' => 'modal-import',
    'title' => 'Import komponentu',
    'openEvent' => 'open-modal-import',
    'submitEvent' => 'modal-import-submit',
    'initialHtml' => "<div class=\"component\">\n  <h3>Novy komponent</h3>\n  <p>Sem vlozte HTML obsah...</p>\n</div>",
])

<div id="{{ $id }}"
     x-data="{
         show: false,
         loading: false,
         noticeVisible: false,
         noticeMessage: '',
         activeTab: '',
         activeCategory: '',
         targetPath: '',
         itemPath: '',
         componentName: '',
         componentSlug: '',
         componentHtml: @js($initialHtml),
         editorMode: 'import',

         showInfo(message) {
             this.noticeMessage = message;
             this.noticeVisible = true;
             setTimeout(() => {
                 this.noticeVisible = false;
             }, 3000);
         },

         slugify(value) {
             if (!value) {
                 return '';
             }

             return value
                 .toString()
                 .normalize('NFD')
                 .replace(/[\u0300-\u036f]/g, '')
                 .toLowerCase()
                 .replace(/[^a-z0-9]+/g, '-')
                 .replace(/^-+|-+$/g, '');
         },

         syncSlug() {
             this.componentSlug = this.slugify(this.componentName);
         },

         missingJsonFallbackHtml() {
             return '';
         },

         init() {
             window.addEventListener(@js($openEvent), (e) => this.open(e.detail || {}));
         },

         open(detail = {}) {
             this.show = true;
             this.editorMode = 'import';
             this.activeTab = detail.tab || '';
             this.activeCategory = detail.category || '';
             this.targetPath = detail.targetPath || this.slugify(this.activeTab || 'components');
             this.itemPath = detail.itemPath || '';
             this.componentName = detail.name || '';
             this.componentSlug = detail.slug || this.slugify(this.componentName);
             this.componentHtml = detail.loadFromJson
                 ? ''
                 : (detail.html || @js($initialHtml));

             if (detail.loadFromJson) {
                 this.loadExistingJson();
             }
         },

         async loadExistingJson() {
             this.loading = true;
             try {
                 const response = await fetch(@js(route('ui.components.load')), {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': @js(csrf_token()),
                         'Accept': 'application/json'
                     },
                     body: JSON.stringify({
                         item_path: this.itemPath,
                         target_path: this.targetPath,
                         slug: this.componentSlug
                     })
                 });

                 const data = await response.json().catch(() => ({}));
                 if (!response.ok || !data.success) {
                     throw new Error(data.message || 'Nepodarilo sa nacitat JSON komponentu.');
                 }

                 if (data.exists) {
                     this.componentHtml = data.content || this.componentHtml;
                     this.editorMode = 'json';
                     if (data.name) {
                         this.componentName = data.name;
                     }
                     if (data.slug) {
                         this.componentSlug = this.slugify(data.slug);
                     }
                     if (data.target_path) {
                         this.targetPath = data.target_path;
                     }
                     this.showInfo('Nacitany existujuci JSON: ' + (data.file || '')); 
                 } else {
                     this.editorMode = 'import';
                     this.componentHtml = this.missingJsonFallbackHtml();
                     this.showInfo('JSON neexistuje, spusteny import rezim.');
                 }
             } catch (error) {
                 alert(error.message || 'Nacitavanie JSON zlyhalo.');
             } finally {
                 this.loading = false;
             }
         },

         close() {
             this.show = false;
         },

         async save() {
             this.syncSlug();
             if (!this.componentName || !this.componentSlug || !this.componentHtml) {
                 alert('Vypln nazov, slug a obsah.');
                 return;
             }

             this.loading = true;
             try {
                 const response = await fetch(@js(route('ui.components.import-html')), {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': @js(csrf_token()),
                         'Accept': 'application/json'
                     },
                     body: JSON.stringify({
                         tab: this.activeTab,
                         category: this.activeCategory,
                         target_path: this.targetPath,
                         name: this.componentName,
                         slug: this.componentSlug,
                         html: this.editorMode === 'json' ? '' : this.componentHtml,
                         json_content: this.editorMode === 'json' ? this.componentHtml : null
                     })
                 });

                 const data = await response.json().catch(() => ({}));
                 if (!response.ok || !data.success) {
                     throw new Error(data.message || 'Import sa nepodaril.');
                 }

                 window.dispatchEvent(new CustomEvent(@js($submitEvent), {
                     detail: {
                         tab: this.activeTab,
                         category: this.activeCategory,
                         targetPath: this.targetPath,
                        itemPath: this.itemPath,
                         name: this.componentName,
                         slug: this.componentSlug,
                         html: this.componentHtml,
                         file: data.file || null,
                     }
                 }));

                 this.show = false;
                 this.showInfo('Import uspesny: ' + (data.file || (this.componentSlug + '.json')));
                 setTimeout(() => {
                     window.location.reload();
                 }, 3000);
             } catch (error) {
                 alert(error.message || 'Import sa nepodaril.');
             } finally {
                 this.loading = false;
             }
         }
     }"
     x-init="init()"
     x-cloak>

    <div x-show="noticeVisible"
         x-transition
         style="position: fixed; top: 14px; right: 16px; z-index: 1500; background: #1f8d72; color: #e9fff8; border: 1px solid #38b898; border-radius: 6px; padding: 10px 14px; font-size: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.28);"
         x-text="noticeMessage"></div>

    <template x-if="show">
        <div style="position: fixed; inset: 0; z-index: 1300; background: rgba(0, 0, 0, 0.58); display: flex; align-items: center; justify-content: center;"
             @click="close()"
             @keydown.escape.window="close()">
            <div style="width: min(860px, calc(100vw - 40px)); max-height: calc(100vh - 60px); background: var(--wa-surface-base); border: 1px solid var(--wa-border); border-radius: 8px; overflow: hidden; box-shadow: 0 18px 50px rgba(0,0,0,0.45); display: flex; flex-direction: column;"
                 @click.stop>
                <div style="padding: 12px 16px; border-bottom: 1px solid var(--wa-border); display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0; font-size: 14px; font-weight: 600; color: #3bc0c3;" x-text="@js($title)"></h5>
                    <button type="button"
                            @click="close()"
                            style="background: transparent; border: 0; color: #b7c1dd; font-size: 16px; cursor: pointer;">x</button>
                </div>

                <div style="padding: 14px 16px; overflow: auto; flex: 1;">
                    <div style="display: grid; gap: 12px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 11px; color: #8a94ad; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase;">Nazov komponentu</label>
                            <input type="text"
                                   x-model="componentName"
                                   @input="syncSlug()"
                                   placeholder="Napr. Hero Banner"
                                   style="width: 100%; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); color: #eef2ff; border-radius: 4px; padding: 8px 10px; font-size: 13px;" />
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 11px; color: #8a94ad; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase;">Slug</label>
                            <input type="text"
                                   x-model="componentSlug"
                                   readonly
                                   style="width: 100%; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); color: #7fd7de; border-radius: 4px; padding: 8px 10px; font-size: 13px;" />
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 11px; color: #8a94ad; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase;">Cieľová cesta v config/ui/components</label>
                            <input type="text"
                                   x-model="targetPath"
                                   readonly
                                   style="width: 100%; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); color: #f7d46a; border-radius: 4px; padding: 8px 10px; font-size: 13px;" />
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 6px; font-size: 11px; color: #8a94ad; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase;" x-text="editorMode === 'json' ? 'JSON editor' : 'HTML editor'"></label>
                            <textarea x-model="componentHtml"
                                      style="width: 100%; height: 290px; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); color: #eef2ff; border-radius: 4px; padding: 10px; font-size: 12px; font-family: monospace; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>

                <div style="padding: 12px 16px; border-top: 1px solid var(--wa-border); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button"
                            @click="close()"
                            style="background: var(--wa-surface-hover); border: 1px solid var(--wa-border-strong); color: #fff; border-radius: 4px; font-size: 12px; padding: 6px 14px;">Zrusit</button>
                    <button type="button"
                            @click="save()"
                            :disabled="loading"
                            style="background: #3bc0c3; border: none; color: var(--wa-surface-base); font-weight: 700; border-radius: 4px; font-size: 12px; padding: 6px 14px;">Importovat</button>
                </div>
            </div>
        </div>
    </template>
</div>
